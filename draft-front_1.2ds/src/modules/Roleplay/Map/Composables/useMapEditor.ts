import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { CanvasPoint } from '@/modules/Roleplay/Map/Dto/CanvasPoint';
import type { MapAreaHitCandidate } from '@/modules/Roleplay/Map/Dto/MapAreaHitCandidate';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapEdgeHitCandidate } from '@/modules/Roleplay/Map/Dto/MapEdgeHitCandidate';
import type { MapEditTarget } from '@/modules/Roleplay/Map/Dto/MapEditTarget';
import type { MapHit } from '@/modules/Roleplay/Map/Dto/MapHit';
import type { MapHitCandidates } from '@/modules/Roleplay/Map/Dto/MapHitCandidates';
import type { MapLineHitCandidate } from '@/modules/Roleplay/Map/Dto/MapLineHitCandidate';
import type { MapNodeHitCandidate } from '@/modules/Roleplay/Map/Dto/MapNodeHitCandidate';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapSize } from '@/modules/Roleplay/Map/Dto/MapSize';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapViewport } from '@/modules/Roleplay/Map/Dto/MapViewport';
import type { MapToolMode } from '@/modules/Roleplay/Map/Enum/MapToolMode';
import type { MapDraftApi } from '@/modules/Roleplay/Map/Composables/useMapDraft';
import { MAP_CANVAS_CONFIG } from '@/modules/Roleplay/Map/Constant/Canvas/MAP_CANVAS_CONFIG';
import { mapGeometryService, mapHitTestService, mapViewportService } from '@/modules/Roleplay/Map/init';

type LineTarget = Extract<MapEditTarget, { kind: 'line-vertex' | 'line-handle-in' | 'line-handle-out' | 'line-width' }>;

type DragState =
  | { kind: 'node'; nodeId: number }
  | { kind: 'area'; areaId: number; start: MapPoint; origin: MapPoint[] }
  | { kind: 'line'; lineId: number; start: MapPoint; origin: MapPoint[] }
  | { kind: 'vertex'; target: Extract<MapEditTarget, { kind: 'area-vertex' | 'line-vertex' | 'edge-vertex' }> }
  | { kind: 'handle'; target: Extract<MapEditTarget, { kind: 'line-handle-in' | 'line-handle-out' }> }
  | { kind: 'width'; target: Extract<MapEditTarget, { kind: 'line-width' }> };

/**
 * Состояние редактора карты: вид, инструмент, черновики, вершины и перетаскивание.
 *
 * Рисование остаётся в `MapCanvas`, правки снимка — в `useMapDraft`.
 */
export function useMapEditor(params: {
  map: Ref<MapDetail | null>;
  variant: Ref<MapVariant | null>;
  selected: Ref<MapHit | null>;
  draft: MapDraftApi;
  onOpenNode: (nodeId: number) => void;
  onCreateNode: (point: MapPoint) => void;
  onCreateEdge: (fromNodeId: number, toNodeId: number) => void;
  onCreateArea: (path: MapPoint[]) => void;
  onCreateRect: (path: MapPoint[]) => void;
  onCreateLine: (points: MapPoint[]) => void;
  onCalibrate: (points: MapPoint[]) => void;
}) {
  const viewport = ref<MapViewport>({ scale: 1, offsetX: 0, offsetY: 0 });
  const canvasSize = ref<MapSize>({ width: MAP_CANVAS_CONFIG.fallbackWidth, height: MAP_CANVAS_CONFIG.fallbackHeight });
  const tool = ref<MapToolMode>('view');
  const draftArea = ref<MapPoint[]>([]);
  const draftLine = ref<MapPoint[]>([]);
  const rectPoints = ref<MapPoint[]>([]);
  const edgeSourceId = ref<number | null>(null);
  const calibrationPoints = ref<MapPoint[]>([]);
  const rulerPoints = ref<MapPoint[]>([]);
  const activeTarget = ref<MapEditTarget | null>(null);
  const isPanning = ref(false);
  const pointerStart = ref<CanvasPoint | null>(null);
  const pointerLast = ref<CanvasPoint | null>(null);
  const isMoved = ref(false);
  let drag: DragState | null = null;
  let altPressed = false;

  const worldSize = computed<MapSize>(() => ({
    width: params.variant.value?.baseFile?.width ?? MAP_CANVAS_CONFIG.fallbackWidth,
    height: params.variant.value?.baseFile?.height ?? MAP_CANVAS_CONFIG.fallbackHeight,
  }));

  const placements = computed<Record<number, MapPoint>>(() => {
    const detail = params.map.value;
    const variant = params.variant.value;
    if (!detail || !variant) return {};

    return mapGeometryService.placementPoints(detail.nodes, variant.id);
  });

  const activeNodes = computed<MapNodeHitCandidate[]>(() =>
    Object.entries(placements.value).map(([nodeId, point]) => ({
      id: Number(nodeId),
      x: point[0],
      y: point[1],
    })),
  );

  const objects = computed<MapAreaHitCandidate[]>(() => areaCandidates('object'));

  const terrains = computed<MapAreaHitCandidate[]>(() => areaCandidates('terrain'));

  const activeLines = computed<MapLineHitCandidate[]>(() => {
    const detail = params.map.value;
    const variant = params.variant.value;
    if (!detail || !variant) return [];

    const lines: MapLineHitCandidate[] = [];
    for (const line of detail.lines) {
      if (line.variantId !== variant.id || !line.isPresent || line.points.length < 2) continue;
      lines.push({ id: line.id, points: line.points, widths: line.widths, handles: line.handles ?? [] });
    }

    return lines;
  });

  const activeEdges = computed<MapEdgeHitCandidate[]>(() => {
    const detail = params.map.value;
    const variant = params.variant.value;
    if (!detail || !variant) return [];

    const edges: MapEdgeHitCandidate[] = [];
    for (const edge of detail.edges) {
      if (edge.variantId !== variant.id) continue;
      const path = mapGeometryService.edgePoints(edge, placements.value);
      if (path.length >= 2) {
        edges.push({ id: edge.id, path });
      }
    }

    return edges;
  });

  const selectedArea = computed(() => {
    const selection = params.selected.value;
    if (selection?.kind !== 'area') return null;

    return params.map.value?.areas.find((area) => area.id === selection.id) ?? null;
  });

  const selectedLine = computed(() => {
    const selection = params.selected.value;
    if (selection?.kind !== 'line') return null;

    return params.map.value?.lines.find((line) => line.id === selection.id) ?? null;
  });

  const selectedEdge = computed(() => {
    const selection = params.selected.value;
    if (selection?.kind !== 'edge') return null;

    return params.map.value?.edges.find((edge) => edge.id === selection.id) ?? null;
  });

  const selectedAreaPath = computed<MapPoint[]>(() => {
    const variant = params.variant.value;
    const area = selectedArea.value;
    if (!variant || !area) return [];

    return area.shapes.find((shape) => shape.variantId === variant.id && shape.isPresent)?.path ?? [];
  });

  const selectedEdgePath = computed<MapPoint[]>(() => {
    const edge = selectedEdge.value;
    if (!edge) return [];

    return mapGeometryService.edgePoints(edge, placements.value);
  });

  const cursor = computed(() => {
    if (isPanning.value) return 'grabbing';
    if (tool.value === 'node') return 'copy';
    if (tool.value === 'area' || tool.value === 'rect' || tool.value === 'line' || tool.value === 'calibrate') {
      return 'crosshair';
    }
    if (tool.value === 'ruler') return 'crosshair';
    if (tool.value === 'edge') return 'pointer';
    if (
      activeTarget.value !== null ||
      params.selected.value?.kind === 'node' ||
      params.selected.value?.kind === 'area' ||
      params.selected.value?.kind === 'line'
    ) {
      return 'move';
    }

    return 'grab';
  });

  const hint = computed(() => {
    if (tool.value === 'view') {
      if (activeTarget.value !== null) {
        if (activeTarget.value.kind === 'line-handle-in' || activeTarget.value.kind === 'line-handle-out') {
          return 'Ручка Безье: тяните, Alt — разорвать симметрию';
        }
        if (activeTarget.value.kind === 'line-width') {
          return 'Ширина в точке: тяните от осевой линии';
        }

        return 'Вершина: тяните; Delete — удалить; двойной клик по сегменту — вставить';
      }

      if (params.selected.value === null) {
        return 'Клик — выбрать; пустое место — панорама; колесо — зум';
      }
      if (params.selected.value.kind === 'edge') {
        return 'Двойной клик по ребру — добавить точку пути; вершины тянутся';
      }
      if (params.selected.value.kind === 'line') {
        return 'Тяните ленту целиком; вершины, ручки Безье и оранжевые ручки ширины — на выделении';
      }

      return 'Тяните объект целиком; вершины — на выделении; двойной клик по сегменту — вставить';
    }

    if (tool.value === 'node') return 'Клик — новая точка; тяните точку — перенос';
    if (tool.value === 'edge') {
      return edgeSourceId.value === null
        ? 'Ребро: кликните первую точку'
        : 'Ребро: кликните вторую точку или Esc';
    }
    if (tool.value === 'area') {
      return draftArea.value.length === 0
        ? 'Полигон: кликните первую вершину'
        : 'Полигон: клики — вершины; Enter/двойной клик — замкнуть';
    }
    if (tool.value === 'rect') return 'Прямоугольник: кликните два противоположных угла';
    if (tool.value === 'line') {
      return draftLine.value.length === 0
        ? 'Лента: кликните первую точку осевой линии'
        : 'Лента: клики — точки; Enter/двойной клик — завершить';
    }
    if (tool.value === 'ruler') {
      return rulerPoints.value.length === 0
        ? 'Линейка: кликните точку A'
        : 'Линейка: кликните следующую точку; Esc — очистить';
    }

    return 'Калибровка: кликните две точки известного отрезка';
  });

  const rulerPixels = computed(() => mapGeometryService.polylineLength(rulerPoints.value));

  watch(
    () => params.variant.value?.id,
    () => {
      resetDrafts();
      fitToView();
    },
  );

  watch(
    () => params.variant.value?.baseFile?.width,
    () => fitToView(),
  );

  watch(
    () => params.variant.value?.baseFile?.height,
    () => fitToView(),
  );

  function areaCandidates(layer: 'terrain' | 'object'): MapAreaHitCandidate[] {
    const detail = params.map.value;
    const variant = params.variant.value;
    if (!detail || !variant) return [];

    const candidates: MapAreaHitCandidate[] = [];
    for (const area of detail.areas) {
      if (area.layer !== layer) continue;
      const shape = area.shapes.find((item) => item.variantId === variant.id && item.isPresent);
      if (shape) {
        candidates.push({ id: area.id, path: shape.path });
      }
    }

    return candidates;
  }

  function edgePath(edgeId: number): MapPoint[] {
    const edge = params.map.value?.edges.find((candidate) => candidate.id === edgeId);
    if (!edge) return [];

    return mapGeometryService.edgePoints(edge, placements.value);
  }

  function fitToView(): void {
    viewport.value = mapViewportService.fit(canvasSize.value, worldSize.value);
  }

  function resize(size: MapSize): void {
    const isFirst = canvasSize.value.width === MAP_CANVAS_CONFIG.fallbackWidth;
    canvasSize.value = size;
    if (isFirst || viewport.value.scale === 1) {
      fitToView();
    } else {
      viewport.value = mapViewportService.constrain(viewport.value, canvasSize.value, worldSize.value);
    }
  }

  function zoomBy(factor: number): void {
    const center: CanvasPoint = { x: canvasSize.value.width / 2, y: canvasSize.value.height / 2 };
    viewport.value = mapViewportService.zoomAt(viewport.value, center, factor);
  }

  function zoomAt(screenPoint: CanvasPoint, deltaY: number): void {
    const factor = deltaY < 0 ? 1.15 : 1 / 1.15;
    viewport.value = mapViewportService.zoomAt(viewport.value, screenPoint, factor);
  }

  function hitAt(point: MapPoint): MapHit | null {
    const tolerance = MAP_CANVAS_CONFIG.nodeHitRadius / viewport.value.scale;
    const edgeTolerance = MAP_CANVAS_CONFIG.edgeHitRadius / viewport.value.scale;
    const candidates: MapHitCandidates = {
      nodes: activeNodes.value,
      objects: objects.value,
      terrains: terrains.value,
      lines: activeLines.value,
      edges: activeEdges.value,
    };

    return mapHitTestService.hitTest(point, candidates, { node: tolerance, edge: edgeTolerance });
  }

  function editTargetAt(point: MapPoint): MapEditTarget | null {
    const scale = viewport.value.scale;
    const vertexTolerance = MAP_CANVAS_CONFIG.vertexHitRadius / scale;
    const handleTolerance = MAP_CANVAS_CONFIG.handleHitRadius / scale;
    const area = selectedArea.value;
    if (area) {
      const index = nearestVertex(selectedAreaPath.value, point, vertexTolerance);
      if (index !== null) return { kind: 'area-vertex', id: area.id, index };
    }

    const line = selectedLine.value;
    if (line) {
      const handled = editLineTargetAt(line, point, handleTolerance, vertexTolerance);
      if (handled) return handled;
    }

    const edge = selectedEdge.value;
    if (edge) {
      const path = selectedEdgePath.value;
      for (let index = 1; index < path.length - 1; index += 1) {
        if (mapGeometryService.distance(point, path[index]) <= vertexTolerance) {
          return { kind: 'edge-vertex', id: edge.id, index };
        }
      }
    }

    return null;
  }

  function editLineTargetAt(
    line: { id: number; points: MapPoint[]; widths: number[]; handles: { in: MapPoint; out: MapPoint }[] },
    point: MapPoint,
    handleTolerance: number,
    vertexTolerance: number,
  ): LineTarget | null {
    const handles = normalizeHandles(line);
    const active = activeTarget.value;
    const activeIndex =
      active !== null &&
      active.id === line.id &&
      (active.kind === 'line-vertex' || active.kind === 'line-handle-in' || active.kind === 'line-handle-out')
        ? active.index
        : null;

    if (activeIndex !== null) {
      const display = mapGeometryService.lineDisplayHandles(
        line.points,
        handles,
        activeIndex,
        MAP_CANVAS_CONFIG.handleStub / viewport.value.scale,
      );
      if (mapGeometryService.distance(point, display.in) <= handleTolerance) {
        return { kind: 'line-handle-in', id: line.id, index: activeIndex };
      }
      if (mapGeometryService.distance(point, display.out) <= handleTolerance) {
        return { kind: 'line-handle-out', id: line.id, index: activeIndex };
      }
    }

    for (let index = 0; index < line.points.length; index += 1) {
      if (index === activeIndex) continue;
      const handle = handles[index];
      const base = line.points[index];
      if (
        Math.hypot(handle.in[0], handle.in[1]) > 1 &&
        mapGeometryService.distance(point, [base[0] + handle.in[0], base[1] + handle.in[1]]) <= handleTolerance
      ) {
        return { kind: 'line-handle-in', id: line.id, index };
      }
      if (
        Math.hypot(handle.out[0], handle.out[1]) > 1 &&
        mapGeometryService.distance(point, [base[0] + handle.out[0], base[1] + handle.out[1]]) <= handleTolerance
      ) {
        return { kind: 'line-handle-out', id: line.id, index };
      }
    }

    for (let index = 0; index < line.points.length; index += 1) {
      const handlePoint = mapGeometryService.widthHandlePoint(
        line.points,
        handles,
        line.widths,
        index,
        MAP_CANVAS_CONFIG.handleOffset / viewport.value.scale,
      );
      if (mapGeometryService.distance(point, handlePoint) <= handleTolerance) {
        return { kind: 'line-width', id: line.id, index };
      }
    }

    const index = nearestVertex(line.points, point, vertexTolerance);

    return index === null ? null : { kind: 'line-vertex', id: line.id, index };
  }

  function onPointerDown(point: MapPoint, screen: CanvasPoint, button: number, alt = false): void {
    pointerStart.value = screen;
    pointerLast.value = screen;
    isMoved.value = false;
    altPressed = alt;

    if (button === 1 || button === 2) {
      isPanning.value = true;

      return;
    }

    if (tool.value === 'view') {
      const editable = params.map.value?.canEdit === true;
      const target = editable ? editTargetAt(point) : null;
      if (target !== null) {
        activeTarget.value = target;
        beginTargetDrag(target, point);

        return;
      }

      const hit = hitAt(point);
      if (hit?.kind === 'node') {
        params.selected.value = hit;
        activeTarget.value = null;
        if (editable) beginDrag({ kind: 'node', nodeId: hit.id });

        return;
      }

      if (hit?.kind === 'area' || hit?.kind === 'line') {
        params.selected.value = hit;
        activeTarget.value = null;
        if (editable) beginObjectDrag(hit, point);

        return;
      }

      if (hit?.kind === 'edge') {
        params.selected.value = hit;
        activeTarget.value = null;

        return;
      }

      isPanning.value = true;

      return;
    }

    if (tool.value === 'node') {
      const hit = hitAt(point);
      if (hit?.kind === 'node') {
        params.selected.value = hit;
        activeTarget.value = null;
        beginDrag({ kind: 'node', nodeId: hit.id });
      }
    }
  }

  function beginObjectDrag(hit: MapHit, point: MapPoint): void {
    const variant = params.variant.value;
    if (!variant) return;
    const start = snap(point);

    if (hit.kind === 'area') {
      const shape = params.map.value?.areas
        .find((area) => area.id === hit.id)
        ?.shapes.find((candidate) => candidate.variantId === variant.id && candidate.isPresent);
      if (!shape) return;
      beginDrag({ kind: 'area', areaId: hit.id, start, origin: copyPoints(shape.path) });

      return;
    }

    if (hit.kind === 'line') {
      const line = params.map.value?.lines.find((candidate) => candidate.id === hit.id);
      if (!line) return;
      beginDrag({ kind: 'line', lineId: hit.id, start, origin: copyPoints(line.points) });
    }
  }

  function beginTargetDrag(target: MapEditTarget, _point: MapPoint): void {
    const variant = params.variant.value;
    if (!variant) return;

    if (target.kind === 'area-vertex' || target.kind === 'line-vertex' || target.kind === 'edge-vertex') {
      beginDrag({ kind: 'vertex', target });
    } else if (target.kind === 'line-handle-in' || target.kind === 'line-handle-out') {
      beginDrag({ kind: 'handle', target });
    } else {
      beginDrag({ kind: 'width', target });
    }
  }

  function beginDrag(state: DragState): void {
    drag = state;
    params.draft.beginHistory();
  }

  function onPointerMove(point: MapPoint, screen: CanvasPoint, alt = altPressed): void {
    if (pointerStart.value && pointerLast.value) {
      const movedBy = Math.hypot(screen.x - pointerStart.value.x, screen.y - pointerStart.value.y);
      if (movedBy > MAP_CANVAS_CONFIG.dragThreshold) {
        isMoved.value = true;
      }
    }

    if (isPanning.value && pointerLast.value) {
      viewport.value = mapViewportService.constrain(
        mapViewportService.pan(viewport.value, screen.x - pointerLast.value.x, screen.y - pointerLast.value.y),
        canvasSize.value,
        worldSize.value,
      );
    } else if (drag !== null) {
      applyDrag(point, alt);
    }

    pointerLast.value = screen;
  }

  function applyDrag(point: MapPoint, alt: boolean): void {
    const variant = params.variant.value;
    if (!variant || drag === null) return;
    const snapped = snap(point);

    if (drag.kind === 'node') {
      params.draft.moveNode(drag.nodeId, variant.id, snapped);

      return;
    }

    if (drag.kind === 'area') {
      const dragged = snapFree(point);
      const delta = clampTranslation(drag.origin, [dragged[0] - drag.start[0], dragged[1] - drag.start[1]]);
      params.draft.setAreaPath(
        drag.areaId,
        variant.id,
        drag.origin.map((item) => [item[0] + delta[0], item[1] + delta[1]] as MapPoint),
      );

      return;
    }

    if (drag.kind === 'line') {
      const dragged = snapFree(point);
      const delta = clampTranslation(drag.origin, [dragged[0] - drag.start[0], dragged[1] - drag.start[1]]);
      params.draft.setLinePoints(
        drag.lineId,
        drag.origin.map((item) => [item[0] + delta[0], item[1] + delta[1]] as MapPoint),
      );

      return;
    }

    if (drag.kind === 'vertex') {
      if (drag.target.kind === 'area-vertex') {
        params.draft.setAreaVertex(drag.target.id, variant.id, drag.target.index, snapped);
      } else if (drag.target.kind === 'line-vertex') {
        params.draft.setLineVertex(drag.target.id, drag.target.index, snapped);
      } else {
        params.draft.setEdgeVertex(drag.target.id, drag.target.index, snapped);
      }

      return;
    }

    if (drag.kind === 'handle') {
      const target = drag.target;
      const line = params.map.value?.lines.find((candidate) => candidate.id === target.id);
      if (!line) return;
      const index = target.index;
      const base = line.points[index];
      const free = snapFree(point);
      const offset: MapPoint = [free[0] - base[0], free[1] - base[1]];
      const side = target.kind === 'line-handle-in' ? 'in' : 'out';
      params.draft.setLineHandle(line.id, index, side, offset);
      if (!alt) {
        params.draft.setLineHandle(line.id, index, side === 'in' ? 'out' : 'in', [-offset[0], -offset[1]]);
      }

      return;
    }

    const target = drag.target;
    const line = params.map.value?.lines.find((candidate) => candidate.id === target.id);
    if (!line) return;
    const base = line.points[target.index];
    const offset = MAP_CANVAS_CONFIG.handleOffset / viewport.value.scale;
    const free = snapFree(point);
    const width = Math.max(1, Math.round((mapGeometryService.distance(base, free) - offset) * 2));
    params.draft.setLineWidth(line.id, target.index, width);
  }

  function onPointerUp(point: MapPoint): void {
    if (drag !== null) {
      const current = drag;
      const moved = isMoved.value;
      if (moved) {
        params.draft.endHistory();
      } else {
        params.draft.cancelHistory();
      }
      drag = null;
      if (current.kind === 'node' && !moved) {
        params.onOpenNode(current.nodeId);
      }
      resetPointer();

      return;
    }

    if (!isMoved.value) {
      handleClick(point);
    }

    resetPointer();
  }

  function onDoubleClick(point: MapPoint): void {
    if (params.map.value?.canEdit !== true) return;
    if (tool.value === 'view' && insertVertexAt(point)) return;
    finishDraft();
  }

  function insertVertexAt(point: MapPoint): boolean {
    const variant = params.variant.value;
    if (!variant) return false;

    const area = selectedArea.value;
    if (area) {
      const path = selectedAreaPath.value;
      if (path.length < 3) return false;
      const segment = nearestSegment(path, point, true);
      if (segment === null || segment.distance > MAP_CANVAS_CONFIG.vertexHitRadius / viewport.value.scale) {
        return false;
      }
      params.draft.insertAreaVertex(area.id, variant.id, segment.index, snap(point));

      return true;
    }

    const line = selectedLine.value;
    if (line) {
      return insertLineVertexAt(line, point);
    }

    const edge = selectedEdge.value;
    if (edge) {
      const path = selectedEdgePath.value;
      if (path.length < 2) return false;
      const segment = nearestSegment(path, point, false);
      if (segment === null || segment.distance > MAP_CANVAS_CONFIG.vertexHitRadius / viewport.value.scale) {
        return false;
      }
      params.draft.beginHistory();
      if (edge.path.length < 2) {
        params.draft.setEdgePath(edge.id, path);
      }
      params.draft.insertEdgeVertex(edge.id, segment.index, snap(point));
      params.draft.endHistory();

      return true;
    }

    return false;
  }

  function insertLineVertexAt(
    line: { id: number; points: MapPoint[]; widths: number[]; handles: { in: MapPoint; out: MapPoint }[] },
    point: MapPoint,
  ): boolean {
    if (line.points.length < 2) return false;
    const handles = normalizeHandles(line);
    let bestSegment = 0;
    let bestT = 0;
    let bestDistance = Number.POSITIVE_INFINITY;
    for (let index = 1; index < line.points.length; index += 1) {
      const start = line.points[index - 1];
      const end = line.points[index];
      const controlOne: MapPoint = [start[0] + handles[index - 1].out[0], start[1] + handles[index - 1].out[1]];
      const controlTwo: MapPoint = [end[0] + handles[index].in[0], end[1] + handles[index].in[1]];
      for (let step = 0; step <= 24; step += 1) {
        const t = step / 24;
        const sample = mapGeometryService.cubicPoint(start, controlOne, controlTwo, end, t);
        const distance = mapGeometryService.distance(point, sample);
        if (distance < bestDistance) {
          bestDistance = distance;
          bestSegment = index - 1;
          bestT = t;
        }
      }
    }

    if (bestDistance > MAP_CANVAS_CONFIG.vertexHitRadius / viewport.value.scale) {
      return false;
    }

    const start = line.points[bestSegment];
    const end = line.points[bestSegment + 1];
    const controlOne: MapPoint = [start[0] + handles[bestSegment].out[0], start[1] + handles[bestSegment].out[1]];
    const controlTwo: MapPoint = [end[0] + handles[bestSegment + 1].in[0], end[1] + handles[bestSegment + 1].in[1]];
    const split = mapGeometryService.splitCubic(start, controlOne, controlTwo, end, bestT);
    const newPoint = clampPoint(split.left[3]);
    const newHandle = {
      in: [split.left[2][0] - newPoint[0], split.left[2][1] - newPoint[1]] as MapPoint,
      out: [split.right[1][0] - newPoint[0], split.right[1][1] - newPoint[1]] as MapPoint,
    };
    const width = line.widths[bestSegment] + (line.widths[bestSegment + 1] - line.widths[bestSegment]) * bestT;

    params.draft.beginHistory();
    params.draft.setLineHandle(line.id, bestSegment, 'out', [
      split.left[1][0] - start[0],
      split.left[1][1] - start[1],
    ]);
    params.draft.setLineHandle(line.id, bestSegment + 1, 'in', [
      split.right[2][0] - end[0],
      split.right[2][1] - end[1],
    ]);
    params.draft.insertLineVertex(line.id, bestSegment, newPoint, Math.max(1, Math.round(width)), newHandle);
    params.draft.endHistory();

    return true;
  }

  function handleClick(point: MapPoint): void {
    const hit = hitAt(point);
    if (tool.value === 'view') {
      params.selected.value = hit;
      activeTarget.value = null;

      return;
    }

    if (tool.value === 'node') {
      if (hit?.kind === 'node') {
        params.onOpenNode(hit.id);
      } else {
        params.onCreateNode(snap(point));
      }

      return;
    }

    if (tool.value === 'edge') {
      handleEdgeClick(hit);
      params.selected.value = hit;

      return;
    }

    if (tool.value === 'area') {
      draftArea.value = [...draftArea.value, snap(point)];
      params.selected.value = null;
      activeTarget.value = null;

      return;
    }

    if (tool.value === 'line') {
      draftLine.value = [...draftLine.value, snap(point)];
      params.selected.value = null;
      activeTarget.value = null;

      return;
    }

    if (tool.value === 'rect') {
      handleRectClick(point);

      return;
    }

    if (tool.value === 'ruler') {
      rulerPoints.value = [...rulerPoints.value, snap(point)];

      return;
    }

    if (tool.value === 'calibrate') {
      calibrationPoints.value = [...calibrationPoints.value, snap(point)];
      if (calibrationPoints.value.length === 2) {
        params.onCalibrate(calibrationPoints.value);
        calibrationPoints.value = [];
      }
    }
  }

  function handleEdgeClick(hit: MapHit | null): void {
    if (hit?.kind !== 'node') {
      edgeSourceId.value = null;

      return;
    }

    if (edgeSourceId.value === null) {
      edgeSourceId.value = hit.id;

      return;
    }

    if (edgeSourceId.value !== hit.id) {
      params.onCreateEdge(edgeSourceId.value, hit.id);
    }

    edgeSourceId.value = null;
  }

  function handleRectClick(point: MapPoint): void {
    if (rectPoints.value.length === 0) {
      rectPoints.value = [snap(point)];

      return;
    }

    const start = rectPoints.value[0];
    const end = snap(point);
    rectPoints.value = [];
    if (start[0] === end[0] || start[1] === end[1]) return;
    params.onCreateRect([
      [start[0], start[1]],
      [end[0], start[1]],
      [end[0], end[1]],
      [start[0], end[1]],
    ]);
  }

  function finishDraft(): void {
    if (tool.value === 'area') {
      finishArea();
    }
    if (tool.value === 'line') {
      finishLine();
    }
  }

  function finishArea(): void {
    if (draftArea.value.length >= 3) {
      const path = dedupe(draftArea.value);
      if (path.length >= 3) {
        params.onCreateArea(path);
      }
    }

    draftArea.value = [];
  }

  function finishLine(): void {
    if (draftLine.value.length >= 2) {
      const points = dedupe(draftLine.value);
      if (points.length >= 2) {
        params.onCreateLine(points);
      }
    }

    draftLine.value = [];
  }

  function startBranch(point: MapPoint): void {
    draftArea.value = [];
    draftLine.value = [snap(point)];
    rectPoints.value = [];
    edgeSourceId.value = null;
    activeTarget.value = null;
    tool.value = 'line';
  }

  function cancelDraft(): void {
    resetDrafts();
    isPanning.value = false;
    drag = null;
    params.draft.cancelHistory();
    resetPointer();
  }

  function resetDrafts(): void {
    draftArea.value = [];
    draftLine.value = [];
    rectPoints.value = [];
    edgeSourceId.value = null;
    calibrationPoints.value = [];
    rulerPoints.value = [];
    activeTarget.value = null;
  }

  function clearRuler(): void {
    rulerPoints.value = [];
  }

  function clearTarget(): void {
    activeTarget.value = null;
  }

  function focusLineVertex(lineId: number, index: number): void {
    activeTarget.value = { kind: 'line-vertex', id: lineId, index };
  }

  function smoothLine(): boolean {
    const line = selectedLine.value;
    if (!line || line.points.length < 2) return false;
    params.draft.smoothLine(line.id);

    return true;
  }

  function cornerLine(): boolean {
    const line = selectedLine.value;
    if (!line || line.points.length < 2) return false;
    params.draft.beginHistory();
    line.points.forEach((_point, index) => {
      params.draft.setLineHandle(line.id, index, 'in', [0, 0]);
      params.draft.setLineHandle(line.id, index, 'out', [0, 0]);
    });
    params.draft.endHistory();

    return true;
  }

  function deleteActive(): boolean {
    const target = activeTarget.value;
    const variant = params.variant.value;
    if (target === null || !variant) return false;

    if (target.kind === 'area-vertex') {
      params.draft.removeAreaVertex(target.id, variant.id, target.index);
    } else if (target.kind === 'line-vertex') {
      params.draft.removeLineVertex(target.id, target.index);
    } else if (target.kind === 'edge-vertex') {
      params.draft.removeEdgeVertex(target.id, target.index);
    } else if (target.kind === 'line-handle-in' || target.kind === 'line-handle-out') {
      params.draft.setLineHandle(target.id, target.index, target.kind === 'line-handle-in' ? 'in' : 'out', [0, 0]);
    } else {
      params.draft.setLineWidth(target.id, target.index, 1);
    }
    activeTarget.value = null;

    return true;
  }

  function setTool(nextTool: MapToolMode): void {
    tool.value = nextTool;
    cancelDraft();
  }

  function resetPointer(): void {
    pointerStart.value = null;
    pointerLast.value = null;
    isPanning.value = false;
    isMoved.value = false;
  }

  function snap(point: MapPoint): MapPoint {
    return clampPoint([Math.round(point[0]), Math.round(point[1])]);
  }

  /** Округление без прижатия к миру: ручки Безье и ширина могут выходить за край. */
  function snapFree(point: MapPoint): MapPoint {
    return [Math.round(point[0]), Math.round(point[1])];
  }

  /** Прижимает точку к прямоугольнику подложки. */
  function clampPoint(point: MapPoint): MapPoint {
    const size = worldSize.value;

    return [clampCoordinate(point[0], size.width), clampCoordinate(point[1], size.height)];
  }

  function clampCoordinate(value: number, max: number): number {
    if (!Number.isFinite(value)) return 0;

    return Math.min(max, Math.max(0, Math.round(value)));
  }

  /** Сдвиг объекта целиком: останавливается, когда любая его точка касается края. */
  function clampTranslation(origin: MapPoint[], delta: MapPoint): MapPoint {
    const size = worldSize.value;
    const xs = origin.map((point) => point[0]);
    const ys = origin.map((point) => point[1]);
    const minDx = -Math.min(...xs);
    const maxDx = size.width - Math.max(...xs);
    const minDy = -Math.min(...ys);
    const maxDy = size.height - Math.max(...ys);

    return [
      minDx <= maxDx ? Math.min(Math.max(delta[0], minDx), maxDx) : 0,
      minDy <= maxDy ? Math.min(Math.max(delta[1], minDy), maxDy) : 0,
    ];
  }

  function dedupe(points: MapPoint[]): MapPoint[] {
    return points.filter(
      (point, index, list) => index === 0 || point[0] !== list[index - 1][0] || point[1] !== list[index - 1][1],
    );
  }

  function copyPoints(points: MapPoint[]): MapPoint[] {
    return points.map((item) => [item[0], item[1]] as MapPoint);
  }

  function nearestVertex(points: MapPoint[], point: MapPoint, tolerance: number): number | null {
    let nearest: number | null = null;
    let distance = tolerance;
    for (let index = 0; index < points.length; index += 1) {
      const current = mapGeometryService.distance(point, points[index]);
      if (current <= distance) {
        nearest = index;
        distance = current;
      }
    }

    return nearest;
  }

  function nearestSegment(
    points: MapPoint[],
    point: MapPoint,
    closed: boolean,
  ): { index: number; distance: number } | null {
    if (points.length < 2) return null;
    let nearest: { index: number; distance: number } | null = null;
    const count = closed ? points.length : points.length - 1;
    for (let index = 0; index < count; index += 1) {
      const end = points[(index + 1) % points.length];
      const current = mapGeometryService.distanceToSegment(point, points[index], end);
      if (nearest === null || current < nearest.distance) {
        nearest = { index, distance: current };
      }
    }

    return nearest;
  }

  function normalizeHandles(line: {
    points: MapPoint[];
    handles: { in: MapPoint; out: MapPoint }[];
  }): { in: MapPoint; out: MapPoint }[] {
    while (line.handles.length < line.points.length) line.handles.push({ in: [0, 0], out: [0, 0] });

    return line.handles;
  }

  return {
    viewport,
    canvasSize,
    worldSize,
    tool,
    draftArea,
    draftLine,
    rectPoints,
    edgeSourceId,
    calibrationPoints,
    rulerPoints,
    rulerPixels,
    activeTarget,
    isPanning,
    cursor,
    hint,
    activeNodes,
    objects,
    terrains,
    activeLines,
    activeEdges,
    placements,
    selectedAreaPath,
    selectedEdgePath,
    edgePath,
    fitToView,
    resize,
    zoomBy,
    zoomAt,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onDoubleClick,
    handleClick,
    finishDraft,
    finishArea,
    finishLine,
    startBranch,
    cancelDraft,
    clearRuler,
    clearTarget,
    focusLineVertex,
    smoothLine,
    cornerLine,
    deleteActive,
    setTool,
    snap,
  };
}
