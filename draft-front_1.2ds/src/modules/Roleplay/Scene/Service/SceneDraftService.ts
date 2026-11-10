import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSaveElement } from '@/modules/Roleplay/Scene/Dto/SceneSaveElement';
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
  ): SceneSaveElement {
    const element: SceneSaveElement = {
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
