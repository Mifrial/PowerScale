import { computed, ref } from 'vue';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSaveSnapshot } from '@/modules/Roleplay/Scene/Dto/SceneSaveSnapshot';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneToolDefinition } from '@/modules/Roleplay/Scene/Dto/SceneToolDefinition';
import { SceneDraftMapper } from '@/modules/Roleplay/Scene/Service/SceneDraftMapper';
import { SceneDraftService } from '@/modules/Roleplay/Scene/Service/SceneDraftService';
import { SceneToolShape } from '@/modules/Roleplay/Scene/Service/SceneToolShape';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

/**
 * Черновик редактора локации: состояние и операции над ним.
 *
 * Все мутации идут через сервис и помечают черновик изменённым; компонент
 * только вызывает методы.
 */
export function useSceneDraft(initial: SceneDetail) {
  const mapper = new SceneDraftMapper();
  const service = new SceneDraftService();
  const shapeBuilder = new SceneToolShape();
  const draft = ref<SceneDraft>(mapper.fromDetail(initial));
  const isDirty = ref(false);
  const selection = ref<number[]>([]);

  const activeBoard = computed<SceneDraftBoard | null>(
    () => draft.value.boards.find((board) => board.id === draft.value.activeBoardId) ?? null,
  );
  const selectedElements = computed(() =>
    draft.value.elements.filter((element) => selection.value.includes(element.id)),
  );

  /** Заменяет черновик сохранённым снимком. */
  function reset(detail: SceneDetail): void {
    draft.value = mapper.fromDetail(detail);
    selection.value = [];
    isDirty.value = false;
  }

  /** Помечает черновик изменённым. */
  function markDirty(): void {
    isDirty.value = true;
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
  function createElement(tool: SceneToolDefinition, shape: SceneShape): number | null {
    const boardId = draft.value.activeBoardId;
    if (boardId === null || tool.kind === null) {
      return null;
    }

    const element = service.addElement(draft.value, boardId, tool.kind, shape, tool.props);
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

  /** Задаёт ассет декора. */
  function setElementAsset(elementId: number, publicId: string | null): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    element.assetPublicId = publicId;
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Меняет геометрию элемента. */
  function updateElementShape(elementId: number, shape: SceneShape): void {
    service.updateElementShape(draft.value, elementId, shape);
    draft.value.elements = [...draft.value.elements];
    markDirty();
  }

  /** Двигает вершину полигона в пределах поля. */
  function moveVertex(elementId: number, index: number, point: ScenePoint): void {
    const element = draft.value.elements.find((item) => item.id === elementId);
    if (element === undefined || element.shape.type !== 'polygon') {
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
    const points = element.shape.points.map((item, position) => (position === index ? clamped : item));
    element.shape = { ...element.shape, points };
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
    reset,
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
    updateElementProps,
    setElementTarget,
    setElementAsset,
    updateElementShape,
    moveVertex,
    moveElements,
    removeSelected,
    select,
    snapshot,
  };
}
