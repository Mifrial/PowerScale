import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneFootprint } from '@/modules/Roleplay/Scene/Dto/SceneFootprint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneDraftElement } from '@/modules/Roleplay/Scene/Dto/SceneDraftElement';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { SceneDecorZone } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import { SCENE_BOARD_DEFAULTS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BOARD_DEFAULTS';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneVertexService } from '@/modules/Roleplay/Scene/Service/Instance/sceneVertexService';
import { scaleSceneFootprint } from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';

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
      assetContentBox: null,
    };
    draft.elements.push(element);

    return element;
  }

  /** Дублирует элемент; точка сдвигается, чтобы копия не легла ровно под оригинал. */
  duplicateElement(draft: SceneDraft, elementId: number, offset: number): SceneDraftElement | null {
    const source = draft.elements.find((element) => element.id === elementId);
    if (source === undefined) {
      return null;
    }

    const shape = JSON.parse(JSON.stringify(source.shape)) as SceneShape;
    if (shape.type === 'point') {
      shape.x += offset;
      shape.y += offset;
    }

    const element: SceneDraftElement = {
      id: this.allocateId(),
      boardId: source.boardId,
      kind: source.kind,
      shape,
      props: JSON.parse(JSON.stringify(source.props)) as SceneElementProps,
      sortOrder: this.nextSortOrder(draft, source.boardId),
      targetBoardId: source.targetBoardId,
      assetPublicId: source.assetPublicId,
      assetContentBox: source.assetContentBox,
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

  /** Двигает вершину полигона или ленты. */
  setVertex(draft: SceneDraft, elementId: number, index: number, point: ScenePoint): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    if (index < 0 || index >= element.shape.points.length) {
      return;
    }

    const points = element.shape.points.map((item, position) =>
      position === index ? ([point[0], point[1]] as ScenePoint) : item,
    );
    element.shape = { ...element.shape, points };
  }

  /** Двигает ручку Безье вершины. */
  setHandle(draft: SceneDraft, elementId: number, index: number, side: SceneHandleSide, offset: ScenePoint): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    if (index < 0 || index >= element.shape.points.length) {
      return;
    }

    const handles = sceneCurveService.normalizeHandles(element.shape.points, element.shape.handles);
    handles[index] = {
      ...handles[index],
      [side]: [Math.round(offset[0]), Math.round(offset[1])],
    };
    element.shape = { ...element.shape, handles };
  }

  /** Вставляет вершину после указанной; для ленты интерполирует ширину. */
  insertVertex(
    draft: SceneDraft,
    elementId: number,
    afterIndex: number,
    point: ScenePoint,
    handle: { in: ScenePoint; out: ScenePoint },
    width = 1,
  ): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    const position = Math.min(Math.max(0, afterIndex + 1), element.shape.points.length);
    const points = element.shape.points.slice();
    points.splice(position, 0, [Math.round(point[0]), Math.round(point[1])]);
    const handles = sceneCurveService.normalizeHandles(element.shape.points, element.shape.handles);
    handles.splice(position, 0, {
      in: [Math.round(handle.in[0]), Math.round(handle.in[1])],
      out: [Math.round(handle.out[0]), Math.round(handle.out[1])],
    });

    if (element.shape.type === 'path') {
      const widths = element.shape.widths.slice();
      widths.splice(position, 0, Math.max(1, Math.round(width)));
      element.shape = { ...element.shape, points, handles, widths };
    } else {
      element.shape = { ...element.shape, points, handles };
    }
  }

  /** Удаляет вершину; минимальные размеры фигур сохраняются. */
  removeVertex(draft: SceneDraft, elementId: number, index: number): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    const minimum = element.shape.type === 'polygon' ? 3 : 2;
    if (element.shape.points.length <= minimum || index < 0 || index >= element.shape.points.length) {
      return;
    }

    const points = element.shape.points.filter((_, position) => position !== index);
    const handles = sceneCurveService
      .normalizeHandles(element.shape.points, element.shape.handles)
      .filter((_, position) => position !== index);

    if (element.shape.type === 'path') {
      const widths = element.shape.widths.filter((_, position) => position !== index);
      element.shape = { ...element.shape, points, handles, widths };
    } else {
      element.shape = { ...element.shape, points, handles };
    }
  }

  /** Ширина в вершине ленты; остальные ширины масштабируются пропорционально. */
  /** Ширина ленты в одной вершине; остальные вершины не меняются. */
  setWidth(draft: SceneDraft, elementId: number, index: number, width: number): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined || element.shape.type !== 'path' || index < 0 || index >= element.shape.widths.length) {
      return;
    }

    const target = Math.max(1, Math.round(width));
    if (target === element.shape.widths[index]) {
      return;
    }

    const widths = element.shape.widths.slice();
    widths[index] = target;
    element.shape = { ...element.shape, widths };
  }

  /** Плавные ручки Безье по соседним вершинам. */
  smoothShape(draft: SceneDraft, elementId: number): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    element.shape = { ...element.shape, handles: sceneCurveService.autoHandles(element.shape.points) };
  }

  /** Прямые сегменты: все ручки обнуляются. */
  cornerShape(draft: SceneDraft, elementId: number): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    element.shape = {
      ...element.shape,
      handles: element.shape.points.map(() => sceneCurveService.zeroHandle()),
    };
  }

  /** Зона декора: основание или силуэт. */
  private zoneOf(element: SceneDraftElement, zone: SceneDecorZone): SceneFootprint | null {
    return zone === 'footprint' ? (element.props.footprint ?? null) : (element.props.silhouette ?? null);
  }

  /** Полигон зоны; null — зона не полигон. */
  private zonePolygonOf(element: SceneDraftElement, zone: SceneDecorZone): (SceneShape & { type: 'polygon' }) | null {
    this.materializeZone(element, zone);
    const value = this.zoneOf(element, zone);
    if (value === null || value.shape !== 'polygon') {
      return null;
    }

    return { type: 'polygon', points: value.points, handles: value.handles };
  }

  /**
   * Явная зона перед правкой.
   *
   * Пока силуэт не задан, он совпадает с основанием. Первое перетаскивание
   * вершины снимает копию с основания: правка одной зоны не должна молча
   * менять другую.
   */
  private materializeZone(element: SceneDraftElement, zone: SceneDecorZone): void {
    if (zone !== 'silhouette' || element.props.silhouette !== undefined) {
      return;
    }

    const footprint = element.props.footprint;
    if (footprint === undefined || footprint === null) {
      return;
    }

    element.props = { ...element.props, silhouette: { ...footprint, bottom: 0 } };
  }

  /** Записывает полигон обратно в зону, сохраняя смещение формы и низ силуэта. */
  private setZonePolygon(
    element: SceneDraftElement,
    zone: SceneDecorZone,
    shape: SceneShape & { type: 'polygon' },
  ): void {
    const current = this.zoneOf(element, zone);
    const offset = current?.offset;
    const polygon = {
      shape: 'polygon' as const,
      points: shape.points,
      ...(shape.handles === undefined ? {} : { handles: shape.handles }),
      ...(offset === undefined ? {} : { offset }),
    };

    element.props =
      zone === 'footprint'
        ? { ...element.props, footprint: polygon }
        : { ...element.props, silhouette: { ...polygon, bottom: this.silhouetteBottom(element) } };
  }

  /** Низ силуэта: у полигона сохраняется, у новой зоны — ноль. */
  private silhouetteBottom(element: SceneDraftElement): number {
    const silhouette = element.props.silhouette;

    return silhouette === undefined || silhouette === null ? 0 : Math.max(0, silhouette.bottom);
  }

  /** Двигает вершину зоны; точка уже локальная. */
  setZoneVertex(draft: SceneDraft, elementId: number, zone: SceneDecorZone, index: number, point: ScenePoint): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    const shape = this.zonePolygonOf(element, zone);
    if (shape === null || index < 0 || index >= shape.points.length) {
      return;
    }

    const points = shape.points.map((item, position) =>
      position === index ? ([Math.round(point[0]), Math.round(point[1])] as ScenePoint) : item,
    );
    this.setZonePolygon(element, zone, { ...shape, points });
  }

  /** Двигает ручку Безье зоны; смещение уже локальное. */
  setZoneHandle(
    draft: SceneDraft,
    elementId: number,
    zone: SceneDecorZone,
    index: number,
    side: SceneHandleSide,
    offset: ScenePoint,
  ): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    const shape = this.zonePolygonOf(element, zone);
    if (shape === null || index < 0 || index >= shape.points.length) {
      return;
    }

    const handles = sceneCurveService.normalizeHandles(shape.points, shape.handles);
    handles[index] = { ...handles[index], [side]: [Math.round(offset[0]), Math.round(offset[1])] };
    this.setZonePolygon(element, zone, { ...shape, handles });
  }

  /** Вставляет вершину в зону, сохраняя форму кривой. */
  insertZoneVertex(
    draft: SceneDraft,
    elementId: number,
    zone: SceneDecorZone,
    point: ScenePoint,
    tolerance: number,
  ): boolean {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return false;
    }

    const shape = this.zonePolygonOf(element, zone);
    if (shape === null) {
      return false;
    }

    const insertion = sceneVertexService.insertion(shape, point, tolerance);
    if (insertion === null) {
      return false;
    }

    const split = sceneVertexService.split(shape, insertion);
    if (split === null) {
      return false;
    }

    const handles = sceneCurveService.normalizeHandles(shape.points, shape.handles);
    handles[split.index] = { ...handles[split.index], out: split.startHandle };
    handles[(split.index + 1) % shape.points.length] = {
      ...handles[(split.index + 1) % shape.points.length],
      in: split.endHandle,
    };
    const points = shape.points.slice();
    points.splice(split.index + 1, 0, [Math.round(split.point[0]), Math.round(split.point[1])]);
    handles.splice(split.index + 1, 0, split.handle);
    this.setZonePolygon(element, zone, { ...shape, points, handles });

    return true;
  }

  /** Удаляет вершину зоны; минимум три точки. */
  removeZoneVertex(draft: SceneDraft, elementId: number, zone: SceneDecorZone, index: number): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    const shape = this.zonePolygonOf(element, zone);
    if (shape === null || shape.points.length <= 3 || index < 0 || index >= shape.points.length) {
      return;
    }

    const points = shape.points.filter((_, position) => position !== index);
    const handles = sceneCurveService
      .normalizeHandles(shape.points, shape.handles)
      .filter((_, position) => position !== index);
    this.setZonePolygon(element, zone, { ...shape, points, handles });
  }

  /** Плавные ручки зоны по соседним вершинам. */
  smoothZone(draft: SceneDraft, elementId: number, zone: SceneDecorZone): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    const shape = this.zonePolygonOf(element, zone);
    if (shape === null) {
      return;
    }

    this.setZonePolygon(element, zone, { ...shape, handles: sceneCurveService.autoHandles(shape.points) });
  }

  /** Прямые сегменты зоны. */
  cornerZone(draft: SceneDraft, elementId: number, zone: SceneDecorZone): void {
    const element = draft.elements.find((item) => item.id === elementId);
    if (element === undefined) {
      return;
    }

    const shape = this.zonePolygonOf(element, zone);
    if (shape === null) {
      return;
    }

    this.setZonePolygon(element, zone, {
      ...shape,
      handles: shape.points.map(() => sceneCurveService.zeroHandle()),
    });
  }

  /** Лента с постоянной шириной и плавными ручками. */
  pathShape(points: ScenePoint[], width: number): SceneShape {
    const normalized = points.map((point): ScenePoint => [Math.round(point[0]), Math.round(point[1])]);

    return {
      type: 'path',
      points: normalized,
      widths: normalized.map(() => Math.max(1, Math.round(width))),
      handles: sceneCurveService.autoHandles(normalized),
    };
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

      if (element.props.footprint !== undefined) {
        element.props.footprint = this.scaleFootprint(element.props.footprint, factor);
      }

      const silhouette = element.props.silhouette;
      if (silhouette !== undefined) {
        element.props.silhouette = {
          ...this.scaleFootprint(silhouette, factor),
          bottom: this.scaleLength(silhouette.bottom, factor),
        };
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

    if (shape.type === 'path') {
      const widths = shape.widths.map((value) => this.scaleSize(value, factor, 100000));
      const handles = this.scaleHandles(shape.handles, factor);

      return handles === undefined ? { ...shape, points, widths } : { ...shape, points, widths, handles };
    }

    const handles = this.scaleHandles(shape.handles, factor);

    return handles === undefined ? { ...shape, points } : { ...shape, points, handles };
  }

  /** Масштабирует смещения ручек Безье. */
  private scaleHandles(
    handles: { in: ScenePoint; out: ScenePoint }[] | undefined,
    factor: number,
  ): { in: ScenePoint; out: ScenePoint }[] | undefined {
    if (handles === undefined) {
      return undefined;
    }

    return handles.map((handle) => ({
      in: [Math.round(handle.in[0] * factor), Math.round(handle.in[1] * factor)],
      out: [Math.round(handle.out[0] * factor), Math.round(handle.out[1] * factor)],
    }));
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

  /**
   * Масштабирует основание при смене разрешения.
   *
   * Основание хранится в единицах сцены, поэтому оно живёт по тем же правилам,
   * что фигура и высота. Точки локальные и могут быть отрицательными.
   */
  private scaleFootprint(footprint: SceneFootprint, factor: number): SceneFootprint {
    return scaleSceneFootprint(footprint, factor);
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
