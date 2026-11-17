import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';

/** Преобразование координат поля в мир корня. */
export interface SceneFloorTransform {
  /** Смещение начала поля в мире корня. */
  x: number;
  y: number;
  /** Масштаб поля по осям: пятно вложения сжимает или растягивает план. */
  scaleX: number;
  scaleY: number;
}

/** Видимый этаж: поле и его место в мире корня. */
export interface SceneFloor {
  boardId: number;
  board: SceneBoard;
  transform: SceneFloorTransform;
  /** Абсолютная отметка: сумма `elevation` по цепочке вложений. */
  elevation: number;
  /** Корень локации: общая земля и нижняя опора стопки. */
  root: boolean;
  /** Этаж выбранной стопки: на нём фокус. */
  active: boolean;
  /** Ближайший этаж ниже выбранного: нужен для разреза. */
  below: boolean;
  /**
   * Этаж под плитой фокуса в той же стопке.
   *
   * Стены и потолок такого этажа срезаны плоскостью плиты: сквозь
   * полупрозрачный пол виден план, а не торчащие сквозь него стены.
   */
  underCut: boolean;
}

/** Что и в каком порядке рисует композит этажей. */
export interface SceneFloorPlan {
  rootBoardId: number | null;
  /** Этажи снизу вверх, в порядке рисования. */
  floors: SceneFloor[];
  /** Фокус: поле, на котором стоит автор. */
  activeBoardId: number;
  /** Этаж с полупрозрачной плитой; null — плита непрозрачна. */
  slabBoardId: number | null;
  /** Поля, акторы которых видны. */
  visibleBoardIds: number[];
}

const IDENTITY: SceneFloorTransform = { x: 0, y: 0, scaleX: 1, scaleY: 1 };

/**
 * Стопки этажей и мир корня.
 *
 * Этаж — не отдельный мир: каждое поле живёт в координатах корня, а цепочка
 * вложений даёт преобразование. Сервис ничего не рисует — он отвечает, какие
 * поля видны при текущем фокусе и как их точки перевести в мир корня.
 */
export class SceneFloorPlanService {
  constructor(private readonly geometry: SceneGeometryService = new SceneGeometryService()) {}

  /**
   * Пятно плиты этажа: габарит его террейна, кроме воды.
   *
   * Поле шире плана на поля обзора (`SCENE_FLOOR.contextMarginIpari`): пустота
   * за ними — не пол. Резать нижний этаж и искать стену под плитой нужно по
   * нарисованному полу, иначе вместе с планом исчезает всё, что стоит во дворе
   * вокруг дома, — забор и ворота в нём.
   *
   * @param model Модель рендера.
   * @param boardId Поле этажа.
   *
   * @returns Прямоугольник плиты в координатах поля или null, если пола нет.
   */
  slabBounds(model: SceneRenderModel, boardId: number): SceneBounds | null {
    let minX = Number.POSITIVE_INFINITY;
    let minY = Number.POSITIVE_INFINITY;
    let maxX = Number.NEGATIVE_INFINITY;
    let maxY = Number.NEGATIVE_INFINITY;
    for (const element of model.elements) {
      if (
        element.boardId !== boardId ||
        element.kind !== 'terrain' ||
        element.props.kind === 'water' ||
        element.shape.type === 'point'
      ) {
        continue;
      }

      const box = this.geometry.bounds(element.shape);
      if (box.width <= 0 || box.height <= 0) {
        continue;
      }

      minX = Math.min(minX, box.x);
      minY = Math.min(minY, box.y);
      maxX = Math.max(maxX, box.x + box.width);
      maxY = Math.max(maxY, box.y + box.height);
    }

    if (!Number.isFinite(minX) || !Number.isFinite(minY)) {
      return null;
    }

    return { x: minX, y: minY, width: maxX - minX, height: maxY - minY };
  }

  /**
   * Проёмы-лестницы в плите поля: места, где пол прорезан.
   *
   * Колодец — прямоугольный проём `stairs` без цели: дыра в полу, сквозь неё
   * видны ступени этажа ниже, и клик по ним принадлежит тому этажу, а не
   * этому. Площадка с целью — тоже вырез (она верхняя ступень марша), но это
   * дверь: клик по ней остаётся переходом, а не кликом по настилу под ней.
   * Поэтому `doors` решает, попадают ли площадки в ответ.
   *
   * @param model Модель рендера.
   * @param boardId Поле этажа.
   * @param doors true — вместе с площадками перехода; false — только колодцы.
   *
   * @returns Прямоугольники проёмов в координатах поля.
   */
  slabHoles(model: SceneRenderModel, boardId: number, doors = true): SceneBounds[] {
    const holes: SceneBounds[] = [];
    for (const element of model.elements) {
      if (element.boardId !== boardId || element.kind !== 'opening' || element.shape.type !== 'rect') {
        continue;
      }

      if (element.props.kind !== 'stairs') {
        continue;
      }

      // Площадка — дверь: её вырез не отдаёт клик этажу ниже.
      if (!doors && element.targetBoardId !== null) {
        continue;
      }

      holes.push({ x: element.shape.x, y: element.shape.y, width: element.shape.w, height: element.shape.h });
    }

    return holes;
  }

  /**
   * Видимый этаж и точка на нём под точкой мира.
   *
   * Этажи перебираются сверху вниз: клик принадлежит верхнему нарисованному
   * полу, накрывающему точку. В колодце срезанной плиты пола нет — сквозь
   * вырез видны ступени этажа ниже, и клик принадлежит им. Иначе клик по
   * ступеням с верхнего этажа уходил бы на переход, а не ставил на ступень.
   *
   * @param model Модель рендера.
   * @param world Точка в мире корня.
   * @param cutaway Плита фокуса прорезана колодцами.
   *
   * @returns Поле и локальная точка или null, если этажа под точкой нет.
   */
  targetAt(
    model: SceneRenderModel,
    world: ScenePoint,
    cutaway: boolean,
  ): { point: ScenePoint; boardId: number } | null {
    const plan = this.plan(model);
    for (let index = plan.floors.length - 1; index >= 0; index -= 1) {
      const floor = plan.floors[index];
      const point = this.toLocal(floor.transform, world);
      if (point[0] < 0 || point[1] < 0 || point[0] > floor.board.boundWidth || point[1] > floor.board.boundHeight) {
        continue;
      }

      if (this.insideSlabHole(model, floor, point, plan.slabBoardId, cutaway)) {
        continue;
      }

      return { point, boardId: floor.boardId };
    }

    return null;
  }

  /**
   * Точка в колодце срезанной плиты: сквозь вырез видно этаж ниже.
   *
   * Площадка перехода — не колодец: её вырез скрывает верхние ступени марша,
   * но клик по ней остаётся переходом на другой этаж.
   *
   * @param model Модель рендера.
   * @param floor Этаж, накрывающий точку.
   * @param point Локальная точка.
   * @param slabBoardId Поле срезанной плиты.
   * @param cutaway Плита фокуса прорезана колодцами.
   *
   * @returns true — под точкой вырез, видно этаж ниже.
   */
  private insideSlabHole(
    model: SceneRenderModel,
    floor: SceneFloor,
    point: ScenePoint,
    slabBoardId: number | null,
    cutaway: boolean,
  ): boolean {
    if (!cutaway || slabBoardId !== floor.boardId) {
      return false;
    }

    return this.slabHoles(model, floor.boardId, false).some(
      (hole) =>
        point[0] >= hole.x &&
        point[1] >= hole.y &&
        point[0] <= hole.x + hole.width &&
        point[1] <= hole.y + hole.height,
    );
  }

  /**
   * Абсолютные отметки полей: сумма `elevation` по цепочке вложений.
   *
   * @param model Модель рендера.
   *
   * @returns Поле → отметка в единицах сцены.
   */
  elevations(model: SceneRenderModel): Map<number, number> {
    const byChild = new Map(model.enclosures.map((enclosure) => [enclosure.childBoardId, enclosure]));
    const result = new Map<number, number>();
    for (const board of model.boards) {
      let elevation = 0;
      let current = board.id;
      const seen = new Set<number>();
      // Циклы запрещены валидатором; повтор поля обрывает цепочку, чтобы
      // рендер не завис на кривых данных.
      while (!seen.has(current)) {
        seen.add(current);
        const enclosure = byChild.get(current);
        if (enclosure === undefined) {
          break;
        }

        elevation += enclosure.elevation;
        current = enclosure.parentBoardId;
      }

      result.set(board.id, elevation);
    }

    return result;
  }

  /**
   * Корень локации — верхнее поле цепочки, в которую входит фокус.
   *
   * @param model Модель рендера.
   *
   * @returns Id корня или null, если полей нет.
   */
  rootBoardId(model: SceneRenderModel): number | null {
    const active = model.boards.find((board) => board.id === model.activeBoardId);
    if (active === undefined) {
      return model.boards[0]?.id ?? null;
    }

    const parents = this.parents(model.enclosures);
    let current = active.id;
    const seen = new Set<number>();
    while (!seen.has(current)) {
      seen.add(current);
      const parent = parents.get(current);
      if (parent === undefined) {
        return current;
      }

      current = parent;
    }

    return current;
  }

  /**
   * Преобразование поля в мир корня.
   *
   * @param model Модель рендера.
   * @param boardId Поле.
   *
   * @returns Преобразование; для неизвестного поля — единичное.
   */
  transform(model: SceneRenderModel, boardId: number): SceneFloorTransform {
    return this.transformOf(model, boardId, new Set());
  }

  /**
   * Преобразование поля с защитой от цикла.
   *
   * Циклы запрещены валидатором, но рендер не должен зависнуть на кривых
   * данных: повтор поля в цепочке обрывает её.
   *
   * @param model Модель рендера.
   * @param boardId Поле.
   * @param seen Уже пройденные поля.
   *
   * @returns Преобразование в мир корня.
   */
  private transformOf(model: SceneRenderModel, boardId: number, seen: Set<number>): SceneFloorTransform {
    if (seen.has(boardId)) {
      return IDENTITY;
    }

    seen.add(boardId);
    const enclosure = model.enclosures.find((item) => item.childBoardId === boardId);
    if (enclosure === undefined) {
      return IDENTITY;
    }

    const parent = model.boards.find((item) => item.id === enclosure.parentBoardId);
    const board = model.boards.find((item) => item.id === boardId);
    if (parent === undefined || board === undefined) {
      return IDENTITY;
    }

    const footprint = this.footprintRect(enclosure);
    if (footprint === null || footprint.width <= 0 || footprint.height <= 0) {
      return IDENTITY;
    }

    const parentTransform = this.transformOf(model, parent.id, seen);
    const scaleX = (footprint.width / Math.max(1, board.boundWidth)) * parentTransform.scaleX;
    const scaleY = (footprint.height / Math.max(1, board.boundHeight)) * parentTransform.scaleY;

    return {
      x: parentTransform.x + footprint.x * parentTransform.scaleX,
      y: parentTransform.y + footprint.y * parentTransform.scaleY,
      scaleX,
      scaleY,
    };
  }

  /**
   * Точка поля в мире корня.
   *
   * @param transform Преобразование поля.
   * @param point Локальная точка.
   *
   * @returns Точка в мире корня.
   */
  toRoot(transform: SceneFloorTransform, point: ScenePoint): ScenePoint {
    return [transform.x + point[0] * transform.scaleX, transform.y + point[1] * transform.scaleY];
  }

  /**
   * Точка мира корня в координатах поля.
   *
   * @param transform Преобразование поля.
   * @param point Точка в мире корня.
   *
   * @returns Локальная точка.
   */
  toLocal(transform: SceneFloorTransform, point: ScenePoint): ScenePoint {
    const scaleX = transform.scaleX === 0 ? 1 : transform.scaleX;
    const scaleY = transform.scaleY === 0 ? 1 : transform.scaleY;

    return [(point[0] - transform.x) / scaleX, (point[1] - transform.y) / scaleY];
  }

  /**
   * Видимые этажи при текущем фокусе.
   *
   * Корень — общая земля и рисуется всегда. У каждого строения берётся этаж
   * с наибольшей отметкой не выше фокуса; стопка целиком выше фокуса не
   * рисуется — её землю уже показывает корень. Активная стопка идёт последней
   * и дополнительно показывает ближайший этаж ниже: сквозь полупрозрачную
   * плиту и колодец видно, что под ней.
   *
   * @param model Модель рендера.
   *
   * @returns План рисования.
   */
  plan(model: SceneRenderModel): SceneFloorPlan {
    const activeBoardId = model.activeBoardId;
    const rootBoardId = this.rootBoardId(model);
    if (rootBoardId === null) {
      return { rootBoardId: null, floors: [], activeBoardId, slabBoardId: null, visibleBoardIds: [] };
    }

    const elevations = this.elevations(model);
    const focus = elevations.get(activeBoardId) ?? 0;
    const parents = this.parents(model.enclosures);
    const stacks = this.stacks(model, rootBoardId, parents, elevations);
    const activeStack = stacks.find((stack) => stack.some((board) => board.id === activeBoardId)) ?? null;
    const root = model.boards.find((board) => board.id === rootBoardId);

    // Предки фокуса — его стопка: только у них стены срезаны плитой. Чужие
    // строения на той же отметке стоят рядом, а не под полом.
    const ancestors = new Set<number>();
    for (let current: number | undefined = activeBoardId; current !== undefined && !ancestors.has(current);) {
      ancestors.add(current);
      current = parents.get(current);
    }

    const floorOf = (board: SceneBoard, active: boolean, below: boolean): SceneFloor => ({
      boardId: board.id,
      board,
      transform: this.transform(model, board.id),
      elevation: elevations.get(board.id) ?? 0,
      root: board.id === rootBoardId,
      active,
      below,
      underCut: board.id !== activeBoardId && ancestors.has(board.id) && (elevations.get(board.id) ?? 0) < focus,
    });

    const base: SceneFloor[] = root === undefined ? [] : [floorOf(root, activeBoardId === root.id, false)];

    const others: SceneFloor[] = [];
    for (const stack of stacks) {
      if (stack === activeStack) {
        continue;
      }

      const pick = this.pickFloor(stack, elevations, focus);
      if (pick !== null) {
        others.push(floorOf(pick, false, false));
      }
    }

    const passive = [...base, ...others].sort(
      (left, right) =>
        left.elevation - right.elevation ||
        Number(right.root) - Number(left.root) ||
        left.board.sortOrder - right.board.sortOrder,
    );
    const activeFloors: SceneFloor[] = [];
    if (activeStack !== null) {
      const active =
        activeStack.find((board) => board.id === activeBoardId) ?? this.pickFloor(activeStack, elevations, focus);
      if (active !== null) {
        const index = activeStack.findIndex((board) => board.id === active.id);
        const below = index > 0 ? activeStack[index - 1] : undefined;
        if (below !== undefined) {
          activeFloors.push(floorOf(below, false, true));
        }

        activeFloors.push(floorOf(active, true, false));
      }
    }

    const result: SceneFloor[] = [];
    const seen = new Set<number>();
    for (const floor of [...passive, ...activeFloors]) {
      if (seen.has(floor.boardId)) {
        continue;
      }

      seen.add(floor.boardId);
      result.push(floor);
    }

    const slab = result.find((floor) => floor.active && !floor.root) ?? null;

    return {
      rootBoardId,
      floors: result,
      activeBoardId,
      slabBoardId: slab === null ? null : slab.boardId,
      visibleBoardIds: result.map((floor) => floor.boardId),
    };
  }

  /**
   * Этаж стопки для фокуса: наибольшая отметка не выше фокуса.
   *
   * Если все этажи стопки выше фокуса, стопка не рисуется: её наземный этаж —
   * это корень, он уже нарисован. Верхний этаж поверх корня закрыл бы землю.
   *
   * @param stack Поля стопки снизу вверх.
   * @param elevations Отметки полей.
   * @param focus Отметка фокуса.
   *
   * @returns Поле или null, если стопка целиком выше фокуса.
   */
  private pickFloor(stack: SceneBoard[], elevations: Map<number, number>, focus: number): SceneBoard | null {
    let pick: SceneBoard | null = null;
    for (const board of stack) {
      if ((elevations.get(board.id) ?? 0) <= focus) {
        pick = board;
      }
    }

    return pick;
  }

  /**
   * Стопки строений: верхние вложения корня и их цепочки снизу вверх.
   *
   * @param model Модель рендера.
   * @param rootBoardId Корень.
   * @param parents Ребёнок → родитель.
   * @param elevations Отметки полей.
   *
   * @returns Стопки полей снизу вверх.
   */
  private stacks(
    model: SceneRenderModel,
    rootBoardId: number,
    parents: Map<number, number>,
    elevations: Map<number, number>,
  ): SceneBoard[][] {
    const children = new Map<number, SceneBoard[]>();
    for (const board of model.boards) {
      const parent = parents.get(board.id);
      if (parent === undefined) {
        continue;
      }

      const list = children.get(parent) ?? [];
      list.push(board);
      children.set(parent, list);
    }

    const result: SceneBoard[][] = [];
    for (const top of children.get(rootBoardId) ?? []) {
      const chain: SceneBoard[] = [];
      let current: SceneBoard | undefined = top;
      const seen = new Set<number>();
      while (current !== undefined && !seen.has(current.id)) {
        seen.add(current.id);
        chain.push(current);
        const next: SceneBoard | undefined = (children.get(current.id) ?? []).sort(
          (left, right) => left.sortOrder - right.sortOrder || left.id - right.id,
        )[0];
        current = next;
      }

      // Цепочка вложений не равна порядку по высоте: подвал может висеть под
      // вторым этажом. Стопка — по возрастанию абсолютной отметки.
      chain.sort(
        (left, right) =>
          (elevations.get(left.id) ?? 0) - (elevations.get(right.id) ?? 0) ||
          left.sortOrder - right.sortOrder ||
          left.id - right.id,
      );
      result.push(chain);
    }

    return result;
  }

  /**
   * Ребёнок → родитель.
   *
   * @param enclosures Вложения.
   *
   * @returns Карта родителей.
   */
  private parents(enclosures: SceneEnclosure[]): Map<number, number> {
    const parents = new Map<number, number>();
    for (const enclosure of enclosures) {
      parents.set(enclosure.childBoardId, enclosure.parentBoardId);
    }

    return parents;
  }

  /**
   * Прямоугольник пятна вложения: у полигона берётся габарит.
   *
   * @param enclosure Вложение.
   *
   * @returns Прямоугольник или null.
   */
  private footprintRect(enclosure: SceneEnclosure): { x: number; y: number; width: number; height: number } | null {
    const shape = enclosure.footprint;
    if (shape.type === 'rect') {
      return { x: shape.x, y: shape.y, width: shape.w, height: shape.h };
    }

    if (shape.type !== 'polygon' && shape.type !== 'wall') {
      return null;
    }

    if (shape.points.length === 0) {
      return null;
    }

    let minX = Number.POSITIVE_INFINITY;
    let minY = Number.POSITIVE_INFINITY;
    let maxX = Number.NEGATIVE_INFINITY;
    let maxY = Number.NEGATIVE_INFINITY;
    for (const point of shape.points) {
      minX = Math.min(minX, point[0]);
      minY = Math.min(minY, point[1]);
      maxX = Math.max(maxX, point[0]);
      maxY = Math.max(maxY, point[1]);
    }

    return { x: minX, y: minY, width: maxX - minX, height: maxY - minY };
  }
}
