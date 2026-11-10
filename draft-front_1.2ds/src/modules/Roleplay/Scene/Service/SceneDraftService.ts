import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneDraftElement } from '@/modules/Roleplay/Scene/Dto/SceneDraftElement';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import { SCENE_BOARD_DEFAULTS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BOARD_DEFAULTS';

/**
 * Мутации черновика локации: поля, элементы, временные id.
 *
 * Класс меняет переданный черновик; реактивность обеспечивает вызывающий.
 * Временные id отрицательные, ноль запрещён контрактом `scene.save`.
 */
export class SceneDraftService {
  private nextTempId = -1;

  /** Новый временный id. */
  allocateId(): number {
    return this.nextTempId--;
  }

  /** Границы поля или null. */
  boardBounds(draft: SceneDraft, boardId: number | null): SceneBounds | null {
    const board = draft.boards.find((item) => item.id === boardId);
    if (board === undefined) {
      return null;
    }

    return { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
  }

  /** Свободное имя поля: «Поле», «Поле 2», … */
  freeBoardName(draft: SceneDraft): string {
    const names = new Set(draft.boards.map((board) => board.name));
    if (!names.has(SCENE_BOARD_DEFAULTS.name)) {
      return SCENE_BOARD_DEFAULTS.name;
    }

    let index = 2;
    while (names.has(`${SCENE_BOARD_DEFAULTS.name} ${index}`)) {
      index += 1;
    }

    return `${SCENE_BOARD_DEFAULTS.name} ${index}`;
  }

  /** Добавляет поле в конец. */
  addBoard(draft: SceneDraft, name: string): SceneDraftBoard {
    const board: SceneDraftBoard = {
      id: this.allocateId(),
      name,
      sortOrder: draft.boards.length,
      filePublicId: null,
      boundWidth: SCENE_BOARD_DEFAULTS.boundWidth,
      boundHeight: SCENE_BOARD_DEFAULTS.boundHeight,
      occupancyCell: SCENE_BOARD_DEFAULTS.occupancyCell,
      zQuantum: SCENE_BOARD_DEFAULTS.zQuantum,
      gridStep: SCENE_BOARD_DEFAULTS.gridStep,
      fileWidth: null,
      fileHeight: null,
    };
    draft.boards.push(board);

    return board;
  }

  /** Удаляет поле, его элементы и вложения. Последнее поле не удаляется. */
  removeBoard(draft: SceneDraft, boardId: number): boolean {
    if (draft.boards.length <= 1) {
      return false;
    }

    draft.boards = draft.boards.filter((board) => board.id !== boardId);
    draft.elements = draft.elements.filter((element) => element.boardId !== boardId);
    draft.enclosures = draft.enclosures.filter(
      (enclosure) => enclosure.parentBoardId !== boardId && enclosure.childBoardId !== boardId,
    );
    this.normalizeBoardOrder(draft);
    if (draft.activeBoardId === boardId) {
      draft.activeBoardId = draft.boards[0]?.id ?? null;
    }

    return true;
  }

  /** Сдвигает поле в списке на одну позицию. */
  moveBoard(draft: SceneDraft, boardId: number, offset: number): boolean {
    const index = draft.boards.findIndex((board) => board.id === boardId);
    const target = index + offset;
    if (index < 0 || target < 0 || target >= draft.boards.length) {
      return false;
    }

    const [board] = draft.boards.splice(index, 1);
    draft.boards.splice(target, 0, board);
    this.normalizeBoardOrder(draft);

    return true;
  }

  /** Имя поля занято другим полем. */
  isBoardNameTaken(draft: SceneDraft, name: string, exceptId: number | null = null): boolean {
    return draft.boards.some((board) => board.id !== exceptId && board.name === name);
  }

  /** Добавляет элемент в конец поля. */
  addElement(
    draft: SceneDraft,
    boardId: number,
    kind: SceneElementKind,
    shape: SceneShape,
    props: SceneElementProps,
  ): SceneDraftElement {
    const element: SceneDraftElement = {
      id: this.allocateId(),
      boardId,
      kind,
      shape,
      props: { ...props },
      sortOrder: this.nextSortOrder(draft, boardId),
      targetBoardId: null,
      assetPublicId: null,
    };
    draft.elements.push(element);

    return element;
  }

  /** Меняет геометрию элемента. */
  updateElementShape(draft: SceneDraft, elementId: number, shape: SceneShape): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element !== undefined) {
      element.shape = shape;
    }
  }

  /** Прямоугольник ровно по границам поля. */
  fullBoardShape(width: number, height: number): SceneShape {
    return { type: 'rect', x: 0, y: 0, w: width, h: height };
  }

  /** Совпадает ли фигура с прямоугольником поля. */
  isFullBoardShape(shape: SceneShape, width: number, height: number): boolean {
    return shape.type === 'rect' && shape.x === 0 && shape.y === 0 && shape.w === width && shape.h === height;
  }

  /** Заливает поле террейном: обновляет существующий полный прямоугольник или создаёт новый. */
  fillBoardTerrain(draft: SceneDraft, boardId: number, kind: string): SceneDraftElement | null {
    const board = draft.boards.find((item) => item.id === boardId);
    if (board === undefined) {
      return null;
    }

    const existing = draft.elements.find(
      (element) =>
        element.boardId === boardId &&
        element.kind === 'terrain' &&
        this.isFullBoardShape(element.shape, board.boundWidth, board.boundHeight),
    );
    if (existing !== undefined) {
      existing.props = { ...existing.props, kind };

      return existing;
    }

    return this.addElement(draft, boardId, 'terrain', this.fullBoardShape(board.boundWidth, board.boundHeight), {
      kind,
      label: '',
    });
  }

  /** Растягивает террейн, который раньше занимал всё поле, под новые границы. */
  stretchBoardTerrain(draft: SceneDraft, boardId: number, oldWidth: number, oldHeight: number): void {
    const board = draft.boards.find((item) => item.id === boardId);
    if (board === undefined) {
      return;
    }

    for (const element of draft.elements) {
      if (element.boardId !== boardId || element.kind !== 'terrain') {
        continue;
      }

      if (this.isFullBoardShape(element.shape, oldWidth, oldHeight)) {
        element.shape = this.fullBoardShape(board.boundWidth, board.boundHeight);
      }
    }
  }

  /** Сдвигает элемент в пределах его поля. */
  moveElement(draft: SceneDraft, elementId: number, deltaX: number, deltaY: number): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    const bounds = this.boardBounds(draft, element.boardId);
    if (bounds === null) {
      return;
    }

    element.shape = this.translateShape(element.shape, deltaX, deltaY, bounds);
  }

  /** Удаляет элемент по id. */
  removeElement(draft: SceneDraft, elementId: number): void {
    draft.elements = draft.elements.filter((element) => element.id !== elementId);
  }

  /**
   * Пересчитывает координаты и размеры под новое разрешение.
   *
   * Карта сохраняет физический размер в ипари; меняется только точность.
   * Округление может сдвинуть элементы на единицу.
   */
  rescale(draft: SceneDraft, nextResolution: number): void {
    const current = draft.resolution;
    if (current <= 0 || nextResolution <= 0 || current === nextResolution) {
      return;
    }

    const factor = nextResolution / current;
    for (const board of draft.boards) {
      board.boundWidth = this.scaleSize(board.boundWidth, factor);
      board.boundHeight = this.scaleSize(board.boundHeight, factor);
      board.occupancyCell = this.scaleSize(board.occupancyCell, factor);
      board.zQuantum = this.scaleSize(board.zQuantum, factor);
      board.gridStep = board.gridStep === 0 ? 0 : this.scaleSize(board.gridStep, factor);
    }

    for (const element of draft.elements) {
      const bounds = this.boardBounds(draft, element.boardId);
      element.shape = this.scaleShape(element.shape, factor, bounds);
      if (typeof element.props.height === 'number') {
        element.props.height = this.scaleLength(element.props.height, factor);
      }

      if (typeof element.props.elevation === 'number') {
        element.props.elevation = this.scaleLength(element.props.elevation, factor);
      }
    }

    for (const enclosure of draft.enclosures) {
      const bounds = this.boardBounds(draft, enclosure.parentBoardId);
      enclosure.footprint = this.scaleShape(enclosure.footprint, factor, bounds);
    }

    draft.resolution = nextResolution;
  }

  /** Масштабирует фигуру с прижатием к границам поля. */
  private scaleShape(shape: SceneShape, factor: number, bounds: SceneBounds | null): SceneShape {
    if (shape.type === 'rect') {
      const width =
        bounds === null ? this.scaleSize(shape.w, factor) : Math.min(this.scaleSize(shape.w, factor), bounds.width);
      const height =
        bounds === null ? this.scaleSize(shape.h, factor) : Math.min(this.scaleSize(shape.h, factor), bounds.height);

      return {
        ...shape,
        x: this.scaleCoord(shape.x, factor, bounds?.width ?? null, width),
        y: this.scaleCoord(shape.y, factor, bounds?.height ?? null, height),
        w: width,
        h: height,
      };
    }

    if (shape.type === 'circle') {
      const limit =
        bounds === null ? this.scaleSize(shape.r, factor) : Math.floor(Math.min(bounds.width, bounds.height) / 2);
      const radius = Math.max(1, Math.min(this.scaleSize(shape.r, factor), limit));

      return {
        ...shape,
        cx: this.scaleCoord(shape.cx, factor, bounds?.width ?? null, radius),
        cy: this.scaleCoord(shape.cy, factor, bounds?.height ?? null, radius),
        r: radius,
      };
    }

    if (shape.type === 'point') {
      return {
        ...shape,
        x: this.scaleCoord(shape.x, factor, bounds?.width ?? null, 0),
        y: this.scaleCoord(shape.y, factor, bounds?.height ?? null, 0),
      };
    }

    const points = shape.points.map((point): ScenePoint => [
      this.scaleCoord(point[0], factor, bounds?.width ?? null, 0),
      this.scaleCoord(point[1], factor, bounds?.height ?? null, 0),
    ]);

    if (shape.type === 'wall') {
      return { ...shape, points, width: this.scaleSize(shape.width, factor, 100000) };
    }

    return { ...shape, points };
  }

  /** Масштабирует координату и сжимает в границы с учётом размера фигуры. */
  private scaleCoord(value: number, factor: number, limit: number | null, size: number): number {
    const scaled = Math.max(0, Math.round(value * factor));
    if (limit === null) {
      return scaled;
    }

    return Math.min(Math.max(0, limit - size), scaled);
  }

  /** Масштабирует размер, не меньше единицы. */
  private scaleSize(value: number, factor: number, max = Number.MAX_SAFE_INTEGER): number {
    return Math.max(1, Math.min(max, Math.round(value * factor)));
  }

  /** Масштабирует вертикальную длину; ноль допустим. */
  private scaleLength(value: number, factor: number): number {
    return Math.min(100000, Math.max(0, Math.round(value * factor)));
  }

  /** Следующий порядок элемента на поле. */
  nextSortOrder(draft: SceneDraft, boardId: number): number {
    let max = -1;
    for (const element of draft.elements) {
      if (element.boardId === boardId && element.sortOrder > max) {
        max = element.sortOrder;
      }
    }

    return max + 1;
  }

  /** Пересчитывает порядок полей по массиву. */
  private normalizeBoardOrder(draft: SceneDraft): void {
    draft.boards.forEach((board, index) => {
      board.sortOrder = index;
    });
  }

  /** Сдвиг фигуры с прижатием к границам поля. */
  private translateShape(shape: SceneShape, deltaX: number, deltaY: number, bounds: SceneBounds): SceneShape {
    if (shape.type === 'rect') {
      return {
        ...shape,
        x: this.clamp(shape.x + deltaX, 0, Math.max(0, bounds.width - shape.w)),
        y: this.clamp(shape.y + deltaY, 0, Math.max(0, bounds.height - shape.h)),
      };
    }

    if (shape.type === 'circle') {
      return {
        ...shape,
        cx: this.clamp(shape.cx + deltaX, shape.r, Math.max(shape.r, bounds.width - shape.r)),
        cy: this.clamp(shape.cy + deltaY, shape.r, Math.max(shape.r, bounds.height - shape.r)),
      };
    }

    if (shape.type === 'point') {
      return {
        ...shape,
        x: this.clamp(shape.x + deltaX, 0, bounds.width),
        y: this.clamp(shape.y + deltaY, 0, bounds.height),
      };
    }

    const xs = shape.points.map((point) => point[0]);
    const ys = shape.points.map((point) => point[1]);
    const shiftX = this.clamp(deltaX, -Math.min(...xs), bounds.width - Math.max(...xs));
    const shiftY = this.clamp(deltaY, -Math.min(...ys), bounds.height - Math.max(...ys));

    return {
      ...shape,
      points: shape.points.map((point): ScenePoint => [point[0] + shiftX, point[1] + shiftY]),
    };
  }

  /** Сжимает значение в диапазон с округлением. */
  private clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, Math.round(value)));
  }
}
