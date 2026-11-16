import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneNavMesh, SceneNavRoute, SceneNavSegment } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import { SceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';
import { SceneNavigationService } from '@/modules/Roleplay/Scene/Service/SceneNavigationService';

/** Конечная точка маршрута: поле, локальная точка и уровень поверхности. */
export interface SceneRoutePoint {
  boardId: number;
  point: ScenePoint;
  /** Уровень поверхности; без него берётся верхний в точке. */
  level?: number;
}

/** Настройки маршрута. */
export interface SceneRouteOptions {
  /** Радиус агента в единицах сцены. */
  agentRadius: number;
}

/** Площадка перехода на другом поле. */
interface SceneLanding {
  element: SceneElement;
  boardId: number;
  targetBoardId: number;
  /** Центр площадки в координатах своего поля: докуда идти до перехода. */
  center: ScenePoint;
  /** Точка в мире корня: по ней площадки ищут пару. */
  world: ScenePoint;
  /** Парная площадка на целевом поле; без пары перехода нет. */
  pair: SceneLanding | null;
  /** Точка прибытия на этом поле после перехода с парной площадки. */
  entry: ScenePoint;
}

/** Ребро поиска: маршрут по полю или нулевой переход между площадками. */
interface SceneRouteEdge {
  to: number;
  cost: number;
  route: SceneNavRoute | null;
}

/**
 * Маршрут через стопку этажей.
 *
 * Поля связаны только площадками перехода: пара площадок — это одна точка
 * мира, поэтому актор переходит на другое поле, не прыгая. Марш и подъём
 * лежат на нижнем поле и проходятся навигацией этого поля; на верхнем поле
 * остаётся путь от площадки до цели. Пары не обязаны соединять соседние
 * этажи: длинная лестница — одно ребро между любыми двумя полями.
 */
export class SceneRouteService {
  constructor(
    private readonly navigation: SceneNavigationService = new SceneNavigationService(),
    private readonly floors: SceneFloorPlanService = new SceneFloorPlanService(),
  ) {}

  /**
   * Маршрут между точками разных полей.
   *
   * @param model Модель рендера: поля, вложения, элементы.
   * @param from Откуда идёт актор.
   * @param to Куда ведёт клик.
   * @param options Настройки маршрута.
   *
   * @returns Маршрут с участками по полям или null, если пути нет.
   */
  route(
    model: SceneRenderModel,
    from: SceneRoutePoint,
    to: SceneRoutePoint,
    options: SceneRouteOptions,
  ): SceneNavRoute | null {
    const direct = this.direct(model, from, to, options);
    if (from.boardId === to.boardId) {
      return direct;
    }

    const landings = this.landings(model);
    if (landings.length === 0) {
      return null;
    }

    // Узлы: площадки, затем старт и цель. Переход между парой площадок
    // нулевой: это одна точка мира, а не путь.
    const start = landings.length;
    const goal = landings.length + 1;
    const edges = new Map<number, SceneRouteEdge[]>();
    const add = (fromNode: number, edge: SceneRouteEdge): void => {
      const list = edges.get(fromNode) ?? [];
      list.push(edge);
      edges.set(fromNode, list);
    };

    for (let index = 0; index < landings.length; index += 1) {
      const landing = landings[index];
      if (landing.pair !== null) {
        add(index, { to: landings.indexOf(landing.pair), cost: 0, route: null });
      }
      for (let next = 0; next < landings.length; next += 1) {
        const other = landings[next];
        if (next === index || other.boardId !== landing.boardId || other.pair === null) {
          continue;
        }

        const route = this.walk(model, landing.boardId, landing.entry, other.center, options, undefined);
        if (route !== null) {
          add(index, { to: next, cost: route.cost, route });
        }
      }
      if (landing.boardId === to.boardId) {
        const route = this.walk(model, landing.boardId, landing.entry, to.point, options, to.level);
        if (route !== null) {
          add(index, { to: goal, cost: route.cost, route });
        }
      }
    }

    for (let index = 0; index < landings.length; index += 1) {
      const landing = landings[index];
      if (landing.boardId !== from.boardId || landing.pair === null) {
        continue;
      }

      const route = this.walk(model, from.boardId, from.point, landing.center, options, undefined);
      if (route !== null) {
        add(start, { to: index, cost: route.cost, route });
      }
    }

    const path = this.shortest(edges, start, goal);
    if (path === null) {
      return null;
    }

    const segments: SceneNavSegment[] = [];
    let distance = 0;
    let cost = 0;
    for (const edge of path) {
      if (edge.route === null) {
        continue;
      }

      segments.push(...edge.route.segments);
      distance += edge.route.distance;
      cost += edge.route.cost;
    }

    if (segments.length === 0) {
      return null;
    }

    return { points: segments.flatMap((segment) => segment.points), segments, distance, cost };
  }

  /** Маршрут по одному полю: обычный случай и последний участок. */
  private direct(
    model: SceneRenderModel,
    from: SceneRoutePoint,
    to: SceneRoutePoint,
    options: SceneRouteOptions,
  ): SceneNavRoute | null {
    if (from.boardId !== to.boardId) {
      return null;
    }

    return this.walk(model, from.boardId, from.point, to.point, options, to.level, from.level);
  }

  /**
   * Маршрут по одному полю.
   *
   * @param model Модель рендера.
   * @param boardId Поле.
   * @param from Начало в координатах поля.
   * @param to Конец в координатах поля.
   * @param options Настройки.
   * @param toLevel Уровень цели; без него — верхний.
   * @param fromLevel Уровень старта; без него — верхний.
   *
   * @returns Маршрут по полю или null.
   */
  private walk(
    model: SceneRenderModel,
    boardId: number,
    from: ScenePoint,
    to: ScenePoint,
    options: SceneRouteOptions,
    toLevel?: number,
    fromLevel?: number,
  ): SceneNavRoute | null {
    const board = model.boards.find((item) => item.id === boardId);
    if (board === undefined) {
      return null;
    }

    const mesh = this.navigation.build(board, model.elements, model.resolution, {
      agentRadius: options.agentRadius,
    });
    if (mesh === null) {
      return null;
    }

    const startLevel = fromLevel ?? this.topLevel(mesh, from);
    const route = this.navigation.routeOn(mesh, from, to, {
      agentRadius: options.agentRadius,
      fromLevel: startLevel,
      toLevel,
    });
    if (route === null || route.points.length < 2) {
      return null;
    }

    return route;
  }

  /** Верхний уровень поверхности в точке. */
  private topLevel(mesh: SceneNavMesh, point: ScenePoint): number {
    let level = 0;
    for (const item of this.navigation.levelsAt(mesh, point)) {
      level = Math.max(level, item.level);
    }

    return level;
  }

  /**
   * Площадки перехода и их пары.
   *
   * Пара — площадка на целевом поле, ведущая назад и ближайшая в мире корня:
   * двери и лестницы хранят только обратную ссылку, а не общий id.
   *
   * @param model Модель рендера.
   *
   * @returns Площадки с парами.
   */
  private landings(model: SceneRenderModel): SceneLanding[] {
    const result: SceneLanding[] = [];
    for (const element of model.elements) {
      if (element.kind !== 'opening' || element.targetBoardId === null) {
        continue;
      }

      const transform = this.floors.transform(model, element.boardId);
      const center = this.center(element);
      result.push({
        element,
        boardId: element.boardId,
        targetBoardId: element.targetBoardId,
        center,
        world: this.floors.toRoot(transform, center),
        pair: null,
        entry: center,
      });
    }

    for (const landing of result) {
      let best: SceneLanding | null = null;
      let bestDistance = Number.POSITIVE_INFINITY;
      for (const candidate of result) {
        if (candidate.boardId !== landing.targetBoardId || candidate.targetBoardId !== landing.boardId) {
          continue;
        }

        const distance = Math.hypot(candidate.world[0] - landing.world[0], candidate.world[1] - landing.world[1]);
        if (distance < bestDistance) {
          best = candidate;
          bestDistance = distance;
        }
      }

      landing.pair = best;
    }

    for (const landing of result) {
      if (landing.pair === null) {
        continue;
      }

      const transform = this.floors.transform(model, landing.boardId);
      landing.entry = this.floors.toLocal(transform, landing.pair.world);
    }

    return result;
  }

  /** Центр площадки: середина её полосы. */
  private center(element: SceneElement): ScenePoint {
    const shape = element.shape;
    if (shape.type === 'rect') {
      return [shape.x + shape.w / 2, shape.y + shape.h / 2];
    }

    if (shape.type === 'wall' && shape.points.length >= 2) {
      const first = shape.points[0];
      const last = shape.points[shape.points.length - 1];

      return [(first[0] + last[0]) / 2, (first[1] + last[1]) / 2];
    }

    if ((shape.type === 'polygon' || shape.type === 'path') && shape.points.length > 0) {
      let x = 0;
      let y = 0;
      for (const point of shape.points) {
        x += point[0];
        y += point[1];
      }

      return [x / shape.points.length, y / shape.points.length];
    }

    return [0, 0];
  }

  /**
   * Кратчайший путь по рёбрам графа.
   *
   * @param edges Рёбра по узлам.
   * @param start Стартовый узел.
   * @param goal Целевой узел.
   *
   * @returns Рёбра пути или null.
   */
  private shortest(edges: Map<number, SceneRouteEdge[]>, start: number, goal: number): SceneRouteEdge[] | null {
    const costs = new Map<number, number>([[start, 0]]);
    const previous = new Map<number, { node: number; edge: SceneRouteEdge }>();
    const queue = new Set<number>([start]);
    while (queue.size > 0) {
      let current: number | null = null;
      let currentCost = Number.POSITIVE_INFINITY;
      for (const node of queue) {
        const cost = costs.get(node) ?? Number.POSITIVE_INFINITY;
        if (cost < currentCost) {
          current = node;
          currentCost = cost;
        }
      }

      if (current === null) {
        break;
      }

      queue.delete(current);
      if (current === goal) {
        break;
      }

      for (const edge of edges.get(current) ?? []) {
        const next = currentCost + edge.cost;
        if (next < (costs.get(edge.to) ?? Number.POSITIVE_INFINITY)) {
          costs.set(edge.to, next);
          previous.set(edge.to, { node: current, edge });
          queue.add(edge.to);
        }
      }
    }

    if (!costs.has(goal)) {
      return null;
    }

    const path: SceneRouteEdge[] = [];
    let node = goal;
    while (node !== start) {
      const step = previous.get(node);
      if (step === undefined) {
        return null;
      }

      path.push(step.edge);
      node = step.node;
    }

    return path.reverse();
  }
}
