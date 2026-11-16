import { computed, ref } from 'vue';
import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRectHandle } from '@/modules/Roleplay/Scene/Dto/SceneRectHandle';
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
import { sceneWallService } from '@/modules/Roleplay/Scene/Service/Instance/sceneWallService';
import { sceneWallGraphService } from '@/modules/Roleplay/Scene/Service/Instance/sceneWallGraphService';
import { SCENE_WALL } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WALL';
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

  /**
   * Создаёт этаж по протяжке плана и переключает на него.
   *
   * Поле этажа шире плана на поля обзора: из окна видна поляна родителя.
   * Вложение хранит область родителя, поэтому переход между полями знает
   * общий масштаб и смещение.
   */
  function addFloor(from: ScenePoint, to: ScenePoint): number | null {
    const result = service.addFloor(draft.value, from, to, draft.value.resolution);
    if (result === null) {
      return null;
    }

    draft.value.boards = [...draft.value.boards];
    draft.value.elements = [...draft.value.elements];
    draft.value.enclosures = [...draft.value.enclosures];
    draft.value.activeBoardId = result.board.id;
    selection.value = [];
    markDirty();

    return result.board.id;
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

  /** Меняет порядок отрисовки элемента в его слое. */
  function setSortOrder(elementId: number, sortOrder: number): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    element.sortOrder = Math.max(0, Math.round(sortOrder));
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Меняет размер прямоугольника, сохраняя его центр. */
  function setRectSize(elementId: number, width: number, height: number): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined || element.shape.type !== 'rect') {
      return;
    }

    const board = draft.value.boards.find((item) => item.id === element.boardId);
    if (board === undefined) {
      return;
    }

    const w = Math.max(1, Math.min(board.boundWidth, Math.round(width)));
    const h = Math.max(1, Math.min(board.boundHeight, Math.round(height)));
    const cx = element.shape.x + element.shape.w / 2;
    const cy = element.shape.y + element.shape.h / 2;
    const x = Math.max(0, Math.min(board.boundWidth - w, Math.round(cx - w / 2)));
    const y = Math.max(0, Math.min(board.boundHeight - h, Math.round(cy - h / 2)));

    element.shape = { ...element.shape, x, y, w, h };
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
  function createFromDrag(
    tool: SceneToolDefinition,
    from: ScenePoint,
    to: ScenePoint,
    props?: SceneElementProps,
  ): number | null {
    const board = activeBoard.value;
    if (board === null || tool.shapeType === null) {
      return null;
    }

    const shape = shapeBuilder.build(
      tool.shapeType,
      from,
      to,
      {
        x: 0,
        y: 0,
        width: board.boundWidth,
        height: board.boundHeight,
      },
      tool.wallWidthIpari === undefined ? undefined : draft.value.resolution * tool.wallWidthIpari,
      tool.wallMode,
    );
    if (shape === null) {
      return null;
    }

    return createElement(tool, shape, props);
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
    // Смещение вдоль стены двигает проём, а не растягивает его: ширина
    // остаётся прежней, конец пересчитывается от нового начала.
    if (sceneWallService.isOpening(element) && element.props.wallId !== undefined) {
      const wall = draft.value.elements.find((item) => item.id === element.props.wallId);
      if (wall !== undefined && sceneWallService.isWall(wall)) {
        const length = sceneWallService.length(wall);
        const width = Math.max(1, Math.min(sceneWallService.length(element), length));
        const offset = Math.max(0, Math.min(Math.max(0, length - width), Math.round(element.props.offset ?? 0)));
        const shape = sceneWallService.spanShape(wall, offset, offset + width);
        if (shape !== null) {
          element.props.offset = offset;
          element.shape = shape;
        }
      }
    }

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
  /** Меняет размер прямоугольника от ручки; противоположный угол стоит. */
  function resizeRect(elementId: number, handle: SceneRectHandle, point: ScenePoint): void {
    service.resizeRect(draft.value, elementId, handle, point);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Поворот прямоугольной опоры. */
  function setRotation(elementId: number, angle: number): void {
    service.setRotation(draft.value, elementId, angle);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  function moveVertex(elementId: number, index: number, point: ScenePoint): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (
      element === undefined ||
      (element.shape.type !== 'polygon' && element.shape.type !== 'path' && element.shape.type !== 'wall')
    ) {
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
    service.setVertexShared(draft.value, elementId, index, clamped);
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

  /** Одна ширина на все вершины ленты; у стены — толщина, у проёма — длина. */
  function setPathWidthAll(elementId: number, width: number): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    if (element.shape.type === 'path') {
      element.shape = {
        ...element.shape,
        widths: element.shape.points.map(() => Math.max(1, Math.round(width))),
      };
    } else if (element.shape.type === 'wall') {
      if (sceneWallService.isOpening(element)) {
        sceneWallService.resize(draft.value.elements, element, width);
      } else {
        element.shape = { ...element.shape, width: Math.max(1, Math.round(width)) };
        sceneWallService.sync(draft.value.elements, elementId);
      }
    } else {
      return;
    }

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

    // Пол, совпадающий с гранью, тянет грань целиком: пол комнаты — комнату,
    // пол дома — всё строение. Иначе «взять дом за пол» оставлял бы стены на
    // месте.
    const ids = new Set(elementIds);
    for (const elementId of elementIds) {
      const element = draft.value.elements.find((item) => item.id === elementId);
      if (element === undefined || element.kind !== 'terrain') {
        continue;
      }

      const bounds = service.boardBounds(draft.value, element.boardId);
      if (bounds === null) {
        continue;
      }

      for (const id of sceneWallGraphService.buildingOfTerrain(
        draft.value.elements,
        element.boardId,
        element.id,
        bounds,
      )) {
        ids.add(id);
      }
    }

    const movedIds = [...ids];
    const selected = new Set(movedIds);
    const delta = service.groupDelta(draft.value, movedIds, deltaX, deltaY);
    if (delta[0] === 0 && delta[1] === 0) {
      return;
    }

    // Границы перемещаемых стен до сдвига: по ним видно, что едет всё
    // строение, а не комната внутри него.
    let minX = Number.POSITIVE_INFINITY;
    let minY = Number.POSITIVE_INFINITY;
    let maxX = Number.NEGATIVE_INFINITY;
    let maxY = Number.NEGATIVE_INFINITY;
    for (const element of draft.value.elements) {
      if (!selected.has(element.id) || !sceneWallService.isWall(element) || element.shape.type !== 'wall') {
        continue;
      }

      for (const point of element.shape.points) {
        minX = Math.min(minX, point[0]);
        minY = Math.min(minY, point[1]);
        maxX = Math.max(maxX, point[0]);
        maxY = Math.max(maxY, point[1]);
      }
    }
    const wallBounds: SceneBounds | null = Number.isFinite(minX)
      ? { x: minX, y: minY, width: maxX - minX, height: maxY - minY }
      : null;

    // Вершины выбранных стен до сдвига: по ним находим общие узлы у соседних
    // стен. Соседняя стена не выделена, но её узел должен поехать вместе с
    // узлом выбранной — иначе сеть рвётся.
    const moved = new Set<string>();
    for (const element of draft.value.elements) {
      if (!selected.has(element.id) || !sceneWallService.isWall(element) || element.shape.type !== 'wall') {
        continue;
      }

      for (const point of element.shape.points) {
        moved.add(`${element.boardId}:${point[0]},${point[1]}`);
      }
    }

    for (const elementId of movedIds) {
      service.moveElement(draft.value, elementId, delta[0], delta[1], false);
    }

    for (const element of draft.value.elements) {
      if (selected.has(element.id) || element.shape.type !== 'wall') {
        continue;
      }

      let changed = false;
      const points = element.shape.points.map((point): ScenePoint => {
        if (moved.has(`${element.boardId}:${point[0]},${point[1]}`)) {
          changed = true;

          return [point[0] + delta[0], point[1] + delta[1]];
        }

        return point;
      });

      if (changed) {
        element.shape = { ...element.shape, points };
        sceneWallService.sync(draft.value.elements, element.id);
      }
    }

    // Этаж — отдельное поле; его пятно на родителе едет вместе со строением.
    // Иначе после перетаскивания дома переход между полями разъедется.
    if (wallBounds !== null) {
      const tolerance = Math.max(2, Math.round(draft.value.resolution / 2));
      for (const enclosure of draft.value.enclosures) {
        if (enclosure.parentBoardId !== draft.value.activeBoardId || enclosure.footprint.type !== 'rect') {
          continue;
        }

        const floors = draft.value.elements.filter(
          (element) =>
            element.boardId === enclosure.childBoardId && element.kind === 'terrain' && element.shape.type === 'rect',
        );
        const floor = floors.sort(
          (left, right) =>
            (right.shape.type === 'rect' ? right.shape.w * right.shape.h : 0) -
            (left.shape.type === 'rect' ? left.shape.w * left.shape.h : 0),
        )[0];
        if (floor === undefined || floor.shape.type !== 'rect') {
          continue;
        }

        const footprint = enclosure.footprint;
        const expected = {
          x: footprint.x + floor.shape.x,
          y: footprint.y + floor.shape.y,
          width: floor.shape.w,
          height: floor.shape.h,
        };
        if (
          Math.abs(expected.x - wallBounds.x) <= tolerance &&
          Math.abs(expected.y - wallBounds.y) <= tolerance &&
          Math.abs(expected.width - wallBounds.width) <= tolerance &&
          Math.abs(expected.height - wallBounds.height) <= tolerance
        ) {
          enclosure.footprint = {
            ...footprint,
            x: footprint.x + delta[0],
            y: footprint.y + delta[1],
          };
          draft.value.enclosures = [...draft.value.enclosures];
        }
      }
    }

    // Стена тянет за собой вставленные проёмы, а перетащенный проём заново
    // привязывается к ближайшей стене.
    for (const elementId of ids) {
      const element = draft.value.elements.find((item) => item.id === elementId);
      if (element !== undefined && sceneWallService.isWall(element)) {
        sceneWallService.sync(draft.value.elements, elementId);
      }
    }

    for (const elementId of ids) {
      const element = draft.value.elements.find((item) => item.id === elementId);
      if (element === undefined || !sceneWallService.isOpening(element)) {
        continue;
      }

      const placement = sceneWallService.attach(
        draft.value.elements,
        element,
        draft.value.resolution * SCENE_WALL.snapIpari,
      );
      if (placement === null) {
        delete element.props.wallId;
        delete element.props.offset;
      } else {
        element.props.wallId = placement.wallId;
        element.props.offset = placement.offset;
        element.shape = placement.shape;
      }
    }

    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Сваривает стены активного поля: близкие вершины и T-стыки. */
  function weldWalls(): void {
    const boardId = draft.value.activeBoardId;
    if (boardId === null) {
      return;
    }

    const affected = sceneWallService.weld(draft.value.elements, boardId, 1);
    if (affected.length === 0) {
      return;
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
    const copies: NonNullable<ReturnType<typeof service.duplicateElement>>[] = [];
    const idMap = new Map<number, number>();
    for (const elementId of selection.value) {
      const copy = service.duplicateElement(draft.value, elementId, offset);
      if (copy !== null) {
        idMap.set(elementId, copy.id);
        copies.push(copy);
      }
    }

    if (copies.length === 0) {
      return;
    }

    // Проём копии должен ехать за копией стены, а не за оригиналом.
    for (const copy of copies) {
      if (!sceneWallService.isOpening(copy) || copy.props.wallId === undefined) {
        continue;
      }

      const mapped = idMap.get(copy.props.wallId);
      if (mapped === undefined) {
        delete copy.props.wallId;
        delete copy.props.offset;
      } else {
        copy.props.wallId = mapped;
      }
    }

    for (const copy of copies) {
      if (sceneWallService.isWall(copy)) {
        sceneWallService.sync(draft.value.elements, copy.id);
      }
    }

    draft.value.elements = [...draft.value.elements];
    selection.value = copies.map((element) => element.id);
    markDirty();
  }

  /** Задаёт выделение. */
  function select(elementIds: number[]): void {
    selection.value = elementIds;
  }

  /** Вставляет дверь или окно в середину выбранной стены. */
  function insertOpening(wallId: number, kind: 'door' | 'window'): number | null {
    const wall = draft.value.elements.find((item) => item.id === wallId);
    if (wall === undefined || !sceneWallService.isWall(wall)) {
      return null;
    }

    const resolution = draft.value.resolution;
    const width = Math.max(
      1,
      Math.round(resolution * (kind === 'window' ? SCENE_WALL.windowWidthIpari : SCENE_WALL.doorWidthIpari)),
    );
    const length = sceneWallService.length(wall);
    const offset = Math.round(Math.max(0, Math.min(Math.max(0, length - width), (length - width) / 2)));
    const shape = sceneWallService.spanShape(wall, offset, offset + width);
    if (shape === null) {
      return null;
    }

    const props: SceneElementProps =
      kind === 'window'
        ? {
            kind: 'window',
            state: 'closed',
            blocksVision: false,
            sill: Math.round(resolution * SCENE_WALL.windowSillIpari),
            lintel: Math.round(resolution * SCENE_WALL.windowLintelIpari),
          }
        : { kind: 'door', state: 'closed', blocksVision: true };
    const element = service.addElement(draft.value, wall.boardId, 'opening', shape, {
      ...props,
      wallId,
      offset,
    });
    draft.value.elements = [...draft.value.elements];
    selection.value = [element.id];
    markDirty();

    return element.id;
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
    addFloor,
    renameBoard,
    removeBoard,
    moveBoard,
    updateBoard,
    setBackdrop,
    fillTerrain,
    stretchTerrain,
    setSortOrder,
    setRectSize,
    resizeRect,
    setRotation,
    createElement,
    createFromDrag,
    createPath,
    updateElementProps,
    setElementTarget,
    setElementAsset,
    duplicateSelected,
    insertOpening,
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
    weldWalls,
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
