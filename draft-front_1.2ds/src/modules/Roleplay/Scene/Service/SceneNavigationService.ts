import earcut, { flatten } from 'earcut';
// ESM-сборка пакета отдаёт булевы операции только объектом по умолчанию:
// именованные импорты в браузере падают, хотя типы их объявляют.
import polygonClipping from 'polygon-clipping';
import type { MultiPolygon, Pair, Polygon } from 'polygon-clipping';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type {
  SceneNavMesh,
  SceneNavRegion,
  SceneNavRoute,
  SceneNavSegment,
  SceneNavTransition,
  SceneNavTransitionType,
  SceneNavTriangle,
} from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import type { SceneDeckFrame } from '@/modules/Roleplay/Scene/Service/SceneDeck';
import { sceneDecorFootprint, type SceneWorldFootprint } from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';
import {
  sceneDeckBaseBlockers,
  sceneDeckEndEdge,
  sceneDeckFrame,
  sceneDeckRailHeight,
  sceneDeckRailPolygons,
  sceneDeckRailSlope,
  sceneRampBaseBlocker,
} from '@/modules/Roleplay/Scene/Service/SceneDeck';
import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import type { SceneRampSlope } from '@/modules/Roleplay/Scene/Dto/SceneRampSlope';
import {
  isSceneDeck,
  sceneDeckClearance,
  sceneRampSlope,
  sceneSlopeElevation,
  sceneSurfaceElevation,
} from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

const { difference, intersection, union } = polygonClipping;

/** Настройки построения навигации. */
export interface SceneNavigationOptions {
  /** Радиус агента в единицах сцены; по умолчанию — радиус манекена. */
  agentRadius?: number;
  /** Уровень старта: 0 — земля, больше — настил. По умолчанию земля. */
  fromLevel?: number;
  /** Уровень цели: 0 — земля, больше — настил. Без него берётся верхний. */
  toLevel?: number;
}

/** Ребро треугольника для склейки соседей. */
interface NavEdge {
  id: number;
  triangle: number;
  a: ScenePoint;
  b: ScenePoint;
  minX: number;
  minY: number;
  maxX: number;
  maxY: number;
}

/** Общий участок двух рёбер. */
interface NavPortal {
  left: ScenePoint;
  right: ScenePoint;
  length: number;
}

/** Направленный переход между треугольниками через портал. */
interface NavArc {
  from: number;
  to: number;
  mid: ScenePoint;
  /** Переход между уровнями; у обычного звена — `null`. */
  transition: SceneNavTransition | null;
}

/** Участок цепочки треугольников на одном уровне. */
interface NavLevelRun {
  level: number;
  elevation: number;
  triangles: number[];
}

/** Минимальная двоичная куча для A*. */
class MinHeap {
  private readonly indexes: number[] = [];

  private readonly priorities: number[] = [];

  /** Добавляет вершину. */
  push(index: number, priority: number): void {
    this.indexes.push(index);
    this.priorities.push(priority);
    let child = this.indexes.length - 1;
    while (child > 0) {
      const parent = (child - 1) >> 1;
      if (this.priorities[parent] <= this.priorities[child]) {
        break;
      }

      this.swap(parent, child);
      child = parent;
    }
  }

  /** Забирает вершину с наименьшим приоритетом. */
  pop(): number | null {
    if (this.indexes.length === 0) {
      return null;
    }

    const top = this.indexes[0];
    const lastIndex = this.indexes.length - 1;
    this.swap(0, lastIndex);
    this.indexes.pop();
    this.priorities.pop();

    let parent = 0;
    for (;;) {
      const left = parent * 2 + 1;
      const right = left + 1;
      let smallest = parent;
      if (left < this.indexes.length && this.priorities[left] < this.priorities[smallest]) {
        smallest = left;
      }
      if (right < this.indexes.length && this.priorities[right] < this.priorities[smallest]) {
        smallest = right;
      }
      if (smallest === parent) {
        break;
      }

      this.swap(parent, smallest);
      parent = smallest;
    }

    return top;
  }

  /** Есть ли ещё вершины. */
  get size(): number {
    return this.indexes.length;
  }

  private swap(left: number, right: number): void {
    [this.indexes[left], this.indexes[right]] = [this.indexes[right], this.indexes[left]];
    [this.priorities[left], this.priorities[right]] = [this.priorities[right], this.priorities[left]];
  }
}

/**
 * Навигационная сетка сцены.
 *
 * Проходимая область собирается из террейна и опор булевыми операциями,
 * препятствия и основания декора вычитаются. Каждый регион одинаковой
 * скорости триангулируется, треугольники склеиваются по общим рёбрам, маршрут
 * ищется A* и спрямляется проверкой видимости. Это не occupancy-сетка света:
 * у навигации своя геометрия и явные скорости элементов.
 */
export class SceneNavigationService {
  private cacheKey = '';

  private cache: SceneNavMesh | null = null;

  constructor(private readonly geometry: SceneGeometryService = new SceneGeometryService()) {}

  /** Строит навигацию по активному полю; результат кэшируется. */
  build(
    board: SceneBoard,
    elements: readonly SceneElement[],
    resolution: number,
    options: SceneNavigationOptions = {},
  ): SceneNavMesh | null {
    const agentRadius = Math.max(1, options.agentRadius ?? resolution * SCENE_NAVIGATION.agentRadiusIpari);
    const key = this.cacheKeyOf(board, elements, resolution, agentRadius);
    if (key === this.cacheKey) {
      return this.cache;
    }

    this.cacheKey = key;
    this.cache = this.buildNow(board, elements, resolution, agentRadius);

    return this.cache;
  }

  /** Строит маршрут между точками активного поля. */
  route(
    board: SceneBoard,
    elements: readonly SceneElement[],
    resolution: number,
    from: ScenePoint,
    to: ScenePoint,
    options: SceneNavigationOptions = {},
  ): SceneNavRoute | null {
    const mesh = this.build(board, elements, resolution, options);
    if (mesh === null) {
      return null;
    }

    return this.routeOn(mesh, from, to, options);
  }

  /** Маршрут по уже построенной сетке. */
  routeOn(
    mesh: SceneNavMesh,
    from: ScenePoint,
    to: ScenePoint,
    options: SceneNavigationOptions = {},
  ): SceneNavRoute | null {
    const startLevel = options.fromLevel ?? 0;
    let start = this.locate(mesh, from, startLevel);
    if (start < 0) {
      start = this.locateTop(mesh, from);
    }
    if (start < 0) {
      start = this.nearestTriangle(mesh, from, startLevel);
    }

    let goal = options.toLevel === undefined ? this.locateTop(mesh, to) : this.locate(mesh, to, options.toLevel);
    if (goal < 0) {
      goal = this.nearestTriangle(mesh, to, options.toLevel ?? 0);
    }
    if (start < 0 || goal < 0) {
      return null;
    }

    const startTriangle = mesh.triangles[start];
    const goalTriangle = mesh.triangles[goal];
    const startPoint = this.speedAt(mesh, from, startTriangle.level) > 0 ? from : startTriangle.centroid;
    const goalPoint = this.speedAt(mesh, to, goalTriangle.level) > 0 ? to : goalTriangle.centroid;
    const chain = this.findChain(mesh, start, goal, startPoint, goalPoint);
    if (chain === null) {
      return null;
    }

    return this.buildRoute(mesh, chain, startPoint, goalPoint);
  }

  /**
   * Собирает маршрут по цепочке: режет её на участки одного уровня, спрямляет
   * каждый отдельно и считает стоимость.
   *
   * Спрямление через границу уровней запрещено: прямой отрезок «по настилу и
   * под ним» обошёл бы портал подъёма. Поэтому воронка работает внутри
   * участка, а стык уровней — общая точка на портале.
   */
  private buildRoute(
    mesh: SceneNavMesh,
    chain: readonly number[],
    from: ScenePoint,
    to: ScenePoint,
  ): SceneNavRoute | null {
    const runs = this.levelRuns(mesh, chain);
    const segments: SceneNavSegment[] = [];
    let start = from;
    for (let index = 0; index < runs.length; index += 1) {
      const run = runs[index];
      const last = index === runs.length - 1;
      const end = last
        ? to
        : this.portalMidpoint(mesh, run.triangles[run.triangles.length - 1], runs[index + 1].triangles[0]);
      if (end === null) {
        return null;
      }

      const path = this.funnel(mesh, run.triangles, start, end);
      const points = this.dedupe(this.smooth(mesh, path, run.level));
      const previous = index === 0 ? undefined : runs[index - 1].triangles[runs[index - 1].triangles.length - 1];
      const link =
        previous === undefined
          ? undefined
          : mesh.triangles[previous].links.find((candidate) => candidate.to === run.triangles[0]);
      segments.push(...this.splitSegments(mesh, points, run.level, run.elevation, link?.transition ?? null));
      start = end;
    }

    return segments.length === 0 ? null : this.measure(mesh, segments);
  }

  /** Режет цепочку на участки одного уровня. */
  private levelRuns(mesh: SceneNavMesh, chain: readonly number[]): NavLevelRun[] {
    const runs: NavLevelRun[] = [];
    for (const triangle of chain) {
      const level = mesh.triangles[triangle].level;
      const last = runs[runs.length - 1];
      if (last !== undefined && last.level === level) {
        last.triangles.push(triangle);
        continue;
      }

      runs.push({ level, elevation: mesh.triangles[triangle].elevation, triangles: [triangle] });
    }

    return runs;
  }

  /** Середина общего портала двух треугольников; null — звена нет. */
  private portalMidpoint(mesh: SceneNavMesh, from: number, to: number): ScenePoint | null {
    const link = mesh.triangles[from].links.find((candidate) => candidate.to === to);
    if (link === undefined) {
      return null;
    }

    return [(link.left[0] + link.right[0]) / 2, (link.left[1] + link.right[1]) / 2];
  }

  /**
   * Делит участок уровня на открытые и закрытые настилом куски.
   *
   * Так линия маршрута приглушается только там, где действительно идёт под
   * мостом, а не целиком из-за одного пересечения.
   */
  private splitSegments(
    mesh: SceneNavMesh,
    points: readonly ScenePoint[],
    level: number,
    elevation: number,
    transition: SceneNavTransition | null,
  ): SceneNavSegment[] {
    const segments: SceneNavSegment[] = [];
    // Пробы вдоль отрезка, а не только по его концам: иначе длинная прямая
    // под мостом целиком считалась бы закрытой.
    const step = Math.max(1, mesh.resolution * 0.5);
    let current: SceneNavSegment | null = null;
    for (let index = 1; index < points.length; index += 1) {
      const from = points[index - 1];
      const to = points[index];
      const length = this.distance(from, to);
      const pieces = Math.max(1, Math.ceil(length / step));
      for (let piece = 0; piece < pieces; piece += 1) {
        const t0 = piece / pieces;
        const t1 = (piece + 1) / pieces;
        const a: ScenePoint = [from[0] + (to[0] - from[0]) * t0, from[1] + (to[1] - from[1]) * t0];
        const b: ScenePoint = [from[0] + (to[0] - from[0]) * t1, from[1] + (to[1] - from[1]) * t1];
        const mid: ScenePoint = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
        const covered = mesh.regions.some((region) => region.level > level && this.regionContains(region, mid));
        if (current === null || current.covered !== covered) {
          const first = segments.length === 0;
          current = { level, elevation, covered, transition: first ? transition : null, points: [a, b] };
          segments.push(current);
        } else {
          current.points.push(b);
        }
      }
    }

    return segments;
  }

  /** Собирает проходимые регионы по уровням. */
  private buildNow(
    board: SceneBoard,
    elements: readonly SceneElement[],
    resolution: number,
    agentRadius: number,
  ): SceneNavMesh | null {
    const own = elements.filter((element) => element.boardId === board.id);
    const regions = this.buildRegions(board, own, resolution, agentRadius);
    if (regions.length === 0) {
      return null;
    }

    const triangles = this.triangulate(regions, resolution);
    if (triangles.length === 0) {
      return null;
    }

    this.link(triangles, resolution);
    this.connectLevels(triangles, regions, resolution);
    const maxSpeed = Math.max(...regions.map((region) => region.speed));

    return {
      triangles,
      regions,
      agentRadius,
      resolution,
      maxSpeed: Math.max(1, maxSpeed),
      bounds: { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight },
    };
  }

  /**
   * Собирает непересекающиеся регионы по уровням.
   *
   * Базовый слой — террейн и опоры на земле: верхний элемент задаёт скорость.
   * Настил выше `stepIpari` становится отдельным уровнем и не прячет то, что
   * под ним: вода и берег остаются проходимыми, если зазор не меньше роста
   * тела. Настил ниже шага — обычная замена пола, как раньше.
   */
  private buildRegions(
    board: SceneBoard,
    elements: readonly SceneElement[],
    resolution: number,
    agentRadius: number,
  ): SceneNavRegion[] {
    const frame = this.rectPolygon(
      agentRadius,
      agentRadius,
      board.boundWidth - agentRadius * 2,
      board.boundHeight - agentRadius * 2,
    );
    if (frame === null) {
      return [];
    }

    const base = Math.max(1, resolution);
    const step = base * SCENE_NAVIGATION.stepIpari;
    const bodyHeight = base * SCENE_NAVIGATION.bodyHeightIpari;
    const blockers: Polygon[] = [];
    const carves: Polygon[] = [];
    const positives: { element: SceneElement; speed: number }[] = [];
    const decks: {
      element: SceneElement;
      speed: number;
      elevation: number;
      slope: SceneRampSlope | null;
      frame: SceneDeckFrame | null;
      railBlocks: Polygon[];
      blockers: Polygon[];
    }[] = [];
    const rails: {
      speed: number;
      elevation: number;
      slope: SceneRampSlope | null;
      frame: SceneDeckFrame | null;
      ring: ScenePoint[];
      blockers: Polygon[];
      order: number;
      id: number;
    }[] = [];
    const levelBlockers: { elevation: number; polygon: Polygon }[] = [];

    for (const element of elements) {
      if (element.kind === 'obstacle') {
        if (element.props.blocksMovement !== false) {
          const blocker = this.polygonOf(element, resolution, agentRadius);
          if (blocker !== null) {
            blockers.push(blocker);
            levelBlockers.push({ elevation: sceneSurfaceElevation(element), polygon: blocker });
          }
        }
        continue;
      }

      if (element.kind === 'decor') {
        const blocker = this.decorBlocker(element, resolution, agentRadius);
        if (blocker !== null) blockers.push(blocker);
        continue;
      }

      if (element.kind === 'opening') {
        const shape = this.polygonOf(element, resolution, agentRadius);
        if (shape === null) continue;
        if (element.props.state === 'open') {
          carves.push(shape);
        } else {
          blockers.push(shape);
          levelBlockers.push({ elevation: sceneSurfaceElevation(element), polygon: shape });
        }
        continue;
      }

      if (element.kind === 'terrain' || element.kind === 'support') {
        const speed = this.speedOf(element);
        const elevation = sceneSurfaceElevation(element);
        const deck = isSceneDeck(element);
        const deckFrame = deck ? sceneDeckFrame(element) : null;
        const railBlocks =
          deckFrame === null
            ? []
            : sceneDeckRailPolygons(deckFrame, resolution, agentRadius).map((ring) => [ring] as Polygon);
        const railRings = deckFrame === null ? [] : sceneDeckRailPolygons(deckFrame, resolution, 0);
        const slope = element.props.kind === 'ramp' ? sceneRampSlope(element, elements, resolution) : null;
        const railElevation = elevation + sceneDeckRailHeight(resolution);
        const railSlope = slope === null ? null : sceneDeckRailSlope(slope, resolution);

        // Настил отдельный уровень, если он выше шага над полем или если под
        // ним есть место для тела: мост через врезанное русло стоит на уровне
        // берега, но над водой он всё равно потолок.
        const clearance = deck ? sceneDeckClearance(element, elements, resolution) : 0;
        if (deck && (elevation > step || clearance >= bodyHeight)) {
          // Под настилом закрыты только те полосы, где просвет меньше роста
          // тела: у моста через реку это торцы на берегу, а вода под серединой
          // остаётся проходимой. У съезда закрыта низкая часть.
          if (element.props.kind === 'ramp') {
            const rampBlocker = sceneRampBaseBlocker(element, elements, resolution, agentRadius);
            if (rampBlocker !== null) {
              blockers.push([rampBlocker]);
            }
          } else {
            for (const band of sceneDeckBaseBlockers(element, elements, resolution, agentRadius)) {
              blockers.push([band]);
            }
          }
          if (speed > 0) {
            const deckBlockers = levelBlockers
              .filter((blocker) => Math.abs(blocker.elevation - elevation) <= step)
              .map((blocker) => blocker.polygon);
            decks.push({ element, speed, elevation, slope, frame: deckFrame, railBlocks, blockers: deckBlockers });
            for (const ring of railRings) {
              rails.push({
                speed,
                elevation: railElevation,
                slope: railSlope,
                frame: deckFrame,
                ring,
                blockers: deckBlockers,
                order: element.sortOrder,
                id: element.id,
              });
            }
          }
          continue;
        }

        if (speed <= 0) {
          const blocker = this.polygonOf(element, resolution, agentRadius);
          if (blocker !== null) blockers.push(blocker);
        } else {
          // Настил на уровне пола: его перила закрывают свои полосы, а сам
          // настил остаётся обычной проходимой поверхностью.
          if (deck) {
            for (const block of railBlocks) {
              blockers.push(block);
            }
            const floorBlockers = levelBlockers
              .filter((blocker) => Math.abs(blocker.elevation - elevation) <= step)
              .map((blocker) => blocker.polygon);
            for (const ring of railRings) {
              rails.push({
                speed,
                elevation: railElevation,
                slope: railSlope,
                frame: deckFrame,
                ring,
                blockers: floorBlockers,
                order: element.sortOrder,
                id: element.id,
              });
            }
          }
          positives.push({ element, speed });
        }
      }
    }

    let blocked = this.unionMany(blockers);
    if (carves.length > 0) {
      blocked = this.subtract(blocked, this.unionMany(carves));
    }

    positives.sort(
      (left, right) =>
        this.priority(left.element) - this.priority(right.element) ||
        right.element.sortOrder - left.element.sortOrder ||
        right.element.id - left.element.id,
    );

    const regions: SceneNavRegion[] = [];
    let covered: MultiPolygon = [];
    for (const positive of positives) {
      const shape = this.polygonOf(positive.element, resolution, 0);
      if (shape === null) {
        continue;
      }

      let region = this.subtract([shape], covered);
      region = this.subtract(region, blocked);
      region = this.intersect(region, [frame]);
      this.pushRegions(regions, region, positive.speed, 0, 0);
      covered = this.unionMany([...this.polygonsOf(covered), shape]);
    }

    // Настилы и их перила снизу вверх: уровень — это высота, а не порядок
    // создания. Перила выше настила на `railHeightIpari`, поэтому попадают
    // на следующий уровень.
    const levelEntries: {
      polygon: MultiPolygon;
      speed: number;
      elevation: number;
      slope: SceneRampSlope | null;
      rail: boolean;
      frame: SceneDeckFrame | null;
      order: number;
      id: number;
    }[] = [];

    for (const deck of decks) {
      const shape = this.polygonOf(deck.element, resolution, 0);
      if (shape === null) {
        continue;
      }

      let region: MultiPolygon = [shape];
      if (deck.blockers.length > 0) {
        region = this.subtract(region, this.unionMany(deck.blockers));
      }
      if (deck.railBlocks.length > 0) {
        region = this.subtract(region, this.unionMany(deck.railBlocks));
      }
      region = this.intersect(region, [frame]);
      levelEntries.push({
        polygon: region,
        speed: deck.speed,
        elevation: deck.elevation,
        slope: deck.slope,
        rail: false,
        frame: deck.frame,
        order: deck.element.sortOrder,
        id: deck.element.id,
      });
    }

    for (const rail of rails) {
      let region: MultiPolygon = [[rail.ring]];
      if (rail.blockers.length > 0) {
        region = this.subtract(region, this.unionMany(rail.blockers));
      }
      region = this.intersect(region, [frame]);
      levelEntries.push({
        polygon: region,
        speed: rail.speed,
        elevation: rail.elevation,
        slope: rail.slope,
        rail: true,
        frame: rail.frame,
        order: rail.order,
        id: rail.id,
      });
    }

    levelEntries.sort(
      (left, right) =>
        left.elevation - right.elevation ||
        Number(left.rail) - Number(right.rail) ||
        left.order - right.order ||
        left.id - right.id,
    );
    levelEntries.forEach((entry, index) => {
      this.pushRegions(
        regions,
        entry.polygon,
        entry.speed,
        index + 1,
        entry.elevation,
        entry.slope,
        entry.rail,
        entry.frame,
      );
    });

    return regions;
  }

  /** Превращает булеву геометрию в регионы с уровнем и высотой. */
  private pushRegions(
    regions: SceneNavRegion[],
    geometry: MultiPolygon,
    speed: number,
    level: number,
    elevation: number,
    slope: SceneRampSlope | null = null,
    rail = false,
    frame: SceneDeckFrame | null = null,
  ): void {
    for (const polygon of geometry) {
      if (polygon.length === 0) continue;
      const outer = this.normalizeRing(polygon[0]);
      if (outer.length < 3) continue;
      regions.push({
        speed,
        level,
        elevation,
        slope,
        rail,
        frame,
        outer,
        holes: polygon
          .slice(1)
          .map((ring) => this.normalizeRing(ring))
          .filter((ring) => ring.length >= 3),
      });
    }
  }

  /**
   * Связывает уровни порталами по контуру настила.
   *
   * Точка на краю настила ищет треугольник нижнего уровня снаружи: землю или
   * настил ниже. Перепад решает тип перехода: в пределах `stepIpari` — шаг, до
   * `climbIpari` — подъём, до `dropIpari` вниз — спуск. Больше — звена нет, и
   * настил доступен только через съезд или другой настил. Соседние точки
   * одного треугольника склеиваются в один портал.
   */
  private connectLevels(triangles: SceneNavTriangle[], regions: readonly SceneNavRegion[], resolution: number): void {
    const base = Math.max(1, resolution);
    const step = base * SCENE_NAVIGATION.stepIpari;
    const climb = base * SCENE_NAVIGATION.climbIpari;
    const drop = base * SCENE_NAVIGATION.dropIpari;
    const offsets = [1, base * 0.25, base * 0.5, base, base * 1.5 + 1];
    const levels = new Map<number, { elevation: number; slope: SceneRampSlope | null }>();
    for (const region of regions) {
      if (!levels.has(region.level)) {
        levels.set(region.level, { elevation: region.elevation, slope: region.slope });
      }
    }

    for (const region of regions) {
      if (region.level === 0) {
        continue;
      }

      const ring = region.outer;
      const clockwise = this.signedArea(ring) < 0;
      for (let edge = 0; edge < ring.length; edge += 1) {
        const a = ring[edge];
        const b = ring[(edge + 1) % ring.length];
        const length = this.distance(a, b);
        if (length <= SCENE_NAVIGATION.linkEpsilon) {
          continue;
        }

        // Перила стоят по длинным сторонам, вход — с торцов. Уровень перил
        // соединяется только длинными сторонами, уровень настила — только
        // торцами: иначе A* перескочил бы перила как обычный край.
        if (region.frame !== null) {
          const end = sceneDeckEndEdge(region.frame, a, b);
          if (region.rail ? end : !end) {
            continue;
          }
        }

        const dx = (b[0] - a[0]) / length;
        const dy = (b[1] - a[1]) / length;
        const sign = clockwise ? -1 : 1;
        const normal: ScenePoint = [sign * dy, -sign * dx];
        const samples = Math.max(1, Math.ceil(length / base));
        let group: { below: number; deckTriangle: number; from: ScenePoint; to: ScenePoint } | null = null;
        const flush = (): void => {
          if (group === null) {
            return;
          }

          const mid: ScenePoint = [(group.from[0] + group.to[0]) / 2, (group.from[1] + group.to[1]) / 2];
          const above = this.levelElevation(levels.get(region.level), mid);
          const belowLevel = triangles[group.below].level;
          const below = this.levelElevation(levels.get(belowLevel), mid);
          const delta = above - below;
          // Переход направленный: вверх — `climb`, вниз — `drop`. Один и тот
          // же перепад не может быть одновременно подъёмом и спуском, поэтому
          // звено пишется в каждую сторону со своим типом и знаком.
          const up = this.transitionType(delta, step, climb, drop);
          const down = this.transitionType(-delta, step, climb, drop);
          if (up !== null || down !== null) {
            this.addLevelLink(triangles, group.deckTriangle, group.below, group.from, group.to, up, down, delta);
          }
          group = null;
        };

        for (let sample = 0; sample <= samples; sample += 1) {
          const t = sample / samples;
          const point: ScenePoint = [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
          const deckTriangle = this.locateLevel(triangles, [point[0] - normal[0], point[1] - normal[1]], region.level);
          let below = -1;
          for (const offset of offsets) {
            below = this.locateBelow(
              triangles,
              [point[0] + normal[0] * offset, point[1] + normal[1] * offset],
              region.level,
            );
            if (below >= 0) {
              break;
            }
          }
          if (deckTriangle < 0 || below < 0) {
            flush();
            continue;
          }

          if (group !== null && group.below === below && group.deckTriangle === deckTriangle) {
            group.to = point;
            continue;
          }

          flush();
          group = { below, deckTriangle, from: point, to: point };
        }

        flush();
      }
    }
  }

  /** Высота уровня в точке: у съезда — по наклону. */
  private levelElevation(
    level: { elevation: number; slope: SceneRampSlope | null } | undefined,
    point: ScenePoint,
  ): number {
    if (level === undefined) {
      return 0;
    }

    return level.slope === null ? level.elevation : sceneSlopeElevation(level.slope, point);
  }

  /** Самый высокий уровень ниже указанного, содержащий точку. */
  private locateBelow(triangles: readonly SceneNavTriangle[], point: ScenePoint, level: number): number {
    let best = -1;
    for (let index = 0; index < triangles.length; index += 1) {
      const triangle = triangles[index];
      if (triangle.level >= level || triangle.level <= (best < 0 ? -1 : triangles[best].level)) {
        continue;
      }
      if (this.inTriangle(point, triangle.points)) {
        best = index;
      }
    }

    return best;
  }

  /** Тип перехода по перепаду; null — перепад слишком велик. */
  private transitionType(delta: number, step: number, climb: number, drop: number): SceneNavTransitionType | null {
    if (Math.abs(delta) <= step) {
      return 'walk';
    }
    if (delta > 0 && delta <= climb) {
      return 'climb';
    }
    if (delta < 0 && -delta <= drop) {
      return 'drop';
    }

    return null;
  }

  /** Двустороннее звено между уровнями. */
  /**
   * Добавляет звено между настилом и нижним уровнем.
   *
   * `up` — переход снизу вверх, `down` — сверху вниз. Направления
   * независимы: спуск может быть разрешён, когда подъём уже нет (настил выше
   * `climbIpari`, но в пределах `dropIpari`). Тогда звено существует только
   * в одну сторону, и маршрут не полезет наверх там, где не сможет.
   */
  private addLevelLink(
    triangles: SceneNavTriangle[],
    deck: number,
    base: number,
    from: ScenePoint,
    to: ScenePoint,
    up: SceneNavTransitionType | null,
    down: SceneNavTransitionType | null,
    delta: number,
  ): void {
    const length = this.distance(from, to);
    if (down !== null) {
      const transition: SceneNavTransition = { type: down, delta: -delta };
      triangles[deck].links.push({ to: base, left: from, right: to, length, transition });
    }
    if (up !== null) {
      const transition: SceneNavTransition = { type: up, delta };
      triangles[base].links.push({ to: deck, left: from, right: to, length, transition });
    }
  }

  /** Ориентированная площадь кольца. */
  private signedArea(points: readonly ScenePoint[]): number {
    let area = 0;
    for (let index = 0; index < points.length; index += 1) {
      const a = points[index];
      const b = points[(index + 1) % points.length];
      area += a[0] * b[1] - b[0] * a[1];
    }

    return area / 2;
  }

  /**
   * Триангулирует регионы; у каждой треугольной грани своя скорость.
   *
   * Регион режется сеткой перед `earcut`. Иначе вырез декора сшивается с
   * внешним контуром одним длинным разрезом: A* получает «шоссе» через
   * вырожденный треугольник, и маршрут уходит к дальнему концу выреза.
   * После нарезки разрез не длиннее ячейки, а сетка остаётся связной.
   */
  private triangulate(regions: readonly SceneNavRegion[], resolution: number): SceneNavTriangle[] {
    const triangles: SceneNavTriangle[] = [];
    const cell = Math.max(32, resolution * SCENE_NAVIGATION.triangulationCellIpari);
    for (const region of regions) {
      const polygon: Polygon = [region.outer, ...region.holes];
      const bounds = this.boundsOfRing(region.outer);
      const spans = Math.ceil(bounds.width / cell) > 1 || Math.ceil(bounds.height / cell) > 1;
      if (!spans) {
        this.pushTriangles(triangles, polygon, region.speed, region.level, region.elevation, region.rail);
        continue;
      }

      const startX = Math.floor(bounds.x / cell) * cell;
      const startY = Math.floor(bounds.y / cell) * cell;
      for (let x = startX; x < bounds.x + bounds.width; x += cell) {
        for (let y = startY; y < bounds.y + bounds.height; y += cell) {
          const clipped = intersection(polygon, [
            [
              [x, y],
              [x + cell, y],
              [x + cell, y + cell],
              [x, y + cell],
              [x, y],
            ],
          ]);
          for (const piece of clipped) {
            this.pushTriangles(triangles, piece, region.speed, region.level, region.elevation, region.rail);
          }
        }
      }
    }

    return triangles;
  }

  /** Треугольники одного простого полигона с дырками. */
  private pushTriangles(
    triangles: SceneNavTriangle[],
    polygon: Polygon,
    speed: number,
    level: number,
    elevation: number,
    rail: boolean,
  ): void {
    if (polygon.length === 0 || polygon[0].length < 3) {
      return;
    }

    const rings: Pair[][] = polygon;
    const flat = flatten(rings);
    if (flat.vertices.length < 6) {
      return;
    }

    const indexes = earcut(flat.vertices, flat.holes, flat.dimensions);
    for (let index = 0; index + 2 < indexes.length; index += 3) {
      const points: [ScenePoint, ScenePoint, ScenePoint] = [
        this.vertexAt(flat.vertices, indexes[index]),
        this.vertexAt(flat.vertices, indexes[index + 1]),
        this.vertexAt(flat.vertices, indexes[index + 2]),
      ];
      if (this.area(points) < 0.01) {
        continue;
      }

      triangles.push({
        points,
        speed,
        level,
        elevation,
        rail,
        centroid: [(points[0][0] + points[1][0] + points[2][0]) / 3, (points[0][1] + points[1][1] + points[2][1]) / 3],
        links: [],
      });
    }
  }

  /** Границы кольца. */
  private boundsOfRing(ring: Pair[]): { x: number; y: number; width: number; height: number } {
    let minX = Number.POSITIVE_INFINITY;
    let minY = Number.POSITIVE_INFINITY;
    let maxX = Number.NEGATIVE_INFINITY;
    let maxY = Number.NEGATIVE_INFINITY;
    for (const point of ring) {
      minX = Math.min(minX, point[0]);
      minY = Math.min(minY, point[1]);
      maxX = Math.max(maxX, point[0]);
      maxY = Math.max(maxY, point[1]);
    }

    return {
      x: minX,
      y: minY,
      width: Math.max(0, maxX - minX),
      height: Math.max(0, maxY - minY),
    };
  }

  /** Склеивает треугольники по общим или частично совпадающим рёбрам. */
  private link(triangles: SceneNavTriangle[], resolution: number): void {
    const edges: NavEdge[] = [];
    for (let triangle = 0; triangle < triangles.length; triangle += 1) {
      const points = triangles[triangle].points;
      for (let side = 0; side < 3; side += 1) {
        const a = points[side];
        const b = points[(side + 1) % 3];
        if (this.distance(a, b) <= SCENE_NAVIGATION.linkEpsilon) {
          continue;
        }

        edges.push({
          id: edges.length,
          triangle,
          a,
          b,
          minX: Math.min(a[0], b[0]),
          minY: Math.min(a[1], b[1]),
          maxX: Math.max(a[0], b[0]),
          maxY: Math.max(a[1], b[1]),
        });
      }
    }

    const cell = Math.max(1, resolution * SCENE_NAVIGATION.linkGridFactor);
    const buckets = new Map<string, number[]>();
    for (const edge of edges) {
      const left = Math.floor(edge.minX / cell);
      const right = Math.floor(edge.maxX / cell);
      const top = Math.floor(edge.minY / cell);
      const bottom = Math.floor(edge.maxY / cell);
      for (let x = left; x <= right; x += 1) {
        for (let y = top; y <= bottom; y += 1) {
          const key = `${x}:${y}`;
          const bucket = buckets.get(key);
          if (bucket === undefined) {
            buckets.set(key, [edge.id]);
          } else {
            bucket.push(edge.id);
          }
        }
      }
    }

    const best = new Map<string, { from: number; to: number; portal: NavPortal }>();
    const checked = new Set<string>();
    for (const bucket of buckets.values()) {
      for (let left = 0; left < bucket.length; left += 1) {
        for (let right = left + 1; right < bucket.length; right += 1) {
          const first = edges[bucket[left]];
          const second = edges[bucket[right]];
          if (first.triangle === second.triangle) {
            continue;
          }
          // Уровни склеиваются только порталами: общий участок контура не
          // делает настил соседом земли, иначе A* перескакивает высоту.
          if (triangles[first.triangle].level !== triangles[second.triangle].level) {
            continue;
          }

          const pair = first.id < second.id ? `${first.id}:${second.id}` : `${second.id}:${first.id}`;
          if (checked.has(pair)) {
            continue;
          }
          checked.add(pair);

          const portal = this.overlap(first, second);
          if (portal === null) {
            continue;
          }

          const from = Math.min(first.triangle, second.triangle);
          const to = Math.max(first.triangle, second.triangle);
          const key = `${from}:${to}`;
          const current = best.get(key);
          if (current === undefined || portal.length > current.portal.length) {
            best.set(key, { from, to, portal });
          }
        }
      }
    }

    for (const { from, to, portal } of best.values()) {
      triangles[from].links.push({
        to,
        left: portal.left,
        right: portal.right,
        length: portal.length,
        transition: null,
      });
      triangles[to].links.push({
        to: from,
        left: portal.left,
        right: portal.right,
        length: portal.length,
        transition: null,
      });
    }
  }

  /** Пересечение двух рёбер: общий участок прямой. */
  private overlap(first: NavEdge, second: NavEdge): NavPortal | null {
    const length = this.distance(first.a, first.b);
    if (length <= SCENE_NAVIGATION.linkEpsilon) {
      return null;
    }

    const direction: ScenePoint = [(first.b[0] - first.a[0]) / length, (first.b[1] - first.a[1]) / length];
    const lineDistance = (point: ScenePoint): number =>
      Math.abs((point[0] - first.a[0]) * direction[1] - (point[1] - first.a[1]) * direction[0]);
    if (
      lineDistance(second.a) > SCENE_NAVIGATION.linkEpsilon ||
      lineDistance(second.b) > SCENE_NAVIGATION.linkEpsilon
    ) {
      return null;
    }

    const project = (point: ScenePoint): number =>
      (point[0] - first.a[0]) * direction[0] + (point[1] - first.a[1]) * direction[1];
    const start = Math.max(0, Math.min(project(second.a), project(second.b)));
    const end = Math.min(length, Math.max(project(second.a), project(second.b)));
    if (end - start <= SCENE_NAVIGATION.linkEpsilon) {
      return null;
    }

    return {
      left: [first.a[0] + direction[0] * start, first.a[1] + direction[1] * start],
      right: [first.a[0] + direction[0] * end, first.a[1] + direction[1] * end],
      length: end - start,
    };
  }

  /**
   * A* по направленным порталам.
   *
   * Состояние — не треугольник, а вход в треугольник через конкретный портал:
   * только так стоимость перехода считается по серединам общих рёбер, а не
   * между центрами. У вытянутого треугольника центр далеко от портала, и
   * оценка «центр-центр» делала такой коридор дешевле, чем он есть.
   */
  private findChain(
    mesh: SceneNavMesh,
    start: number,
    goal: number,
    from: ScenePoint,
    to: ScenePoint,
  ): number[] | null {
    if (start === goal) {
      return [start];
    }

    const arcs: NavArc[] = [];
    const outgoing: number[][] = mesh.triangles.map(() => []);
    for (let triangle = 0; triangle < mesh.triangles.length; triangle += 1) {
      for (const link of mesh.triangles[triangle].links) {
        const arc: NavArc = {
          from: triangle,
          to: link.to,
          mid: [(link.left[0] + link.right[0]) / 2, (link.left[1] + link.right[1]) / 2],
          transition: link.transition,
        };
        outgoing[triangle].push(arcs.length);
        arcs.push(arc);
      }
    }

    const costs = new Float64Array(arcs.length).fill(Number.POSITIVE_INFINITY);
    const previous = new Int32Array(arcs.length).fill(-1);
    const closed = new Uint8Array(arcs.length);
    const heap = new MinHeap();
    const startSpeed = Math.max(1, mesh.triangles[start].speed);
    for (const arc of outgoing[start]) {
      const cost =
        (this.distance(from, arcs[arc].mid) * 100) / startSpeed + this.transitionCost(arcs[arc].transition, mesh);
      costs[arc] = cost;
      heap.push(arc, cost + this.arcHeuristic(mesh, arcs[arc], to));
    }

    let finish = -1;
    let finishCost = Number.POSITIVE_INFINITY;
    while (heap.size > 0) {
      const current = heap.pop();
      if (current === null) {
        break;
      }
      if (closed[current] === 1) {
        continue;
      }
      closed[current] = 1;
      const arc = arcs[current];
      if (arc.to === goal) {
        const total = costs[current] + (this.distance(arc.mid, to) * 100) / Math.max(1, mesh.triangles[goal].speed);
        if (total < finishCost) {
          finish = current;
          finishCost = total;
        }
        continue;
      }

      const speed = Math.max(1, mesh.triangles[arc.to].speed);
      for (const next of outgoing[arc.to]) {
        if (closed[next] === 1) {
          continue;
        }

        const nextArc = arcs[next];
        const cost =
          costs[current] +
          (this.distance(arc.mid, nextArc.mid) * 100) / speed +
          this.transitionCost(nextArc.transition, mesh);
        if (cost >= costs[next]) {
          continue;
        }

        costs[next] = cost;
        previous[next] = current;
        heap.push(next, cost + this.arcHeuristic(mesh, nextArc, to));
      }
    }

    if (finish < 0) {
      return null;
    }

    const chain: number[] = [];
    let current = finish;
    while (current >= 0) {
      chain.push(arcs[current].to);
      current = previous[current];
    }
    chain.push(start);
    chain.reverse();

    return chain;
  }

  /** Доплата за переход между уровнями: подъём дороже спуска. */
  private transitionCost(transition: SceneNavTransition | null, mesh: SceneNavMesh): number {
    if (transition === null || transition.type === 'walk') {
      return 0;
    }

    const ipari = transition.type === 'climb' ? SCENE_NAVIGATION.climbCostIpari : SCENE_NAVIGATION.dropCostIpari;

    return Math.max(1, mesh.resolution) * ipari;
  }

  /** Эвристика A* по порталу: расстояние до цели с максимальной скоростью. */
  private arcHeuristic(mesh: SceneNavMesh, arc: NavArc, goal: ScenePoint): number {
    return (this.distance(arc.mid, goal) * 100) / mesh.maxSpeed;
  }

  /** Эвристика A*: расстояние с максимальной скоростью сцены. */
  private heuristic(mesh: SceneNavMesh, triangle: number, goal: number): number {
    const from = mesh.triangles[triangle].centroid;
    const to = mesh.triangles[goal].centroid;

    return (this.distance(from, to) * 100) / mesh.maxSpeed;
  }

  /**
   * Строит путь по коридору треугольников.
   *
   * Порталы обходятся funnel-алгоритмом: путь прижимается к краю коридора, а
   * не идёт через середины общих рёбер. У вырожденных треугольников середина
   * ребра может быть далеко от самого коридора, и маршрут уходил бы в сторону.
   */
  private funnel(mesh: SceneNavMesh, chain: readonly number[], start: ScenePoint, goal: ScenePoint): ScenePoint[] {
    if (chain.length < 2) {
      return [start, goal];
    }

    const portals: [ScenePoint, ScenePoint][] = [];
    for (let index = 0; index < chain.length - 1; index += 1) {
      const from = mesh.triangles[chain[index]];
      const to = mesh.triangles[chain[index + 1]];
      const link = from.links.find((candidate) => candidate.to === chain[index + 1]);
      if (link === undefined) {
        continue;
      }

      // Ориентируем портал по ходу движения: funnel требует, чтобы left и
      // right были согласованы вдоль коридора.
      const travel: ScenePoint = [to.centroid[0] - from.centroid[0], to.centroid[1] - from.centroid[1]];
      const cross = (link.right[0] - link.left[0]) * travel[1] - (link.right[1] - link.left[1]) * travel[0];
      portals.push(cross > 0 ? [link.right, link.left] : [link.left, link.right]);
    }

    const path: ScenePoint[] = [start];
    let apex = start;
    let left = start;
    let right = start;
    let leftIndex = 0;
    let rightIndex = 0;
    const list: [ScenePoint, ScenePoint][] = [...portals, [goal, goal]];

    for (let index = 0; index < list.length; index += 1) {
      const [portalLeft, portalRight] = list[index];

      // Правая граница: вершина подтягивается, пока коридор не повернёт.
      if (this.turn(apex, right, portalRight) <= 0) {
        if (this.samePoint(apex, right) || this.turn(apex, left, portalRight) > 0) {
          right = portalRight;
          rightIndex = index;
        } else {
          const restart = leftIndex;
          path.push(left);
          apex = left;
          left = apex;
          right = apex;
          leftIndex = restart;
          rightIndex = restart;
          index = restart;
          continue;
        }
      }

      // Левая граница.
      if (this.turn(apex, left, portalLeft) >= 0) {
        if (this.samePoint(apex, left) || this.turn(apex, right, portalLeft) < 0) {
          left = portalLeft;
          leftIndex = index;
        } else {
          const restart = rightIndex;
          path.push(right);
          apex = right;
          left = apex;
          right = apex;
          leftIndex = restart;
          rightIndex = restart;
          index = restart;
          continue;
        }
      }
    }

    path.push(goal);

    return path;
  }

  /** Знак поворота a → b → c. */
  private turn(a: ScenePoint, b: ScenePoint, c: ScenePoint): number {
    return (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0]);
  }

  /** Совпадают ли точки в пределах допуска склейки. */
  private samePoint(a: ScenePoint, b: ScenePoint): boolean {
    return this.distance(a, b) <= SCENE_NAVIGATION.linkEpsilon;
  }

  /** Спрямляет участок одного уровня проверкой видимости и стоимости. */
  private smooth(mesh: SceneNavMesh, points: ScenePoint[], level: number): ScenePoint[] {
    if (points.length <= 2) {
      return points;
    }

    // Стоимость вдоль исходной цепочки: спрямление допустимо, только если
    // прямой отрезок не дороже заменяемого участка. Иначе маршрут через мост
    // схлопнулся бы в прямую через воду: та тоже проходима, но медленнее.
    const along = new Array<number>(points.length).fill(0);
    for (let index = 1; index < points.length; index += 1) {
      along[index] = along[index - 1] + this.segmentCost(mesh, points[index - 1], points[index], level);
    }

    const result: ScenePoint[] = [points[0]];
    let anchor = 0;
    while (anchor < points.length - 1) {
      let next = points.length - 1;
      while (next > anchor + 1) {
        const direct = this.segmentCost(mesh, points[anchor], points[next], level);
        const alongCost = along[next] - along[anchor];
        if (Number.isFinite(alongCost) && Number.isFinite(direct) && direct <= alongCost + 1e-6) {
          break;
        }
        next -= 1;
      }
      result.push(points[next]);
      anchor = next;
    }

    return result;
  }

  /** Стоимость отрезка на уровне; `Infinity`, если он выходит из области. */
  private segmentCost(mesh: SceneNavMesh, from: ScenePoint, to: ScenePoint, level: number): number {
    const length = this.distance(from, to);
    const step = Math.max(1, this.sampleStep(mesh));
    const steps = Math.max(1, Math.ceil(length / step));
    const piece = length / steps;
    let cost = 0;
    for (let index = 0; index < steps; index += 1) {
      const t = (index + 0.5) / steps;
      const point: ScenePoint = [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
      const speed = this.speedAt(mesh, point, level);
      if (speed <= 0) {
        return Number.POSITIVE_INFINITY;
      }
      cost += piece * (100 / Math.max(1, speed));
    }

    return cost;
  }

  /** Длина и стоимость маршрута по участкам уровней. */
  private measure(mesh: SceneNavMesh, segments: readonly SceneNavSegment[]): SceneNavRoute {
    const points: ScenePoint[] = [];
    let distance = 0;
    let cost = 0;
    const step = Math.max(1, this.sampleStep(mesh));
    for (const segment of segments) {
      for (const point of segment.points) {
        const last = points[points.length - 1];
        if (last === undefined || this.distance(last, point) > SCENE_NAVIGATION.linkEpsilon) {
          points.push(point);
        }
      }

      for (let index = 1; index < segment.points.length; index += 1) {
        const from = segment.points[index - 1];
        const to = segment.points[index];
        const length = this.distance(from, to);
        distance += length;
        const steps = Math.max(1, Math.ceil(length / step));
        const piece = length / steps;
        const fallback = this.speedAt(mesh, from, segment.level) || this.speedAt(mesh, to, segment.level);
        for (let sample = 0; sample < steps; sample += 1) {
          const t = (sample + 0.5) / steps;
          const point: ScenePoint = [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
          const speed = this.speedAt(mesh, point, segment.level) || fallback;
          cost += piece * (100 / Math.max(1, speed));
        }
      }
    }

    // Переходы между уровнями: подъём и спуск стоят как участок пути.
    // Тип берётся у звена, а не из разницы `elevation`: у съезда высота
    // участка — это высокий конец, но переход с него на землю всё равно `walk`.
    for (let index = 1; index < segments.length; index += 1) {
      const transition = segments[index].transition;
      if (transition === null || transition.type === 'walk') {
        continue;
      }
      const resolution = Math.max(1, mesh.resolution);
      cost +=
        resolution * (transition.type === 'climb' ? SCENE_NAVIGATION.climbCostIpari : SCENE_NAVIGATION.dropCostIpari);
    }

    return { points, segments: [...segments], distance, cost };
  }

  /** Скорость в точке на уровне. */
  private speedAt(mesh: SceneNavMesh, point: ScenePoint, level: number): number {
    for (const region of mesh.regions) {
      if (region.level !== level || !this.regionContains(region, point)) {
        continue;
      }

      return region.speed;
    }

    return 0;
  }

  /** Точка внутри региона; небольшая граница считается проходимой. */
  private regionContains(region: SceneNavRegion, point: ScenePoint): boolean {
    const inside = this.geometry.pointInPolygon(point, region.outer);
    if (!inside && this.geometry.distanceToPolyline(point, region.outer, true) > SCENE_NAVIGATION.linkEpsilon) {
      return false;
    }
    for (const hole of region.holes) {
      // Точка на самой кромке выреза — ещё проходима: путь по границе обрезки
      // не должен стоить как сквозь препятствие.
      if (
        this.geometry.pointInPolygon(point, hole) &&
        this.geometry.distanceToPolyline(point, hole, true) > SCENE_NAVIGATION.linkEpsilon
      ) {
        return false;
      }
    }

    return true;
  }

  /** Треугольник, содержащий точку. */
  /** Треугольник уровня, содержащий точку; `-1` — точка вне уровня. */
  private locate(mesh: SceneNavMesh, point: ScenePoint, level: number): number {
    return this.locateLevel(mesh.triangles, point, level);
  }

  /** Треугольник уровня по массиву: нужен и до сборки сетки. */
  private locateLevel(triangles: readonly SceneNavTriangle[], point: ScenePoint, level: number): number {
    for (let index = 0; index < triangles.length; index += 1) {
      const triangle = triangles[index];
      if (triangle.level !== level) {
        continue;
      }
      if (this.inTriangle(point, triangle.points)) {
        return index;
      }
    }

    return -1;
  }

  /**
   * Верхний уровень под точкой.
   *
   * Клик по настилу должен вести на настил, а не под него. Если настила нет,
   * это обычная земля. Выбор уровня, когда под настилом тоже хочется пройти, —
   * отдельный шаг: пока приоритет у верхней поверхности.
   */
  private locateTop(mesh: SceneNavMesh, point: ScenePoint): number {
    let best = -1;
    for (let index = 0; index < mesh.triangles.length; index += 1) {
      const triangle = mesh.triangles[index];
      if (triangle.rail || !this.inTriangle(point, triangle.points)) {
        continue;
      }
      if (best < 0 || triangle.level > mesh.triangles[best].level) {
        best = index;
      }
    }

    return best;
  }

  /**
   * Уровни, на которых лежит точка: земля, настилы, без перил.
   *
   * Нужно для выбора «на настил или под настил»: если уровней несколько,
   * маршрут строится до каждого, а выбор остаётся за игроком.
   */
  levelsAt(mesh: SceneNavMesh, point: ScenePoint): { level: number; elevation: number }[] {
    const found = new Map<number, number>();
    for (const triangle of mesh.triangles) {
      if (triangle.rail || !this.inTriangle(point, triangle.points)) {
        continue;
      }

      const current = found.get(triangle.level);
      if (current === undefined || triangle.elevation > current) {
        found.set(triangle.level, triangle.elevation);
      }
    }

    return [...found.entries()]
      .map(([level, elevation]) => ({ level, elevation }))
      .sort((left, right) => right.elevation - left.elevation || right.level - left.level);
  }

  /** Ближайший треугольник уровня в пределах радиуса агента. */
  private nearestTriangle(mesh: SceneNavMesh, point: ScenePoint, level: number): number {
    let best = -1;
    let bestDistance = Number.POSITIVE_INFINITY;
    for (let index = 0; index < mesh.triangles.length; index += 1) {
      const triangle = mesh.triangles[index];
      if (triangle.level !== level) {
        continue;
      }
      const distance = this.distanceToTriangle(point, triangle.points);
      if (distance < bestDistance) {
        bestDistance = distance;
        best = index;
      }
    }

    return bestDistance <= mesh.agentRadius * 2 ? best : -1;
  }

  /** Точка внутри треугольника; граница включена. */
  private inTriangle(point: ScenePoint, triangle: [ScenePoint, ScenePoint, ScenePoint]): boolean {
    const sign = (p: ScenePoint, a: ScenePoint, b: ScenePoint): number =>
      (p[0] - b[0]) * (a[1] - b[1]) - (a[0] - b[0]) * (p[1] - b[1]);
    const d1 = sign(point, triangle[0], triangle[1]);
    const d2 = sign(point, triangle[1], triangle[2]);
    const d3 = sign(point, triangle[2], triangle[0]);
    const hasNegative = d1 < -1e-6 || d2 < -1e-6 || d3 < -1e-6;
    const hasPositive = d1 > 1e-6 || d2 > 1e-6 || d3 > 1e-6;

    return !(hasNegative && hasPositive);
  }

  /** Расстояние до треугольника. */
  private distanceToTriangle(point: ScenePoint, triangle: [ScenePoint, ScenePoint, ScenePoint]): number {
    if (this.inTriangle(point, triangle)) {
      return 0;
    }

    let distance = Number.POSITIVE_INFINITY;
    for (let index = 0; index < 3; index += 1) {
      distance = Math.min(distance, this.geometry.distanceToSegment(point, triangle[index], triangle[(index + 1) % 3]));
    }

    return distance;
  }

  /** Полигон элемента для булевых операций; точка — не полигон. */
  private polygonOf(element: SceneElement, resolution: number, pad: number): Polygon | null {
    const shape = element.shape;
    if (shape.type === 'point') {
      return null;
    }

    const segments =
      shape.type === 'circle'
        ? Math.max(
            SCENE_NAVIGATION.circleSegmentsMin,
            Math.min(SCENE_NAVIGATION.circleSegmentsMax, Math.ceil((Math.PI * 2 * (shape.r + pad)) / 8)),
          )
        : SCENE_NAVIGATION.circleSegmentsMax;
    const outline = this.geometry.outline(shape, pad, segments, sceneRotation(element));
    const ring = this.normalizeRing(outline.map((point): Pair => [point[0], point[1]]));

    return ring.length >= 3 ? [ring] : null;
  }

  /** Основание декора: явное поле или старый круг от ширины спрайта. */
  private decorBlocker(element: SceneElement, resolution: number, agentRadius: number): Polygon | null {
    // Проходимый декор (трава, ковёр) не вычитается из навмеша: скорость выше
    // нуля — это поверхность, а не препятствие.
    if (this.decorSpeed(element) > 0) {
      return null;
    }

    if (element.shape.type !== 'point') {
      return this.polygonOf(element, resolution, agentRadius);
    }

    const footprint = sceneDecorFootprint(element, resolution);

    return footprint === null ? null : this.footprintPolygon(footprint, agentRadius);
  }

  /** Мировое основание как полигон с запасом на радиус тела. */
  private footprintPolygon(area: SceneWorldFootprint, pad: number): Polygon | null {
    if (area.shape === 'circle') {
      return this.circlePolygon(area.center, area.radius + pad);
    }

    const offset = this.geometry.offsetPolygon(area.points, pad);
    const ring = this.normalizeRing(offset ?? area.points);

    return ring.length >= 3 ? [ring] : null;
  }

  /** Скорость декора: явная или непроходимость по умолчанию. */
  private decorSpeed(element: SceneElement): number {
    const speed = element.props.speed;
    if (typeof speed !== 'number' || !Number.isFinite(speed)) {
      return 0;
    }

    return Math.max(0, Math.min(SCENE_NAVIGATION.maxSpeed, speed));
  }

  /** Пресет или явная скорость элемента. */
  private speedOf(element: SceneElement): number {
    const kind = element.props.kind ?? '';
    const preset =
      element.kind === 'support'
        ? kind === 'bridge' || kind === 'ramp'
          ? SCENE_SURFACE.speeds.bridge
          : 100
        : (SCENE_SURFACE.speeds[kind as keyof typeof SCENE_SURFACE.speeds] ?? 100);
    const speed = element.props.speed;

    return typeof speed === 'number' && Number.isFinite(speed)
      ? Math.max(0, Math.min(SCENE_NAVIGATION.maxSpeed, speed))
      : preset;
  }

  /** Порядок наложения: опоры, вода, остальной террейн. */
  private priority(element: SceneElement): number {
    if (element.kind === 'support') return 0;
    if (element.props.kind === 'water') return 1;

    return 2;
  }

  /** Прямоугольник как полигон. */
  private rectPolygon(x: number, y: number, width: number, height: number): Polygon | null {
    if (width <= 0 || height <= 0) {
      return null;
    }

    return [
      [
        [x, y],
        [x + width, y],
        [x + width, y + height],
        [x, y + height],
      ],
    ];
  }

  /** Круг как полигон. */
  private circlePolygon(center: ScenePoint, radius: number): Polygon | null {
    if (radius <= 0) {
      return null;
    }

    const segments = Math.max(
      SCENE_NAVIGATION.circleSegmentsMin,
      Math.min(SCENE_NAVIGATION.circleSegmentsMax, Math.ceil((Math.PI * 2 * radius) / 8)),
    );
    const ring: Pair[] = [];
    for (let index = 0; index < segments; index += 1) {
      const angle = (index / segments) * Math.PI * 2;
      ring.push([center[0] + Math.cos(angle) * radius, center[1] + Math.sin(angle) * radius]);
    }

    return [ring];
  }

  /** Убирает замыкающую точку и дубликаты кольца. */
  private normalizeRing(points: Pair[]): ScenePoint[] {
    const ring: ScenePoint[] = [];
    for (const point of points) {
      const last = ring[ring.length - 1];
      if (last !== undefined && this.distance(last, [point[0], point[1]]) <= 1e-6) {
        continue;
      }
      ring.push([point[0], point[1]]);
    }
    if (ring.length > 1 && this.distance(ring[0], ring[ring.length - 1]) <= 1e-6) {
      ring.pop();
    }

    return ring;
  }

  /** Плоский список полигонов из MultiPolygon. */
  private polygonsOf(geometry: MultiPolygon): Polygon[] {
    return geometry;
  }

  /** Объединение набора полигонов. */
  private unionMany(polygons: readonly Polygon[]): MultiPolygon {
    if (polygons.length === 0) {
      return [];
    }

    let result: MultiPolygon = [polygons[0]];
    for (let index = 1; index < polygons.length; index += 1) {
      result = union(result, polygons[index]);
    }

    return result;
  }

  /** Разность с пустыми операндами. */
  private subtract(subject: MultiPolygon, clip: MultiPolygon): MultiPolygon {
    if (subject.length === 0 || clip.length === 0) {
      return subject;
    }

    return difference(subject, clip);
  }

  /** Пересечение с пустыми операндами. */
  private intersect(subject: MultiPolygon, clip: MultiPolygon): MultiPolygon {
    if (subject.length === 0 || clip.length === 0) {
      return [];
    }

    return intersection(subject, clip);
  }

  /** Вершина из плоского массива earcut. */
  private vertexAt(vertices: number[], index: number): ScenePoint {
    return [vertices[index * 2], vertices[index * 2 + 1]];
  }

  /** Удвоенная площадь треугольника. */
  private area(triangle: [ScenePoint, ScenePoint, ScenePoint]): number {
    return Math.abs(
      (triangle[1][0] - triangle[0][0]) * (triangle[2][1] - triangle[0][1]) -
        (triangle[2][0] - triangle[0][0]) * (triangle[1][1] - triangle[0][1]),
    );
  }

  /** Убирает слишком близкие точки. */
  private dedupe(points: ScenePoint[]): ScenePoint[] {
    const result: ScenePoint[] = [];
    for (const point of points) {
      const last = result[result.length - 1];
      if (last !== undefined && this.distance(last, point) <= SCENE_NAVIGATION.linkEpsilon) {
        continue;
      }
      result.push(point);
    }

    return result;
  }

  /** Шаг выборки вдоль отрезка. */
  private sampleStep(mesh: SceneNavMesh): number {
    return Math.max(1, mesh.agentRadius / SCENE_NAVIGATION.agentRadiusIpari / SCENE_NAVIGATION.sampleFactor);
  }

  /** Ключ кэша: поле, разрешение, радиус и форма элементов. */
  private cacheKeyOf(
    board: SceneBoard,
    elements: readonly SceneElement[],
    resolution: number,
    agentRadius: number,
  ): string {
    const shapes = elements
      .filter((element) => element.boardId === board.id)
      .map((element) =>
        [
          element.id,
          element.kind,
          element.sortOrder,
          element.props.kind ?? '',
          element.props.speed ?? '',
          element.props.height ?? '',
          element.props.elevation ?? '',
          element.props.depth ?? '',
          element.props.shoreWidth ?? '',
          element.props.rotation ?? '',
          element.props.scale ?? '',
          element.props.baseRadius ?? '',
          element.props.sightRadius ?? '',
          element.props.sightBottom ?? '',
          JSON.stringify(element.props.footprint ?? null),
          JSON.stringify(element.props.silhouette ?? null),
          element.props.blocksVision ?? '',
          element.props.blocksMovement ?? '',
          element.props.state ?? '',
          JSON.stringify(element.shape),
        ].join(':'),
      );

    return [board.id, board.boundWidth, board.boundHeight, resolution, agentRadius.toFixed(3), ...shapes].join('|');
  }

  /** Расстояние между точками. */
  private distance(from: ScenePoint, to: ScenePoint): number {
    return Math.hypot(to[0] - from[0], to[1] - from[1]);
  }
}
