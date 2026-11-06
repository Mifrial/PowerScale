<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import type { CanvasPoint } from '@/modules/Roleplay/Map/Dto/CanvasPoint';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapEditTarget } from '@/modules/Roleplay/Map/Dto/MapEditTarget';
import type { MapAreaShape } from '@/modules/Roleplay/Map/Dto/MapAreaShape';
import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapHit } from '@/modules/Roleplay/Map/Dto/MapHit';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapSize } from '@/modules/Roleplay/Map/Dto/MapSize';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapViewport } from '@/modules/Roleplay/Map/Dto/MapViewport';
import type { MapToolMode } from '@/modules/Roleplay/Map/Enum/MapToolMode';
import { MAP_CANVAS_CONFIG } from '@/modules/Roleplay/Map/Constant/Canvas/MAP_CANVAS_CONFIG';
import { mapGeometryService } from '@/modules/Roleplay/Map/init';
import { getFileApi } from '@/modules/Core/File/init';

const props = defineProps<{
  map: MapDetail | null;
  variant: MapVariant | null;
  viewport: MapViewport;
  selected: MapHit | null;
  selectedTarget: MapEditTarget | null;
  route: MapRoute | null;
  draftArea: MapPoint[];
  draftLine: MapPoint[];
  rectPoints: MapPoint[];
  calibrationPoints: MapPoint[];
  rulerPoints: MapPoint[];
  rulerLabel: string | null;
  tool: MapToolMode;
  cursor: string;
}>();

const emit = defineEmits<{
  'pointer-down': [point: MapPoint, screen: CanvasPoint, button: number, alt: boolean];
  'pointer-move': [point: MapPoint, screen: CanvasPoint, alt: boolean, shift: boolean];
  'pointer-up': [point: MapPoint, screen: CanvasPoint];
  'pointer-dblclick': [point: MapPoint];
  wheel: [screen: CanvasPoint, deltaY: number];
  resize: [size: MapSize];
  'base-size': [size: MapSize];
}>();

const wrapper = ref<HTMLDivElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const baseImage = ref<HTMLImageElement | null>(null);
const hoverPoint = ref<MapPoint | null>(null);
let resizeObserver: ResizeObserver | null = null;
let frame = 0;
let lastSize: MapSize = { width: 0, height: 0 };

const worldWidth = (): number => props.variant?.baseFile?.width ?? MAP_CANVAS_CONFIG.fallbackWidth;

const worldHeight = (): number => props.variant?.baseFile?.height ?? MAP_CANVAS_CONFIG.fallbackHeight;

const toScreen = (point: MapPoint): CanvasPoint => ({
  x: point[0] * props.viewport.scale + props.viewport.offsetX,
  y: point[1] * props.viewport.scale + props.viewport.offsetY,
});

const toWorld = (screen: CanvasPoint): MapPoint => [
  (screen.x - props.viewport.offsetX) / props.viewport.scale,
  (screen.y - props.viewport.offsetY) / props.viewport.scale,
];

const areaNodeIds = (): Set<number> => {
  const ids = new Set<number>();
  for (const area of props.map?.areas ?? []) {
    if (area.nodeId !== null) ids.add(area.nodeId);
  }

  return ids;
};

const sortedAreas = (layer: 'surface' | 'object') =>
  (props.map?.areas ?? [])
    .filter((area) => area.layer === layer)
    .slice()
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);

const sortedLines = () => {
  const variant = props.variant;
  if (!variant) return [];

  return (props.map?.lines ?? [])
    .filter((line) => line.variantId === variant.id && line.isPresent && line.points.length >= 2)
    .slice()
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
};

const placements = (): Record<number, MapPoint> => {
  if (!props.map || !props.variant) return {};

  return mapGeometryService.placementPoints(props.map.nodes, props.variant.id);
};

const areaShapeOf = (areaId: number): MapAreaShape | null => {
  const variant = props.variant;
  const area = props.map?.areas.find((candidate) => candidate.id === areaId);
  if (!variant || !area) return null;

  return area.shapes.find((candidate) => candidate.variantId === variant.id && candidate.isPresent) ?? null;
};

const flatShapeOf = (areaId: number): MapPoint[] | null => {
  const shape = areaShapeOf(areaId);

  return shape ? mapGeometryService.flattenContour(shape.path, shape.handles ?? []) : null;
};

const selectedArea = (): { id: number; path: MapPoint[]; handles: MapHandle[] } | null => {
  if (props.selected?.kind !== 'area') return null;
  const shape = areaShapeOf(props.selected.id);
  if (!shape) return null;

  return { id: props.selected.id, path: shape.path, handles: shape.handles ?? [] };
};

const selectedLine = (): MapLine | null => {
  if (props.selected?.kind !== 'line') return null;

  return props.map?.lines.find((line) => line.id === props.selected?.id) ?? null;
};

function scheduleDraw(): void {
  if (frame !== 0) return;
  frame = requestAnimationFrame(() => {
    frame = 0;
    draw();
  });
}

function draw(): void {
  const element = canvas.value;
  if (!element) return;
  const context = element.getContext('2d');
  if (!context) return;
  const width = lastSize.width;
  const height = lastSize.height;
  if (width === 0 || height === 0) return;
  const dpr = window.devicePixelRatio || 1;

  context.setTransform(dpr, 0, 0, dpr, 0, 0);
  context.clearRect(0, 0, width, height);
  context.fillStyle = '#ece7db';
  context.fillRect(0, 0, width, height);

  context.save();
  context.translate(props.viewport.offsetX, props.viewport.offsetY);
  context.scale(props.viewport.scale, props.viewport.scale);

  if (baseImage.value) {
    context.drawImage(baseImage.value, 0, 0, worldWidth(), worldHeight());
  } else {
    context.fillStyle = '#e3dccb';
    context.fillRect(0, 0, worldWidth(), worldHeight());
  }

  drawGrid(context);
  drawAreas(context, 'surface');
  drawLines(context);
  drawAreas(context, 'object');
  drawRoute(context);
  context.restore();

  drawAreaLabels(context);
  drawNodes(context);
  drawEditorHandles(context);
  drawDraft(context);
}

function drawGrid(context: CanvasRenderingContext2D): void {
  const step = MAP_CANVAS_CONFIG.gridStep;
  context.strokeStyle = 'rgba(60, 60, 60, 0.08)';
  context.lineWidth = 1 / props.viewport.scale;
  context.beginPath();
  for (let x = 0; x <= worldWidth(); x += step) {
    context.moveTo(x, 0);
    context.lineTo(x, worldHeight());
  }
  for (let y = 0; y <= worldHeight(); y += step) {
    context.moveTo(0, y);
    context.lineTo(worldWidth(), y);
  }
  context.stroke();
}

function drawAreas(context: CanvasRenderingContext2D, layer: 'surface' | 'object'): void {
  if (!props.map) return;
  for (const area of sortedAreas(layer)) {
    const path = flatShapeOf(area.id);
    if (!path || path.length < 3) continue;
    const color = area.color;
    context.beginPath();
    path.forEach((point, index) => {
      if (index === 0) {
        context.moveTo(point[0], point[1]);
      } else {
        context.lineTo(point[0], point[1]);
      }
    });
    context.closePath();
    context.globalAlpha = MAP_CANVAS_CONFIG.areaFillAlpha;
    context.fillStyle = color;
    context.fill();
    context.globalAlpha = 1;
    context.strokeStyle = color;
    context.lineWidth = MAP_CANVAS_CONFIG.areaStrokeWidth / props.viewport.scale;
    context.stroke();
    if (props.selected?.kind === 'area' && props.selected.id === area.id) {
      context.strokeStyle = '#1e88e5';
      context.lineWidth = (MAP_CANVAS_CONFIG.areaStrokeWidth + 2) / props.viewport.scale;
      context.stroke();
    }
  }
}

function drawLines(context: CanvasRenderingContext2D): void {
  if (!props.map || !props.variant) return;
  for (const line of sortedLines()) {
    drawLineSegments(context, line);
    if (props.selected?.kind === 'line' && props.selected.id === line.id) {
      const flat = mapGeometryService.flattenLine(line.points, line.handles ?? [], line.widths);
      drawPolyline(context, flat.points, '#1e88e5', 2 / props.viewport.scale);
    }
  }
}

function drawLineSegments(context: CanvasRenderingContext2D, line: MapLine): void {
  const handles = line.handles ?? [];
  for (let index = 1; index < line.points.length; index += 1) {
    const points: MapPoint[] = [line.points[index - 1], line.points[index]];
    const widths = [line.widths[index - 1] ?? 1, line.widths[index] ?? 1];
    const segmentHandles = [
      handles[index - 1] ?? { in: [0, 0] as MapPoint, out: [0, 0] as MapPoint },
      handles[index] ?? { in: [0, 0] as MapPoint, out: [0, 0] as MapPoint },
    ];
    const flat = mapGeometryService.flattenLine(points, segmentHandles, widths);
    const segment = line.segments[index - 1];
    drawRibbon(context, flat.points, flat.widths, segment?.color ?? line.color);
  }
}

function drawRibbon(context: CanvasRenderingContext2D, points: MapPoint[], widths: number[], color: string): void {
  const { left, right } = ribbonEdges(points, widths);

  context.beginPath();
  left.forEach((point, index) => {
    if (index === 0) {
      context.moveTo(point[0], point[1]);
    } else {
      context.lineTo(point[0], point[1]);
    }
  });
  for (let index = right.length - 1; index >= 0; index -= 1) {
    context.lineTo(right[index][0], right[index][1]);
  }
  context.closePath();
  context.globalAlpha = 0.55;
  context.fillStyle = color;
  context.fill();
  context.globalAlpha = 1;
  context.strokeStyle = color;
  context.lineWidth = MAP_CANVAS_CONFIG.lineStrokeWidth / props.viewport.scale;
  context.stroke();
}

/** Края ленты с ограничением митры: острые углы не дают игл и петель. */
function ribbonEdges(points: MapPoint[], widths: number[]): { left: MapPoint[]; right: MapPoint[] } {
  const left: MapPoint[] = [];
  const right: MapPoint[] = [];
  const last = points.length - 1;
  points.forEach((point, index) => {
    const halfWidth = Math.max(1, widths[index] ?? 1) / 2;
    let offset: MapPoint;
    if (index === 0) {
      offset = segmentNormal(point, points[1] ?? point);
    } else if (index === last) {
      offset = segmentNormal(points[index - 1], point);
    } else {
      const before = segmentNormal(points[index - 1], point);
      const after = segmentNormal(point, points[index + 1]);
      const sum: MapPoint = [before[0] + after[0], before[1] + after[1]];
      const length = Math.hypot(sum[0], sum[1]);
      if (length < 1e-6) {
        offset = after;
      } else {
        const miter: MapPoint = [sum[0] / length, sum[1] / length];
        const dot = miter[0] * after[0] + miter[1] * after[1];
        const scale = dot > 0.2 ? Math.min(2.5, 1 / dot) : 1;
        offset = [miter[0] * scale, miter[1] * scale];
      }
    }
    left.push([point[0] + offset[0] * halfWidth, point[1] + offset[1] * halfWidth]);
    right.push([point[0] - offset[0] * halfWidth, point[1] - offset[1] * halfWidth]);
  });

  return { left, right };
}

function segmentNormal(start: MapPoint, end: MapPoint): MapPoint {
  const deltaX = end[0] - start[0];
  const deltaY = end[1] - start[1];
  const length = Math.hypot(deltaX, deltaY);
  if (length === 0) return [0, 0];

  return [-deltaY / length, deltaX / length];
}

function drawPolyline(context: CanvasRenderingContext2D, points: MapPoint[], color: string, width: number): void {
  if (points.length < 2) return;
  context.beginPath();
  points.forEach((point, index) => {
    if (index === 0) {
      context.moveTo(point[0], point[1]);
    } else {
      context.lineTo(point[0], point[1]);
    }
  });
  context.strokeStyle = color;
  context.lineWidth = width;
  context.lineCap = 'round';
  context.lineJoin = 'round';
  context.stroke();
}

function drawRoute(context: CanvasRenderingContext2D): void {
  if (!props.route || props.route.points.length < 2) return;
  const simplified = mapGeometryService.simplifyPath(props.route.points, routeTolerance(props.route.points));
  const smooth = mapGeometryService.smoothPath(simplified);
  drawPolyline(
    context,
    smooth,
    '#d81b60',
    MAP_CANVAS_CONFIG.routeWidth / props.viewport.scale,
  );
}

/** Допуск упрощения по медианному шагу точек сетки. */
function routeTolerance(points: MapPoint[]): number {
  const steps: number[] = [];
  for (let index = 1; index < points.length; index += 1) {
    steps.push(mapGeometryService.distance(points[index - 1], points[index]));
  }
  steps.sort((left, right) => left - right);
  const median = steps[Math.floor(steps.length / 2)] ?? 2;

  return Math.max(1.5, median * 0.75);
}

function drawAreaLabels(context: CanvasRenderingContext2D): void {
  if (!props.map) return;
  context.font = '12px Roboto, sans-serif';
  context.textAlign = 'center';
  context.textBaseline = 'middle';
  context.lineWidth = 3;
  context.strokeStyle = 'rgba(255, 255, 255, 0.85)';
  context.fillStyle = '#263238';
  for (const area of props.map.areas) {
    const label = area.label.trim();
    if (label === '') continue;
    const path = flatShapeOf(area.id);
    if (!path || path.length < 3) continue;
    const point = toScreen(mapGeometryService.polygonCentroid(path));
    context.strokeText(label, point.x, point.y);
    context.fillText(label, point.x, point.y);
  }
  context.textAlign = 'start';
}

function drawNodes(context: CanvasRenderingContext2D): void {
  if (!props.map || !props.variant) return;
  const points = placements();
  const linked = areaNodeIds();
  for (const node of props.map.nodes) {
    const point = points[node.id];
    if (!point) continue;
    if (linked.has(node.id)) continue;
    const screen = toScreen(point);
    const isSelected = props.selected?.kind === 'node' && props.selected.id === node.id;
    context.beginPath();
    context.arc(screen.x, screen.y, MAP_CANVAS_CONFIG.nodeRadius, 0, Math.PI * 2);
    context.fillStyle = '#ffffff';
    context.fill();
    context.lineWidth = isSelected ? 3 : 2;
    context.strokeStyle = isSelected ? '#1e88e5' : '#455a64';
    context.stroke();
    const placement = node.placements.find(
      (candidate) => candidate.variantId === props.variant?.id && candidate.isPresent,
    );
    const label = placement?.labelOverride || node.name;
    if (label) {
      context.font = '12px Roboto, sans-serif';
      context.fillStyle = '#263238';
      context.textBaseline = 'middle';
      context.fillText(label, screen.x + MAP_CANVAS_CONFIG.nodeRadius + 4, screen.y);
    }
  }
}

function drawEditorHandles(context: CanvasRenderingContext2D): void {
  if (props.map?.canEdit !== true) return;
  const area = selectedArea();
  if (area) {
    const target = props.selectedTarget;
    const activeVertexIndex =
      target !== null &&
      (target.kind === 'area-vertex' || target.kind === 'area-handle-in' || target.kind === 'area-handle-out') &&
      target.id === area.id
        ? target.index
        : null;

    area.path.forEach((point, index) => {
      const handle = area.handles[index] ?? { in: [0, 0] as MapPoint, out: [0, 0] as MapPoint };
      if (index === activeVertexIndex) {
        const display = mapGeometryService.areaDisplayHandles(
          area.path,
          area.handles,
          index,
          MAP_CANVAS_CONFIG.handleStub / props.viewport.scale,
        );
        drawBezierHandle(context, point, display.in, '#8e24aa', true, display.inIsStub);
        drawBezierHandle(context, point, display.out, '#8e24aa', true, display.outIsStub);
      } else {
        if (Math.hypot(handle.in[0], handle.in[1]) > 1) {
          drawBezierHandle(context, point, [point[0] + handle.in[0], point[1] + handle.in[1]], '#b39ddb', false, false);
        }
        if (Math.hypot(handle.out[0], handle.out[1]) > 1) {
          drawBezierHandle(context, point, [point[0] + handle.out[0], point[1] + handle.out[1]], '#b39ddb', false, false);
        }
      }
    });

    area.path.forEach((point, index) => {
      drawVertex(context, point, isActive('area-vertex', area.id, index));
    });
  }

  const line = selectedLine();
  if (line) {
    const target = props.selectedTarget;
    const handles = line.handles ?? [];
    const activeVertexIndex =
      target !== null &&
      (target.kind === 'line-vertex' || target.kind === 'line-handle-in' || target.kind === 'line-handle-out') &&
      target.id === line.id
        ? target.index
        : null;

    line.points.forEach((point, index) => {
      const widthPoint = mapGeometryService.widthHandlePoint(
        line.points,
        handles,
        line.widths,
        index,
        MAP_CANVAS_CONFIG.handleOffset / props.viewport.scale,
      );
      const active = target?.kind === 'line-width' && target.id === line.id && target.index === index;
      drawWidthHandle(context, point, widthPoint, active);
    });

    line.points.forEach((point, index) => {
      const handle = handles[index] ?? { in: [0, 0] as MapPoint, out: [0, 0] as MapPoint };
      if (index === activeVertexIndex) {
        const display = mapGeometryService.lineDisplayHandles(
          line.points,
          handles,
          index,
          MAP_CANVAS_CONFIG.handleStub / props.viewport.scale,
        );
        drawBezierHandle(context, point, display.in, '#8e24aa', true, display.inIsStub);
        drawBezierHandle(context, point, display.out, '#8e24aa', true, display.outIsStub);
      } else {
        if (Math.hypot(handle.in[0], handle.in[1]) > 1) {
          drawBezierHandle(context, point, [point[0] + handle.in[0], point[1] + handle.in[1]], '#b39ddb', false, false);
        }
        if (Math.hypot(handle.out[0], handle.out[1]) > 1) {
          drawBezierHandle(context, point, [point[0] + handle.out[0], point[1] + handle.out[1]], '#b39ddb', false, false);
        }
      }
    });

    line.points.forEach((point, index) => {
      drawVertex(
        context,
        point,
        target?.kind === 'line-vertex' && target.id === line.id && target.index === index,
      );
    });
  }

}

function isActive(kind: MapEditTarget['kind'], id: number, index: number): boolean {
  const target = props.selectedTarget;

  return target?.kind === kind && target.id === id && target.index === index;
}

function drawVertex(context: CanvasRenderingContext2D, point: MapPoint, active: boolean): void {
  const screen = toScreen(point);
  const size = active ? MAP_CANVAS_CONFIG.vertexRadius + 2 : MAP_CANVAS_CONFIG.vertexRadius;
  context.beginPath();
  context.rect(screen.x - size, screen.y - size, size * 2, size * 2);
  context.fillStyle = active ? '#1e88e5' : '#ffffff';
  context.fill();
  context.strokeStyle = '#1e88e5';
  context.lineWidth = 1.5;
  context.stroke();
}

function drawBezierHandle(
  context: CanvasRenderingContext2D,
  base: MapPoint,
  target: MapPoint,
  color: string,
  active: boolean,
  stub: boolean,
): void {
  const center = toScreen(base);
  const handle = toScreen(target);
  context.beginPath();
  context.moveTo(center.x, center.y);
  context.lineTo(handle.x, handle.y);
  context.strokeStyle = color;
  context.lineWidth = active ? 1.5 : 1;
  context.setLineDash(stub ? [2, 3] : [4, 3]);
  context.stroke();
  context.setLineDash([]);
  const radius = active ? MAP_CANVAS_CONFIG.handleRadius : MAP_CANVAS_CONFIG.handleRadius - 1;
  context.beginPath();
  if (active) {
    context.arc(handle.x, handle.y, radius + 1, 0, Math.PI * 2);
  } else {
    context.arc(handle.x, handle.y, radius, 0, Math.PI * 2);
  }
  context.fillStyle = active ? '#ffffff' : 'rgba(255, 255, 255, 0.75)';
  context.fill();
  context.strokeStyle = color;
  context.lineWidth = active ? 2 : 1.25;
  context.stroke();
}

/** Ручка ширины: ромб на краю полосы с коротким поводком от осевой линии. */
function drawWidthHandle(
  context: CanvasRenderingContext2D,
  base: MapPoint,
  target: MapPoint,
  active: boolean,
): void {
  const center = toScreen(base);
  const handle = toScreen(target);
  context.beginPath();
  context.moveTo(center.x, center.y);
  context.lineTo(handle.x, handle.y);
  context.strokeStyle = 'rgba(249, 168, 37, 0.65)';
  context.lineWidth = 1;
  context.stroke();
  const size = active ? MAP_CANVAS_CONFIG.handleRadius + 3 : MAP_CANVAS_CONFIG.handleRadius + 1;
  context.beginPath();
  context.moveTo(handle.x, handle.y - size);
  context.lineTo(handle.x + size, handle.y);
  context.lineTo(handle.x, handle.y + size);
  context.lineTo(handle.x - size, handle.y);
  context.closePath();
  context.fillStyle = active ? '#f57c00' : '#f9a825';
  context.fill();
  context.strokeStyle = '#5d4037';
  context.lineWidth = 1.25;
  context.stroke();
}

function drawDraft(context: CanvasRenderingContext2D): void {
  drawPolylineDraft(context, props.draftArea, true);
  drawPolylineDraft(context, props.draftLine, false);
  drawRectDraft(context);
  drawCalibration(context);
  drawRuler(context);
}

function drawPolylineDraft(context: CanvasRenderingContext2D, points: MapPoint[], isClosed: boolean): void {
  if (points.length === 0) return;
  context.beginPath();
  points.forEach((point, index) => {
    const screen = toScreen(point);
    if (index === 0) {
      context.moveTo(screen.x, screen.y);
    } else {
      context.lineTo(screen.x, screen.y);
    }
  });
  if (hoverPoint.value && points.length > 0) {
    const screen = toScreen(hoverPoint.value);
    context.lineTo(screen.x, screen.y);
  }
  if (isClosed && points.length >= 2) {
    const first = toScreen(points[0]);
    context.lineTo(first.x, first.y);
  }
  context.strokeStyle = '#1e88e5';
  context.lineWidth = 2;
  context.setLineDash([6, 4]);
  context.stroke();
  context.setLineDash([]);
  for (const point of points) {
    const screen = toScreen(point);
    context.beginPath();
    context.arc(screen.x, screen.y, 4, 0, Math.PI * 2);
    context.fillStyle = '#1e88e5';
    context.fill();
  }
}

function drawRectDraft(context: CanvasRenderingContext2D): void {
  const start = props.rectPoints[0];
  if (!start) return;
  const startScreen = toScreen(start);
  context.beginPath();
  context.arc(startScreen.x, startScreen.y, 4, 0, Math.PI * 2);
  context.fillStyle = '#1e88e5';
  context.fill();
  if (!hoverPoint.value) return;
  const endScreen = toScreen(hoverPoint.value);
  context.strokeStyle = '#1e88e5';
  context.lineWidth = 2;
  context.setLineDash([6, 4]);
  context.strokeRect(
    Math.min(startScreen.x, endScreen.x),
    Math.min(startScreen.y, endScreen.y),
    Math.abs(endScreen.x - startScreen.x),
    Math.abs(endScreen.y - startScreen.y),
  );
  context.setLineDash([]);
}

function drawCalibration(context: CanvasRenderingContext2D): void {
  if (props.calibrationPoints.length === 0) return;
  const first = toScreen(props.calibrationPoints[0]);
  context.beginPath();
  context.arc(first.x, first.y, 5, 0, Math.PI * 2);
  context.fillStyle = '#f9a825';
  context.fill();
  if (props.calibrationPoints.length > 1) {
    const second = toScreen(props.calibrationPoints[1]);
    context.beginPath();
    context.moveTo(first.x, first.y);
    context.lineTo(second.x, second.y);
    context.strokeStyle = '#f9a825';
    context.lineWidth = 2;
    context.stroke();
  }
}

function drawRuler(context: CanvasRenderingContext2D): void {
  if (props.rulerPoints.length === 0) return;
  const screens = props.rulerPoints.map((point) => toScreen(point));
  context.beginPath();
  screens.forEach((point, index) => {
    if (index === 0) {
      context.moveTo(point.x, point.y);
    } else {
      context.lineTo(point.x, point.y);
    }
  });
  if (hoverPoint.value && screens.length > 0) {
    const hover = toScreen(hoverPoint.value);
    context.lineTo(hover.x, hover.y);
  }
  context.strokeStyle = '#f9a825';
  context.lineWidth = 2;
  context.setLineDash([5, 4]);
  context.stroke();
  context.setLineDash([]);
  for (const point of screens) {
    context.beginPath();
    context.arc(point.x, point.y, 4, 0, Math.PI * 2);
    context.fillStyle = '#f9a825';
    context.fill();
  }
  if (props.rulerLabel && screens.length > 1) {
    const last = screens[screens.length - 1];
    context.font = '12px Roboto, sans-serif';
    const width = context.measureText(props.rulerLabel).width + 12;
    context.fillStyle = 'rgba(255, 255, 255, 0.9)';
    context.fillRect(last.x + 8, last.y - 22, width, 20);
    context.fillStyle = '#5d4037';
    context.textBaseline = 'middle';
    context.fillText(props.rulerLabel, last.x + 14, last.y - 12);
  }
}

function resizeCanvas(): void {
  const element = canvas.value;
  const container = wrapper.value;
  const context = element?.getContext('2d');
  if (!element || !container || !context) return;
  const width = Math.max(1, Math.floor(container.clientWidth));
  const height = Math.max(1, Math.floor(container.clientHeight));
  if (width === lastSize.width && height === lastSize.height) return;
  lastSize = { width, height };
  const dpr = window.devicePixelRatio || 1;
  element.width = Math.round(width * dpr);
  element.height = Math.round(height * dpr);
  element.style.width = `${width}px`;
  element.style.height = `${height}px`;
  emit('resize', lastSize);
  draw();
}

function screenPointOf(event: MouseEvent | WheelEvent): CanvasPoint {
  const element = canvas.value;
  if (!element) return { x: 0, y: 0 };
  const rect = element.getBoundingClientRect();

  return { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

function onPointerDown(event: PointerEvent): void {
  try {
    canvas.value?.setPointerCapture(event.pointerId);
  } catch {
    // Синтетические события и некоторые браузеры не дают захват указателя.
  }
  const screen = screenPointOf(event);
  emit('pointer-down', toWorld(screen), screen, event.button, event.altKey);
}

function onPointerMove(event: PointerEvent): void {
  const screen = screenPointOf(event);
  const point = toWorld(screen);
  hoverPoint.value = point;
  emit('pointer-move', point, screen, event.altKey, event.shiftKey);
  scheduleDraw();
}

function onPointerUp(event: PointerEvent): void {
  const screen = screenPointOf(event);
  emit('pointer-up', toWorld(screen), screen);
}

function onWheel(event: WheelEvent): void {
  event.preventDefault();
  emit('wheel', screenPointOf(event), event.deltaY);
}

function onDoubleClick(event: MouseEvent): void {
  emit('pointer-dblclick', toWorld(screenPointOf(event)));
}

function loadBaseImage(publicId: string | null | undefined): void {
  baseImage.value = null;
  if (!publicId) {
    scheduleDraw();

    return;
  }

  const image = new Image();
  image.onload = () => {
    baseImage.value = image;
    const variant = props.variant;
    if (
      variant?.baseFile &&
      (variant.baseFile.width === null || variant.baseFile.height === null) &&
      image.naturalWidth > 0 &&
      image.naturalHeight > 0
    ) {
      emit('base-size', { width: image.naturalWidth, height: image.naturalHeight });
    }
    scheduleDraw();
  };
  image.onerror = () => {
    baseImage.value = null;
    scheduleDraw();
  };
  image.src = getFileApi().getViewUrl(publicId);
}

watch(
  () => [
    props.map,
    props.variant,
    props.viewport,
    props.selected,
    props.selectedTarget,
    props.route,
    props.draftArea,
    props.draftLine,
    props.rectPoints,
    props.calibrationPoints,
    props.rulerPoints,
    props.rulerLabel,
  ],
  scheduleDraw,
  { deep: true },
);

watch(() => props.variant?.baseFile?.publicId, loadBaseImage, { immediate: true });

function mount(): void {
  const container = wrapper.value;
  if (!container) return;
  resizeObserver = new ResizeObserver(resizeCanvas);
  resizeObserver.observe(container);
  resizeCanvas();
}

function unmount(): void {
  resizeObserver?.disconnect();
  resizeObserver = null;
  if (frame !== 0) {
    cancelAnimationFrame(frame);
    frame = 0;
  }
}

onMounted(mount);
onUnmounted(unmount);
</script>

<template>
  <div ref="wrapper" class="map-canvas" :style="{ cursor: props.cursor }">
    <canvas
      ref="canvas"
      class="map-canvas__surface"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      @dblclick="onDoubleClick"
      @wheel="onWheel"
      @contextmenu.prevent
    />
  </div>
</template>

<style scoped>
.map-canvas {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 8px;
  background: #ece7db;
  touch-action: none;
}

.map-canvas__surface {
  display: block;
}
</style>
