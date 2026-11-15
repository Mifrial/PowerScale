import { computed, ref } from 'vue';
import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSaveSnapshot } from '@/modules/Roleplay/Scene/Dto/SceneSaveSnapshot';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneToolDefinition } from '@/modules/Roleplay/Scene/Dto/SceneToolDefinition';
import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { SceneDecorZone } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import { SceneDraftMapper } from '@/modules/Roleplay/Scene/Service/SceneDraftMapper';
import { SceneDraftService } from '@/modules/Roleplay/Scene/Service/SceneDraftService';
import { SceneToolShape } from '@/modules/Roleplay/Scene/Service/SceneToolShape';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';
import { sceneVertexService } from '@/modules/Roleplay/Scene/Service/Instance/sceneVertexService';
import {
  sceneFootprintLocalOffset,
  sceneFootprintLocalPoint,
  sceneSilhouetteLocalPoint,
} from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';

/** Предел числа шагов истории. */
const HISTORY_LIMIT = 50;
/** Предел объёма истории в байтах UTF-16. */
const HISTORY_BYTES_LIMIT = 8 * 1024 * 1024;

/**
 * Черновик редактора локации: состояние и операции над ним.
 *
 * Все мутации идут через сервис и помечают черновик изменённым; компонент
 * только вызывает методы. История хранит JSON-снимки и группирует
 * перетаскивания в одну транзакцию.
 */
export function useSceneDraft(initial: SceneDetail) {
  const mapper = new SceneDraftMapper();
  const service = new SceneDraftService();
  const shapeBuilder = new SceneToolShape();
  const draft = ref<SceneDraft>(mapper.fromDetail(initial));
  const isDirty = ref(false);
  const selection = ref<number[]>([]);
  const history = ref<string[]>([]);
  const future = ref<string[]>([]);
  let baselineJson = serialize(draft.value);
  let currentJson = baselineJson;
  let inTransaction = false;
  let pendingChange = false;
  let transactionJson = '';

  const activeBoard = computed<SceneDraftBoard | null>(
    () => draft.value.boards.find((board) => board.id === draft.value.activeBoardId) ?? null,
  );
  const selectedElements = computed(() =>
    draft.value.elements.filter((element) => selection.value.includes(element.id)),
  );
  const canUndo = computed(() => history.value.length > 0);
  const canRedo = computed(() => future.value.length > 0);

  /** Заменяет черновик сохранённым снимком и сбрасывает историю. */
  function reset(detail: SceneDetail): void {
    draft.value = mapper.fromDetail(detail);
    selection.value = [];
    isDirty.value = false;
    baselineJson = serialize(draft.value);
    currentJson = baselineJson;
    history.value = [];
    future.value = [];
    inTransaction = false;
    pendingChange = false;
  }

  /** Начинает транзакцию: перетаскивание даст один шаг истории. */
  function beginEdit(): void {
    if (inTransaction) {
      return;
    }

    inTransaction = true;
    pendingChange = false;
    transactionJson = currentJson;
  }

  /** Завершает транзакцию и фиксирует один шаг. */
  function endEdit(): void {
    if (!inTransaction) {
      return;
    }

    inTransaction = false;
    if (pendingChange) {
      pushHistory(history.value, transactionJson);
      currentJson = serialize(draft.value);
      future.value = [];
    }

    pendingChange = false;
    transactionJson = '';
  }

  /** Отменяет транзакцию без шага истории. */
  function cancelEdit(): void {
    inTransaction = false;
    pendingChange = false;
    transactionJson = '';
  }

  /** Шаг назад. */
  function undo(): void {
    const snapshot = history.value.pop();
    if (snapshot === undefined) {
      return;
    }

    future.value.push(currentJson);
    draft.value = JSON.parse(snapshot) as SceneDraft;
    currentJson = serialize(draft.value);
    isDirty.value = currentJson !== baselineJson;
    selection.value = selection.value.filter((id) => draft.value.elements.some((element) => element.id === id));
    inTransaction = false;
    pendingChange = false;
  }

  /** Шаг вперёд. */
  function redo(): void {
    const snapshot = future.value.pop();
    if (snapshot === undefined) {
      return;
    }

    history.value.push(currentJson);
    draft.value = JSON.parse(snapshot) as SceneDraft;
    currentJson = serialize(draft.value);
    isDirty.value = currentJson !== baselineJson;
    selection.value = selection.value.filter((id) => draft.value.elements.some((element) => element.id === id));
    inTransaction = false;
    pendingChange = false;
  }

  /** Помечает черновик изменённым и фиксирует шаг. */
  function markDirty(): void {
    isDirty.value = true;
    if (inTransaction) {
      pendingChange = true;

      return;
    }

    pushHistory(history.value, currentJson);
    currentJson = serialize(draft.value);
    future.value = [];
  }

  /** Имя и описание локации. */
  function setMeta(patch: Partial<Pick<SceneDraft, 'name' | 'description'>>): void {
    Object.assign(draft.value, patch);
    markDirty();
  }

  /**
   * Меняет точность локации и пересчитывает координаты.
   *
   * Разрешение обязано быть степенью двойки: так ипари переводятся в единицы
   * сцены без дробей.
   */
  function setResolution(next: number): boolean {
    if (!sceneUnits.isPowerOfTwo(next) || next < 1 || next > 1024) {
      return false;
    }

    if (next === draft.value.resolution) {
      return true;
    }

    service.rescale(draft.value, next);
    draft.value.boards = [...draft.value.boards];
    draft.value.elements = [...draft.value.elements];
    draft.value.enclosures = [...draft.value.enclosures];
    markDirty();

    return true;
  }

  /** Выбирает активное поле. */
  function setActiveBoard(boardId: number): void {
    draft.value.activeBoardId = boardId;
    selection.value = [];
  }

  /** Добавляет поле и делает его активным. */
  function addBoard(): number {
    const board = service.addBoard(draft.value, service.freeBoardName(draft.value));
    draft.value.boards = [...draft.value.boards];
    draft.value.activeBoardId = board.id;
    selection.value = [];
    markDirty();

    return board.id;
  }

  /** Переименовывает поле, если имя свободно. */
  function renameBoard(boardId: number, name: string): boolean {
    const trimmed = name.trim();
    if (trimmed === '' || service.isBoardNameTaken(draft.value, trimmed, boardId)) {
      return false;
    }

    const board = draft.value.boards.find((item) => item.id === boardId);
    if (board === undefined) {
      return false;
    }

    board.name = trimmed;
    draft.value.boards = [...draft.value.boards];
    markDirty();

    return true;
  }

  /** Удаляет поле вместе с элементами. */
  function removeBoard(boardId: number): boolean {
    if (!service.removeBoard(draft.value, boardId)) {
      return false;
    }

    selection.value = [];
    markDirty();

    return true;
  }

  /** Сдвигает поле в списке. */
  function moveBoard(boardId: number, offset: number): boolean {
    if (!service.moveBoard(draft.value, boardId, offset)) {
      return false;
    }

    draft.value.boards = [...draft.value.boards];
    markDirty();

    return true;
  }

  /** Меняет параметры поля. */
  function updateBoard(boardId: number, patch: Partial<Omit<SceneSaveBoard, 'id' | 'sortOrder'>>): void {
    const board = draft.value.boards.find((item) => item.id === boardId);
    if (board === undefined) {
      return;
    }

    const oldWidth = board.boundWidth;
    const oldHeight = board.boundHeight;
    Object.assign(board, patch);
    if (board.boundWidth !== oldWidth || board.boundHeight !== oldHeight) {
      service.stretchBoardTerrain(draft.value, boardId, oldWidth, oldHeight);
      draft.value.elements = [...draft.value.elements];
    }

    draft.value.boards = [...draft.value.boards];
    markDirty();
  }

  /** Заливает поле террейном выбранного шаблона. */
  function fillTerrain(kind: string, boardId: number | null = null): number | null {
    const targetBoardId = boardId ?? draft.value.activeBoardId;
    if (targetBoardId === null) {
      return null;
    }

    const element = service.fillBoardTerrain(draft.value, targetBoardId, kind);
    if (element === null) {
      return null;
    }

    draft.value.elements = [...draft.value.elements];
    selection.value = [element.id];
    markDirty();

    return element.id;
  }

  /** Растягивает выделенный террейн на всё поле. */
  function stretchTerrain(elementId: number): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    const board = draft.value.boards.find((item) => item.id === element.boardId);
    if (board === undefined) {
      return;
    }

    element.shape = service.fullBoardShape(board.boundWidth, board.boundHeight);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Меняет подложку поля и её пиксельные размеры. */
  function setBackdrop(boardId: number, publicId: string | null, width: number | null, height: number | null): void {
    const board = draft.value.boards.find((item) => item.id === boardId);
    if (board === undefined) {
      return;
    }

    board.filePublicId = publicId;
    board.fileWidth = width;
    board.fileHeight = height;
    draft.value.boards = [...draft.value.boards];
    markDirty();
  }

  /** Создаёт элемент по инструменту и геометрии. */
  function createElement(tool: SceneToolDefinition, shape: SceneShape, props?: SceneElementProps): number | null {
    const boardId = draft.value.activeBoardId;
    if (boardId === null || tool.kind === null) {
      return null;
    }

    const element = service.addElement(draft.value, boardId, tool.kind, shape, { ...tool.props, ...props });
    draft.value.elements = [...draft.value.elements];
    selection.value = [element.id];
    markDirty();

    return element.id;
  }

  /** Создаёт элемент по протяжке указателя: фигура строится в границах поля. */
  function createFromDrag(tool: SceneToolDefinition, from: ScenePoint, to: ScenePoint): number | null {
    const board = activeBoard.value;
    if (board === null || tool.shapeType === null) {
      return null;
    }

    const shape = shapeBuilder.build(tool.shapeType, from, to, {
      x: 0,
      y: 0,
      width: board.boundWidth,
      height: board.boundHeight,
    });
    if (shape === null) {
      return null;
    }

    return createElement(tool, shape);
  }

  /** Создаёт ленту по кликам: постоянная ширина и плавные ручки. */
  function createPath(
    tool: SceneToolDefinition,
    points: ScenePoint[],
    width: number,
    props?: SceneElementProps,
  ): number | null {
    if (points.length < 2) {
      return null;
    }

    return createElement(tool, service.pathShape(points, width), props);
  }

  /** Меняет свойства элемента. */
  function updateElementProps(elementId: number, props: SceneElementProps): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    element.props = { ...props };
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Задаёт целевое поле проёма. */
  function setElementTarget(elementId: number, boardId: number | null): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    element.targetBoardId = boardId;
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Задаёт ассет декора и его видимую рамку. */
  function setElementAsset(
    elementId: number,
    publicId: string | null,
    contentBox: ImageContentBox | null = null,
  ): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    element.assetPublicId = publicId;
    element.assetContentBox = publicId === null ? null : contentBox;
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Меняет геометрию элемента. */
  function updateElementShape(elementId: number, shape: SceneShape): void {
    service.updateElementShape(draft.value, elementId, shape);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Двигает вершину полигона или ленты в пределах поля. */
  function moveVertex(elementId: number, index: number, point: ScenePoint): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    const board = draft.value.boards.find((item) => item.id === element.boardId);
    if (board === undefined) {
      return;
    }

    const clamped: ScenePoint = [
      Math.min(board.boundWidth, Math.max(0, Math.round(point[0]))),
      Math.min(board.boundHeight, Math.max(0, Math.round(point[1]))),
    ];
    service.setVertex(draft.value, elementId, index, clamped);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Двигает ручку Безье; по умолчанию противоположная ручка зеркальна. */
  function moveHandle(
    elementId: number,
    index: number,
    side: SceneHandleSide,
    offset: ScenePoint,
    mirror: boolean,
  ): void {
    service.setHandle(draft.value, elementId, index, side, offset);
    if (mirror) {
      service.setHandle(draft.value, elementId, index, side === 'in' ? 'out' : 'in', [-offset[0], -offset[1]]);
    }

    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Вставляет вершину в ближайший сегмент, сохраняя форму кривой. */
  function insertVertex(elementId: number, point: ScenePoint, tolerance: number): boolean {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return false;
    }

    const insertion = sceneVertexService.insertion(element.shape, point, tolerance);
    if (insertion === null) {
      return false;
    }

    const split = sceneVertexService.split(element.shape, insertion);
    if (split === null) {
      return false;
    }

    service.setHandle(draft.value, elementId, split.index, 'out', split.startHandle);
    const endIndex =
      element.shape.type === 'polygon' ? (split.index + 1) % element.shape.points.length : split.index + 1;
    service.setHandle(draft.value, elementId, endIndex, 'in', split.endHandle);
    service.insertVertex(draft.value, elementId, split.index, split.point, split.handle, split.width);
    draft.value.elements = [...draft.value.elements];
    markDirty();

    return true;
  }

  /** Удаляет вершину, если фигура остаётся допустимой. */
  function removeVertex(elementId: number, index: number): void {
    service.removeVertex(draft.value, elementId, index);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Двигает вершину зоны декора; точка приходит в мировых координатах. */
  function moveZoneVertex(elementId: number, zone: SceneDecorZone, index: number, point: ScenePoint): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined || element.shape.type !== 'point') {
      return;
    }

    const local =
      zone === 'footprint' ? sceneFootprintLocalPoint(element, point) : sceneSilhouetteLocalPoint(element, point);
    service.setZoneVertex(draft.value, elementId, zone, index, local);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Двигает ручку Безье зоны; смещение приходит в мировых координатах. */
  function moveZoneHandle(
    elementId: number,
    zone: SceneDecorZone,
    index: number,
    side: SceneHandleSide,
    offset: ScenePoint,
    mirror: boolean,
  ): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined || element.shape.type !== 'point') {
      return;
    }

    const local = sceneFootprintLocalOffset(element, offset);
    service.setZoneHandle(draft.value, elementId, zone, index, side, local);
    if (mirror) {
      service.setZoneHandle(draft.value, elementId, zone, index, side === 'in' ? 'out' : 'in', [-local[0], -local[1]]);
    }

    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Вставляет вершину в зону, сохраняя форму кривой. */
  function insertZoneVertex(elementId: number, zone: SceneDecorZone, point: ScenePoint, tolerance: number): boolean {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined || element.shape.type !== 'point') {
      return false;
    }

    const local =
      zone === 'footprint' ? sceneFootprintLocalPoint(element, point) : sceneSilhouetteLocalPoint(element, point);
    const inserted = service.insertZoneVertex(draft.value, elementId, zone, local, tolerance);
    if (!inserted) {
      return false;
    }

    draft.value.elements = [...draft.value.elements];
    markDirty();

    return true;
  }

  /** Удаляет вершину зоны; минимум три точки. */
  function removeZoneVertex(elementId: number, zone: SceneDecorZone, index: number): void {
    service.removeZoneVertex(draft.value, elementId, zone, index);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Сглаживает полигон зоны. */
  function smoothZone(elementId: number, zone: SceneDecorZone): void {
    service.smoothZone(draft.value, elementId, zone);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Убирает кривизну зоны. */
  function cornerZone(elementId: number, zone: SceneDecorZone): void {
    service.cornerZone(draft.value, elementId, zone);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Ширина ленты в вершине; остальные ширины масштабируются. */
  function setPathWidth(elementId: number, index: number, width: number): void {
    service.setWidth(draft.value, elementId, index, width);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Одна ширина на все вершины ленты. */
  function setPathWidthAll(elementId: number, width: number): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined || element.shape.type !== 'path') {
      return;
    }

    element.shape = { ...element.shape, widths: element.shape.points.map(() => Math.max(1, Math.round(width))) };
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Сглаживает углы полигона или ленты. */
  function smoothShape(elementId: number): void {
    service.smoothShape(draft.value, elementId);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Убирает кривизну: все сегменты прямые. */
  function cornerShape(elementId: number): void {
    service.cornerShape(draft.value, elementId);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Сдвигает элементы на дельту в единицах сцены. */
  function moveElements(elementIds: number[], deltaX: number, deltaY: number): void {
    if (deltaX === 0 && deltaY === 0) {
      return;
    }

    for (const elementId of elementIds) {
      service.moveElement(draft.value, elementId, deltaX, deltaY);
    }

    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Удаляет выделенные элементы. */
  function removeSelected(): void {
    for (const elementId of selection.value) {
      service.removeElement(draft.value, elementId);
    }

    draft.value.elements = [...draft.value.elements];
    selection.value = [];
    markDirty();
  }

  /** Дублирует выделение; копии становятся новым выделением. */
  function duplicateSelected(): void {
    const offset = Math.max(1, Math.round(draft.value.resolution / 4));
    const copies = selection.value
      .map((elementId) => service.duplicateElement(draft.value, elementId, offset))
      .filter((element): element is NonNullable<typeof element> => element !== null);
    if (copies.length === 0) {
      return;
    }

    draft.value.elements = [...draft.value.elements];
    selection.value = copies.map((element) => element.id);
    markDirty();
  }

  /** Задаёт выделение. */
  function select(elementIds: number[]): void {
    selection.value = elementIds;
  }

  /** Снимок для `scene.save`. */
  function snapshot(): SceneSaveSnapshot {
    return mapper.toSnapshot(draft.value);
  }

  return {
    draft,
    isDirty,
    selection,
    activeBoard,
    selectedElements,
    canUndo,
    canRedo,
    reset,
    beginEdit,
    endEdit,
    cancelEdit,
    undo,
    redo,
    setMeta,
    setResolution,
    setActiveBoard,
    addBoard,
    renameBoard,
    removeBoard,
    moveBoard,
    updateBoard,
    setBackdrop,
    fillTerrain,
    stretchTerrain,
    createElement,
    createFromDrag,
    createPath,
    updateElementProps,
    setElementTarget,
    setElementAsset,
    duplicateSelected,
    updateElementShape,
    moveVertex,
    moveHandle,
    insertVertex,
    removeVertex,
    moveZoneVertex,
    moveZoneHandle,
    insertZoneVertex,
    removeZoneVertex,
    smoothZone,
    cornerZone,
    setPathWidth,
    setPathWidthAll,
    smoothShape,
    cornerShape,
    moveElements,
    removeSelected,
    select,
    snapshot,
  };
}

/** JSON-снимок черновика. */
function serialize(draft: SceneDraft): string {
  return JSON.stringify(draft);
}

/** Добавляет снимок в историю и держит её в разумных пределах. */
function pushHistory(history: string[], snapshot: string): void {
  history.push(snapshot);
  let bytes = history.reduce((sum, entry) => sum + entry.length * 2, 0);
  while (history.length > 5 && (history.length > HISTORY_LIMIT || bytes > HISTORY_BYTES_LIMIT)) {
    const removed = history.shift();
    bytes -= (removed?.length ?? 0) * 2;
  }
}
