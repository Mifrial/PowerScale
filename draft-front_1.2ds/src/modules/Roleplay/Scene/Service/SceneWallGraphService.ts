import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneBuilding } from '@/modules/Roleplay/Scene/Dto/SceneBuilding';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRoom } from '@/modules/Roleplay/Scene/Dto/SceneRoom';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import { sceneWallService } from '@/modules/Roleplay/Scene/Service/Instance/sceneWallService';
import type { SceneWallElement } from '@/modules/Roleplay/Scene/Service/SceneWallService';

/** Минимум элемента, нужный сети стен: подходит и черновик, и рендер. */
export interface SceneWallGraphElement {
  id: number;
  boardId: number;
  kind: SceneElementKind;
  shape: SceneShape;
  props: SceneElementProps;
}

/** Насколько площадь пола может отличаться от грани, чтобы считаться её полом. */
const FLOOR_AREA_TOLERANCE = 0.15;

/** Узел сети стен. */
interface WallGraphNode {
  point: ScenePoint;
  halfEdges: number[];
}

/** Ребро сети: отрезок между двумя узлами. */
interface WallGraphEdge {
  a: number;
  b: number;
  wallIds: number[];
}

/** Полуребро: направленный обход ребра. */
interface WallGraphHalfEdge {
  edge: number;
  from: number;
  to: number;
  angle: number;
  twin: number;
}

/** Грань сети. Ограниченная грань — комната, внешняя — контур строения. */
export interface WallGraphFace {
  points: ScenePoint[];
  /** Знаковая площадь: у ограниченной грани положительная. */
  area: number;
  wallIds: number[];
  bounded: boolean;
}

/** Сеть стен поля. */
export interface WallGraph {
  boardId: number;
  nodes: WallGraphNode[];
  edges: WallGraphEdge[];
  faces: WallGraphFace[];
}

/**
 * Сеть стен и комнаты как её грани.
 *
 * Хранение не меняется: стены остаются отдельными лентами. Сеть выводится из
 * совпавших вершин, поэтому сварка (`SceneWallService.weld`) должна пройти до
 * построения. Комната — ограниченная грань; пол — обычный террейн внутри неё.
 */
export class SceneWallGraphService {
  /** Строит сеть по стенам поля. */
  build(elements: readonly SceneWallGraphElement[], boardId: number): WallGraph {
    // Копия для сварки: граф не должен менять черновик, но обязан видеть те же
    // узлы, что и редактор. Иначе перегородка, упирающаяся в тело стены, не
    // делит комнату до следующего сохранения.
    const walls: SceneWallElement[] = elements
      .filter((element) => element.boardId === boardId && element.kind === 'obstacle' && element.shape.type === 'wall')
      .map((element) => ({
        id: element.id,
        boardId: element.boardId,
        kind: element.kind,
        shape: JSON.parse(JSON.stringify(element.shape)) as SceneShape,
        props: element.props,
      }));
    sceneWallService.weld(walls, boardId, 1);

    const nodes: WallGraphNode[] = [];
    const nodeIndex = new Map<string, number>();
    const edges: WallGraphEdge[] = [];
    const edgeIndex = new Map<string, number>();

    const nodeOf = (point: ScenePoint): number => {
      const key = `${point[0]},${point[1]}`;
      const existing = nodeIndex.get(key);
      if (existing !== undefined) {
        return existing;
      }

      nodeIndex.set(key, nodes.length);
      nodes.push({ point: [point[0], point[1]], halfEdges: [] });

      return nodes.length - 1;
    };

    for (const wall of walls) {
      if (wall.shape.type !== 'wall') {
        continue;
      }

      const points = wall.shape.points;
      for (let index = 1; index < points.length; index += 1) {
        const a = nodeOf(points[index - 1]);
        const b = nodeOf(points[index]);
        if (a === b) {
          continue;
        }

        const key = a < b ? `${a}:${b}` : `${b}:${a}`;
        const existing = edgeIndex.get(key);
        if (existing !== undefined) {
          edges[existing].wallIds.push(wall.id);

          continue;
        }

        edgeIndex.set(key, edges.length);
        edges.push({ a, b, wallIds: [wall.id] });
      }
    }

    const halfEdges: WallGraphHalfEdge[] = [];
    for (let index = 0; index < edges.length; index += 1) {
      const edge = edges[index];
      const forward = halfEdges.length;
      const backward = forward + 1;
      halfEdges.push({
        edge: index,
        from: edge.a,
        to: edge.b,
        angle: this.angle(nodes[edge.a].point, nodes[edge.b].point),
        twin: backward,
      });
      halfEdges.push({
        edge: index,
        from: edge.b,
        to: edge.a,
        angle: this.angle(nodes[edge.b].point, nodes[edge.a].point),
        twin: forward,
      });
      nodes[edge.a].halfEdges.push(forward);
      nodes[edge.b].halfEdges.push(backward);
    }

    for (const node of nodes) {
      node.halfEdges.sort((left, right) => halfEdges[left].angle - halfEdges[right].angle);
    }

    // Следующее полуребро грани: в узле прихода берём полуребро, ближайшее
    // по часовой стрелке от обратного. Так грань остаётся слева, а внешняя
    // грань обходится в другую сторону и получает отрицательную площадь.
    const next = (halfEdge: number): number => {
      const twin = halfEdges[halfEdge].twin;
      const list = nodes[halfEdges[twin].from].halfEdges;
      const position = list.indexOf(twin);

      return list[(position - 1 + list.length) % list.length];
    };

    const visited = new Set<number>();
    const faces: WallGraphFace[] = [];
    for (let start = 0; start < halfEdges.length; start += 1) {
      if (visited.has(start)) {
        continue;
      }

      const walk: number[] = [];
      let current = start;
      let guard = 0;
      do {
        visited.add(current);
        walk.push(current);
        current = next(current);
        guard += 1;
      } while (current !== start && guard <= halfEdges.length * 2);

      if (current !== start) {
        continue;
      }

      const points = walk.map((index) => nodes[halfEdges[index].from].point);
      const area = this.signedArea(points);
      const wallIds: number[] = [];
      for (const index of walk) {
        for (const wallId of edges[halfEdges[index].edge].wallIds) {
          if (!wallIds.includes(wallId)) {
            wallIds.push(wallId);
          }
        }
      }

      faces.push({ points, area, wallIds, bounded: area > 0.5 });
    }

    return { boardId, nodes, edges, faces };
  }

  /** Ограниченная грань под точкой; из вложенных берётся самая маленькая. */
  faceAt(graph: WallGraph, point: ScenePoint): WallGraphFace | null {
    let best: WallGraphFace | null = null;
    for (const face of graph.faces) {
      if (!face.bounded || !this.contains(face.points, point)) {
        continue;
      }

      if (best === null || face.area < best.area) {
        best = face;
      }
    }

    return best;
  }

  /**
   * Комнаты поля.
   *
   * Комната — ограниченная грань. В её набор входят стены грани, проёмы в них,
   * полы внутри и всё содержимое: так перетаскивание комнаты двигает её
   * целиком, а не только контур.
   */
  rooms(elements: readonly SceneWallGraphElement[], boardId: number, bounds: SceneBounds): SceneRoom[] {
    const graph = this.build(elements, boardId);
    const rooms: SceneRoom[] = [];
    for (const face of graph.faces) {
      if (!face.bounded) {
        continue;
      }

      const { elementIds, floorIds } = this.faceElements(elements, boardId, bounds, face);
      rooms.push({
        points: face.points,
        area: face.area,
        wallIds: [...face.wallIds],
        elementIds,
        floorIds,
      });
    }

    return rooms.sort((left, right) => left.area - right.area);
  }

  /** Набор элементов грани: стены, проёмы, полы и содержимое. */
  private faceElements(
    elements: readonly SceneWallGraphElement[],
    boardId: number,
    bounds: SceneBounds,
    face: WallGraphFace,
  ): { elementIds: number[]; floorIds: number[] } {
    const wallIds = new Set(face.wallIds);
    const elementIds = new Set<number>(wallIds);
    const floorIds = new Set<number>();

    for (const element of elements) {
      if (element.boardId !== boardId || wallIds.has(element.id) || this.isFullBoard(element, bounds)) {
        continue;
      }

      if (element.kind === 'opening' && element.props.wallId !== undefined && wallIds.has(element.props.wallId)) {
        elementIds.add(element.id);

        continue;
      }

      if (element.kind === 'terrain') {
        // Террейн принадлежит комнате, только если лежит в ней целиком:
        // озеро или остров, накрывающие комнату, двигать вместе с ней нельзя.
        if (!this.insideShape(element.shape, face.points)) {
          continue;
        }

        elementIds.add(element.id);
        floorIds.add(element.id);

        continue;
      }

      const anchor = this.anchor(element);
      if (anchor === null || !this.contains(face.points, anchor)) {
        continue;
      }

      elementIds.add(element.id);
    }

    return { elementIds: [...elementIds], floorIds: [...floorIds] };
  }

  /**
   * Строение: связная сеть стен и всё, что стоит внутри её внешнего контура.
   *
   * Нужно, чтобы утащить дом целиком, не выделяя каждую стену по отдельности.
   */
  building(elements: readonly SceneWallGraphElement[], boardId: number, wallId: number, bounds: SceneBounds): number[] {
    const graph = this.build(elements, boardId);
    const wallIds = this.component(graph, wallId);
    if (wallIds.length === 0) {
      return [wallId];
    }

    return this.buildingIds(graph, elements, boardId, wallIds, bounds);
  }

  /** Строения поля: по одному на связную сеть стен. */
  buildings(elements: readonly SceneWallGraphElement[], boardId: number, bounds: SceneBounds): SceneBuilding[] {
    return this.buildingsOf(this.build(elements, boardId), elements, boardId, bounds);
  }

  /** Строение под точкой; из вложенных берётся самое маленькое. */
  buildingAt(
    elements: readonly SceneWallGraphElement[],
    boardId: number,
    point: ScenePoint,
    bounds: SceneBounds,
  ): number[] {
    let best: SceneBuilding | null = null;
    for (const building of this.buildings(elements, boardId, bounds)) {
      if (!this.contains(building.points, point)) {
        continue;
      }

      if (best === null || building.area < best.area) {
        best = building;
      }
    }

    return best === null ? [] : best.elementIds;
  }

  /**
   * Грань, которой соответствует пол.
   *
   * Пол дома совпадает по площади с внешним контуром — он тянет всё строение.
   * Пол комнаты совпадает с её гранью — тянет комнату. Дорога или заплатка
   * внутри двора по площади не совпадают и остаются собой.
   */
  buildingOfTerrain(
    elements: readonly SceneWallGraphElement[],
    boardId: number,
    terrainId: number,
    bounds: SceneBounds,
  ): number[] {
    const terrain = elements.find(
      (element) => element.id === terrainId && element.boardId === boardId && element.kind === 'terrain',
    );
    if (terrain === undefined) {
      return [];
    }

    const graph = this.build(elements, boardId);
    const area = this.shapeArea(terrain.shape);
    const candidates: { area: number; elementIds: number[] }[] = [];
    const consider = (points: readonly ScenePoint[], target: number, elementIds: number[]): void => {
      if (!this.insideShape(terrain.shape, points) || Math.abs(area - target) > target * FLOOR_AREA_TOLERANCE) {
        return;
      }

      candidates.push({ area: target, elementIds });
    };

    for (const face of graph.faces) {
      if (!face.bounded) {
        continue;
      }

      consider(face.points, face.area, this.faceElements(elements, boardId, bounds, face).elementIds);
    }

    for (const building of this.buildingsOf(graph, elements, boardId, bounds)) {
      consider(building.points, building.area, building.elementIds);
    }

    candidates.sort((left, right) => left.area - right.area);

    return candidates[0]?.elementIds ?? [];
  }

  /** Строения по готовому графу. */
  private buildingsOf(
    graph: WallGraph,
    elements: readonly SceneWallGraphElement[],
    boardId: number,
    bounds: SceneBounds,
  ): SceneBuilding[] {
    const seen = new Set<string>();
    const list: SceneBuilding[] = [];
    for (const face of graph.faces) {
      for (const wallId of face.wallIds) {
        const wallIds = this.component(graph, wallId);
        if (wallIds.length === 0) {
          continue;
        }

        const key = [...wallIds].sort((left, right) => left - right).join(',');
        if (seen.has(key)) {
          continue;
        }

        seen.add(key);
        const outer = this.outerFace(graph, wallIds);
        if (outer === null || outer.points.length < 3) {
          continue;
        }

        list.push({
          points: outer.points,
          area: Math.abs(outer.area),
          wallIds,
          elementIds: this.buildingIds(graph, elements, boardId, wallIds, bounds),
        });
      }
    }

    return list.sort((left, right) => left.area - right.area);
  }

  /** Элементы строения: сеть стен, проёмы в них и всё внутри внешнего контура. */
  private buildingIds(
    graph: WallGraph,
    elements: readonly SceneWallGraphElement[],
    boardId: number,
    wallIds: readonly number[],
    bounds: SceneBounds,
  ): number[] {
    const ids = new Set<number>(wallIds);
    for (const element of elements) {
      if (
        element.boardId === boardId &&
        element.kind === 'opening' &&
        element.props.wallId !== undefined &&
        wallIds.includes(element.props.wallId)
      ) {
        ids.add(element.id);
      }
    }

    const outer = this.outerFace(graph, wallIds);
    if (outer === null || outer.points.length < 3) {
      return [...ids];
    }

    for (const element of elements) {
      if (ids.has(element.id) || element.boardId !== boardId || this.isFullBoard(element, bounds)) {
        continue;
      }

      if (element.kind === 'terrain') {
        if (this.insideShape(element.shape, outer.points)) {
          ids.add(element.id);
        }

        continue;
      }

      const anchor = this.anchor(element);
      if (anchor !== null && this.contains(outer.points, anchor)) {
        ids.add(element.id);
      }
    }

    return [...ids];
  }

  /** Связная сеть стен, в которой лежит стена. */
  component(graph: WallGraph, wallId: number): number[] {
    const wallNodes = new Map<number, Set<number>>();
    const nodeWalls = new Map<number, Set<number>>();
    for (const edge of graph.edges) {
      for (const id of edge.wallIds) {
        const nodes = wallNodes.get(id) ?? new Set<number>();
        nodes.add(edge.a);
        nodes.add(edge.b);
        wallNodes.set(id, nodes);

        for (const node of [edge.a, edge.b]) {
          const walls = nodeWalls.get(node) ?? new Set<number>();
          walls.add(id);
          nodeWalls.set(node, walls);
        }
      }
    }

    if (!wallNodes.has(wallId)) {
      return [];
    }

    const walls = new Set<number>([wallId]);
    const queue = [wallId];
    while (queue.length > 0) {
      const current = queue.shift();
      if (current === undefined) {
        break;
      }

      for (const node of wallNodes.get(current) ?? []) {
        for (const neighbour of nodeWalls.get(node) ?? []) {
          if (!walls.has(neighbour)) {
            walls.add(neighbour);
            queue.push(neighbour);
          }
        }
      }
    }

    return [...walls];
  }

  /** Внешняя грань компонента: контур строения. */
  private outerFace(graph: WallGraph, wallIds: readonly number[]): WallGraphFace | null {
    let outer: WallGraphFace | null = null;
    for (const face of graph.faces) {
      if (face.bounded || !face.wallIds.some((id) => wallIds.includes(id))) {
        continue;
      }

      if (outer === null || Math.abs(face.area) > Math.abs(outer.area)) {
        outer = face;
      }
    }

    return outer;
  }

  /** Террейн целиком внутри грани; касание границы считается попаданием. */
  private insideShape(shape: SceneShape, polygon: readonly ScenePoint[]): boolean {
    const points = this.shapePoints(shape);
    if (points.length === 0) {
      return false;
    }

    return points.every((point) => this.contains(polygon, point) || this.nearBoundary(point, polygon));
  }

  /** Опорные точки фигуры для проверки вложения. */
  private shapePoints(shape: SceneShape): ScenePoint[] {
    if (shape.type === 'point') {
      return [[shape.x, shape.y]];
    }

    if (shape.type === 'rect') {
      return [
        [shape.x, shape.y],
        [shape.x + shape.w, shape.y],
        [shape.x + shape.w, shape.y + shape.h],
        [shape.x, shape.y + shape.h],
      ];
    }

    if (shape.type === 'circle') {
      return [
        [shape.cx - shape.r, shape.cy - shape.r],
        [shape.cx + shape.r, shape.cy - shape.r],
        [shape.cx + shape.r, shape.cy + shape.r],
        [shape.cx - shape.r, shape.cy + shape.r],
      ];
    }

    return shape.points;
  }

  /** Площадь фигуры в единицах сцены². */
  private shapeArea(shape: SceneShape): number {
    if (shape.type === 'rect') {
      return shape.w * shape.h;
    }

    if (shape.type === 'circle') {
      return Math.PI * shape.r * shape.r;
    }

    if (shape.type !== 'polygon') {
      return 0;
    }

    return Math.abs(this.signedArea(shape.points));
  }

  /** Точка на грани или рядом с ней. */
  private nearBoundary(point: ScenePoint, polygon: readonly ScenePoint[], tolerance = 1.5): boolean {
    for (let index = 0; index < polygon.length; index += 1) {
      const from = polygon[index];
      const to = polygon[(index + 1) % polygon.length];
      const dx = to[0] - from[0];
      const dy = to[1] - from[1];
      const square = dx * dx + dy * dy;
      if (square <= 0) {
        continue;
      }

      const t = Math.max(0, Math.min(1, ((point[0] - from[0]) * dx + (point[1] - from[1]) * dy) / square));
      if (Math.hypot(point[0] - (from[0] + dx * t), point[1] - (from[1] + dy * t)) <= tolerance) {
        return true;
      }
    }

    return false;
  }

  /** Представительная точка элемента: якорь или центр фигуры. */
  private anchor(element: SceneWallGraphElement): ScenePoint | null {
    const shape = element.shape;
    if (shape.type === 'point') {
      return [shape.x, shape.y];
    }

    if (shape.type === 'rect') {
      return [shape.x + shape.w / 2, shape.y + shape.h / 2];
    }

    if (shape.type === 'circle') {
      return [shape.cx, shape.cy];
    }

    if (shape.points.length === 0) {
      return null;
    }

    let x = 0;
    let y = 0;
    for (const point of shape.points) {
      x += point[0];
      y += point[1];
    }

    return [x / shape.points.length, y / shape.points.length];
  }

  /** Террейн на всё поле не принадлежит комнате. */
  private isFullBoard(element: SceneWallGraphElement, bounds: SceneBounds): boolean {
    const shape = element.shape;

    return (
      shape.type === 'rect' && shape.x === 0 && shape.y === 0 && shape.w === bounds.width && shape.h === bounds.height
    );
  }

  /** Точка внутри полигона; граница считается попаданием. */
  private contains(points: readonly ScenePoint[], point: ScenePoint): boolean {
    let inside = false;
    for (let index = 0, previous = points.length - 1; index < points.length; previous = index, index += 1) {
      const a = points[index];
      const b = points[previous];
      if (
        point[1] > Math.min(a[1], b[1]) &&
        point[1] <= Math.max(a[1], b[1]) &&
        point[0] <= Math.max(a[0], b[0]) &&
        a[1] !== b[1]
      ) {
        const x = ((point[1] - a[1]) * (b[0] - a[0])) / (b[1] - a[1]) + a[0];
        if (point[0] <= x) {
          inside = !inside;
        }
      }
    }

    return inside;
  }

  /** Знаковая площадь полигона. */
  private signedArea(points: readonly ScenePoint[]): number {
    let area = 0;
    for (let index = 0; index < points.length; index += 1) {
      const current = points[index];
      const next = points[(index + 1) % points.length];
      area += current[0] * next[1] - next[0] * current[1];
    }

    return area / 2;
  }

  /** Угол направления отрезка. */
  private angle(from: ScenePoint, to: ScenePoint): number {
    return Math.atan2(to[1] - from[1], to[0] - from[0]);
  }
}
