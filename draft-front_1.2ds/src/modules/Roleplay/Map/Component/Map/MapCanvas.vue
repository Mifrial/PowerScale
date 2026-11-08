<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, toRaw, watch } from 'vue';
import type { CanvasPoint } from '@/modules/Roleplay/Map/Dto/CanvasPoint';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
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
import type { RibbonEdges, RibbonLayout } from '@/modules/Roleplay/Map/Service/MapRibbonService';
import type { MapDecorPoint } from '@/modules/Roleplay/Map/Service/MapDecorService';
import { MAP_CANVAS_CONFIG } from '@/modules/Roleplay/Map/Constant/Canvas/MAP_CANVAS_CONFIG';
import { MAP_AREA_STROKE } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_STROKE';
import { MAP_LINE_DEPTH } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DEPTH';
import { MAP_RECOGNITION } from '@/modules/Roleplay/Map/Constant/Map/MAP_RECOGNITION';
import {
  mapDecorPainter,
  mapDecorService,
  mapGeometryService,
  mapRenderService,
  mapRibbonService,
} from '@/modules/Roleplay/Map/init';
import { getFileApi } from '@/modules/Core/File/init';

const props = defineProps<{
  map: MapDetail | null;
  variant: MapVariant | null;
  viewport: MapViewport;
  /** Счётчик правок черновика: холст не наблюдает карту глубоко. */
  revision: number;
  /** Показывать подписи всех областей, а не только крупных. */
  showLabels: boolean;
  selected: MapHit | null;
  selectedTarget: MapEditTarget | null;
  /** Массовое выделение объектов (Shift+рамка). */
  selectedIds: number[];
  /** Текущая рамка выделения в мировых координатах. */
  marquee: { start: MapPoint; end: MapPoint } | null;
  /** Объект под перетаскиванием: рисуется поверх без пересборки статичного слоя. */
  liveDrag: MapHit | null;
  route: MapRoute | null;
  draftArea: MapPoint[];
  draftLine: MapPoint[];
  rectPoints: MapPoint[];
  calibrationPoints: MapPoint[];
  rulerPoints: MapPoint[];
  rulerLabel: string | null;
  tool: MapToolMode;
  cursor: string;
  recognitionMask: HTMLCanvasElement | null;
  recognitionPaths: MapPoint[][];
  recognitionExcluded: boolean[];
}>();

const emit = defineEmits<{
  'pointer-down': [point: MapPoint, screen: CanvasPoint, button: number, alt: boolean, shift: boolean];
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
let flowFrame = 0;
let lastFlowDraw = 0;
let drawTime = 0;
let lastSize: MapSize = { width: 0, height: 0 };
let staticCanvas: HTMLCanvasElement | null = null;
let labelCanvas: HTMLCanvasElement | null = null;
let decorCanvas: HTMLCanvasElement | null = null;
let staticDirty = true;
let paintedStatic = false;
/** Объект, для которого статичный слой уже пересобран без него. */
let livePainted = '';
let staticViewport: MapViewport | null = null;
let staticSize: MapSize = { width: 0, height: 0 };
let zoomTimer: ReturnType<typeof setTimeout> | null = null;
/** Индекс областей по id: без него каждый контур искал себя в списке заново. */
const areaIndex = new Map<number, MapArea>();
let areaIndexSource: MapArea[] | null = null;
let areaIndexRevision = -1;
interface AreaGeometry {
  path: MapPoint[];
  minX: number;
  minY: number;
  maxX: number;
  maxY: number;
}
interface AreaGeometryEntry {
  shape: MapAreaShape;
  pathRef: MapPoint[];
  handlesRef: MapHandle[];
  geometry: AreaGeometry;
  path2d: Path2D;
  blocker: DecorBlocker;
  /** Разложенный декор детерминирован: пересчитывается только при смене декора. */
  decorRef?: MapAreaDecor | null;
  decorPoints?: MapDecorPoint[];
}
/** Геометрия и путь кэшируются по области: правка пересобирает только её. */
const areaGeometryCache = new Map<number, AreaGeometryEntry | null>();
/** Выбранная форма варианта: find по shapes на каждом кадре стоил десятки мс. */
const areaShapeCache = new Map<number, { shapes: MapAreaShape[]; length: number; shape: MapAreaShape | null }>();
let geometryVariant: number | null = null;
let geometryMap: MapDetail | null = null;
const sortedCache = new Map<'surface' | 'object', MapArea[]>();
let sortedRevision = -1;
let sortedMap: MapDetail | null = null;
let nodeIdsCache: Set<number> | null = null;
let nodeIdsRevision = -1;
const reducedMotion = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const flowAvailable = computed(
  () =>
    !reducedMotion &&
    (props.map?.lines ?? []).some(
      (line) => line.variantId === props.variant?.id && line.isPresent && line.decor?.flow?.enabled === true,
    ),
);

/**
 * Сырые данные карты: холст перерисовывается по revision, а обход реактивных
 * прокси на 6000 объектах стоит десятки миллисекунд на кадр. Ссылки
 * обновляются наблюдателем, а не читаются из props на каждую область.
 */
let renderMap: MapDetail | null = null;
let renderVariant: MapVariant | null = null;
const rawMap = (): MapDetail | null => renderMap;
const rawVariant = (): MapVariant | null => renderVariant;
/** Лента под перетаскиванием: её тело не попадает в статичный слой. */
const liveLineId = (): number | null => (props.liveDrag?.kind === 'line' ? props.liveDrag.id : null);
/** Область под перетаскиванием: её фигура и декор не попадают в статичный слой. */
const liveAreaId = (): number | null => (props.liveDrag?.kind === 'area' ? props.liveDrag.id : null);

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
  if (nodeIdsCache !== null && nodeIdsRevision === props.revision) return nodeIdsCache;
  const ids = new Set<number>();
  for (const area of rawMap()?.areas ?? []) {
    if (area.nodeId !== null) ids.add(area.nodeId);
  }
  nodeIdsCache = ids;
  nodeIdsRevision = props.revision;

  return ids;
};

/** Индекс областей: строится один раз на ревизию черновика. */
function ensureAreaIndex(): Map<number, MapArea> {
  const areas = rawMap()?.areas ?? [];
  if (areaIndexSource === areas && areaIndexRevision === props.revision) return areaIndex;
  areaIndex.clear();
  for (const area of areas) areaIndex.set(area.id, area);
  areaIndexSource = areas;
  areaIndexRevision = props.revision;

  return areaIndex;
}

const sortedAreas = (layer: 'surface' | 'object'): MapArea[] => {
  ensureAreaIndex();
  if (sortedRevision !== props.revision || sortedMap !== rawMap()) {
    sortedCache.clear();
    sortedRevision = props.revision;
    sortedMap = rawMap();
  }
  const cached = sortedCache.get(layer);
  if (cached !== undefined) return cached;
  const list = (rawMap()?.areas ?? [])
    .filter((area) => area.layer === layer)
    .slice()
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
  sortedCache.set(layer, list);

  return list;
};

const sortedLines = () => {
  const variant = rawVariant();
  if (!variant) return [];

  return (rawMap()?.lines ?? [])
    .filter((line) => line.variantId === variant.id && line.isPresent && line.points.length >= 2)
    .slice()
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
};

const placements = (): Record<number, MapPoint> => {
  const map = rawMap();
  const variant = rawVariant();
  if (!map || !variant) return {};

  return mapGeometryService.placementPoints(map.nodes, variant.id);
};

/** Смена карты или варианта сбрасывает кэш целиком. */
function ensureGeometryScope(): void {
  const variantId = rawVariant()?.id ?? null;
  const map = rawMap();
  if (geometryVariant === variantId && geometryMap === map) return;
  areaGeometryCache.clear();
  areaShapeCache.clear();
  geometryVariant = variantId;
  geometryMap = map;
}

/** Форма варианта с кэшем: повторный поиск нужен только при смене набора форм. */
function resolveShape(area: MapArea, variantId: number): MapAreaShape | null {
  const shapes = area.shapes;
  const cached = areaShapeCache.get(area.id);
  if (cached !== undefined && cached.shapes === shapes && cached.length === shapes.length) {
    if (cached.shape === null) return null;

    return cached.shape.isPresent ? cached.shape : null;
  }
  let found: MapAreaShape | null = null;
  for (let index = 0; index < shapes.length; index += 1) {
    const candidate = shapes[index];
    if (candidate.variantId === variantId && candidate.isPresent) {
      found = candidate;

      break;
    }
  }
  areaShapeCache.set(area.id, { shapes, length: shapes.length, shape: found });

  return found;
}

function geometryOf(area: MapArea): AreaGeometry | null {
  ensureGeometryScope();
  const variant = rawVariant();
  const shape = variant ? resolveShape(area, variant.id) : null;
  const cached = areaGeometryCache.get(area.id);
  if (cached !== undefined) {
    const fresh =
      cached === null
        ? shape === null
        : shape !== null &&
          cached.shape === shape &&
          cached.pathRef === shape.path &&
          cached.handlesRef === (shape.handles ?? []);
    if (fresh) return cached === null ? null : cached.geometry;
  }
  if (!shape) {
    areaGeometryCache.set(area.id, null);

    return null;
  }

  const paths = shape.path;
  const handles = shape.handles ?? [];
  const path = mapGeometryService.flattenContour(paths, handles);
  if (path.length < 3) {
    areaGeometryCache.set(area.id, null);

    return null;
  }
  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;
  for (const [x, y] of path) {
    if (x < minX) minX = x;
    if (y < minY) minY = y;
    if (x > maxX) maxX = x;
    if (y > maxY) maxY = y;
  }
  const geometry: AreaGeometry = { path, minX, minY, maxX, maxY };
  const path2d = new Path2D();
  tracePath(path2d, path);
  const blocker: DecorBlocker = { polygon: path, minX, minY, maxX, maxY, erase: true, path2d };
  areaGeometryCache.set(area.id, { shape, pathRef: paths, handlesRef: handles, geometry, path2d, blocker });

  return geometry;
}

function geometryEntryOf(area: MapArea): AreaGeometryEntry | null {
  geometryOf(area);
  const entry = areaGeometryCache.get(area.id);

  return entry === undefined ? null : entry;
}

const areaShapeOf = (areaId: number): MapAreaShape | null => {
  const variant = rawVariant();
  const area = ensureAreaIndex().get(areaId);
  if (!variant || !area) return null;

  return area.shapes.find((candidate) => candidate.variantId === variant.id && candidate.isPresent) ?? null;
};

const flatShapeOf = (areaId: number): MapPoint[] | null => {
  const area = ensureAreaIndex().get(areaId);

  return area === undefined ? null : (geometryOf(area)?.path ?? null);
};

interface WorldRect {
  minX: number;
  minY: number;
  maxX: number;
  maxY: number;
}

/** Видимый прямоугольник мира: по нему отсекаются области и декор. */
function visibleWorldRect(): WorldRect {
  const scale = props.viewport.scale || 1;

  return {
    minX: -props.viewport.offsetX / scale,
    minY: -props.viewport.offsetY / scale,
    maxX: (lastSize.width - props.viewport.offsetX) / scale,
    maxY: (lastSize.height - props.viewport.offsetY) / scale,
  };
}

function intersects(geometry: AreaGeometry, rect: WorldRect, margin: number): boolean {
  return (
    geometry.maxX >= rect.minX - margin &&
    geometry.minX <= rect.maxX + margin &&
    geometry.maxY >= rect.minY - margin &&
    geometry.minY <= rect.maxY + margin
  );
}

const selectedArea = (): { id: number; path: MapPoint[]; handles: MapHandle[] } | null => {
  if (props.selected?.kind !== 'area') return null;
  const shape = areaShapeOf(props.selected.id);
  if (!shape) return null;

  return { id: props.selected.id, path: shape.path, handles: shape.handles ?? [] };
};

const selectedLine = (): MapLine | null => {
  if (props.selected?.kind !== 'line') return null;

  return rawMap()?.lines.find((line) => line.id === props.selected?.id) ?? null;
};

function scheduleDraw(): void {
  if (frame !== 0) return;
  frame = requestAnimationFrame((timestamp) => {
    frame = 0;
    draw(timestamp);
  });
}

/** Помечает статичный слой устаревшим и перерисовывает сцену. */
function invalidateStatic(): void {
  staticDirty = true;
  scheduleDraw();
}

/** Чёткая перерисовка после зума: пока крутится колесо, показываем старый слой. */
const ZOOM_REPAINT_DELAY = 120;

function scheduleZoomRepaint(): void {
  if (zoomTimer !== null) clearTimeout(zoomTimer);
  zoomTimer = setTimeout(() => {
    zoomTimer = null;
    staticDirty = true;
    scheduleDraw();
  }, ZOOM_REPAINT_DELAY);
}

function startFlow(): void {
  if (flowFrame !== 0 || !flowAvailable.value) return;
  // Пока тянут объект, каждый кадр уже занят динамикой: анимация ряби только
  // добавляет второй полный проход отрисовки и удваивает время кадра.
  if (props.liveDrag !== null) return;
  lastFlowDraw = 0;
  flowFrame = requestAnimationFrame(flowTick);
}

function stopFlow(): void {
  if (flowFrame === 0) return;
  cancelAnimationFrame(flowFrame);
  flowFrame = 0;
}

function flowTick(timestamp: number): void {
  flowFrame = 0;
  if (!flowAvailable.value || document.hidden) return;
  // Ограничение ~66 fps: на 120-Гц экране не удваиваем нагрузку.
  if (timestamp - lastFlowDraw >= 15) {
    lastFlowDraw = timestamp;
    draw(timestamp);
  }
  flowFrame = requestAnimationFrame(flowTick);
}

function onVisibilityChange(): void {
  if (document.hidden) {
    stopFlow();
  } else {
    startFlow();
  }
}

function draw(timestamp = performance.now()): void {
  drawTime = timestamp;
  const element = canvas.value;
  if (!element) return;
  const context = element.getContext('2d');
  if (!context) return;
  const { width, height } = lastSize;
  if (width === 0 || height === 0) return;
  const dpr = window.devicePixelRatio || 1;

  ensureStaticLayer(width, height, dpr);
  labelCanvas = ensureLayer(labelCanvas, width, height, dpr);
  const sizeKept = staticSize.width === width && staticSize.height === height;
  // Пока тянут объект, слой уже собран без него: пересобирать 6000 объектов
  // каждый кадр незачем, двигающийся объект рисуется поверх.
  const live = props.liveDrag;
  const liveKey = live === null ? '' : `${live.kind}:${live.id}`;
  const liveChanged = liveKey !== livePainted;
  if (liveChanged) livePainted = liveKey;
  const liveFrozen = !liveChanged && live !== null;
  // Зум идёт пачкой событий колеса: пока слой не перерисован, растягиваем
  // прошлый кадр — это мгновенно, а чёткая картинка придёт по затишью.
  const stale = staticDirty && paintedStatic && zoomTimer !== null && sizeKept && staticViewport !== null;
  // Смена «живого» объекта важнее растянутого кадра зума: слой обязан потерять
  // старую фигуру сразу, иначе под новой останется призрак.
  if (staticDirty && !liveFrozen && (!stale || liveChanged)) {
    paintStaticLayer(width, height, dpr);
    paintLabelLayer(width, height, dpr);
    staticDirty = false;
    paintedStatic = true;
    staticViewport = { ...props.viewport };
    staticSize = { width, height };
  }

  context.setTransform(dpr, 0, 0, dpr, 0, 0);
  context.clearRect(0, 0, width, height);
  if (staticCanvas) {
    if (stale && staticViewport) {
      compositeScaled(context, staticCanvas, staticViewport);
    } else {
      context.drawImage(staticCanvas, 0, 0, width, height);
    }
  }

  context.save();
  context.translate(props.viewport.offsetX, props.viewport.offsetY);
  context.scale(props.viewport.scale, props.viewport.scale);
  drawLiveLine(context);
  drawLiveArea(context);
  // Во время перетаскивания рябь заморожена: рисовать её кадр за кадром
  // бессмысленно, а на картах с десятками рек это лишний полный проход.
  if (props.liveDrag === null) drawFlow(context);
  drawSelection(context);
  drawMarquee(context);
  drawRecognition(context);
  context.restore();

  if (labelCanvas) {
    if (stale && staticViewport) {
      compositeScaled(context, labelCanvas, staticViewport);
    } else {
      context.drawImage(labelCanvas, 0, 0, width, height);
    }
  }
  drawNodes(context);
  drawEditorHandles(context);
  drawDraft(context);
}

/** Переносит готовый слой со старого вида на текущий без перерисовки сцены. */
function compositeScaled(context: CanvasRenderingContext2D, layer: HTMLCanvasElement, from: MapViewport): void {
  const ratio = props.viewport.scale / (from.scale || 1);
  context.drawImage(
    layer,
    props.viewport.offsetX - from.offsetX * ratio,
    props.viewport.offsetY - from.offsetY * ratio,
    lastSize.width * ratio,
    lastSize.height * ratio,
  );
}

/** Превью распознавания: подсветка пикселей и контуры поверх сцены. */
function drawRecognition(context: CanvasRenderingContext2D): void {
  if (props.recognitionMask === null && props.recognitionPaths.length === 0) return;
  if (props.recognitionMask !== null) {
    context.save();
    context.globalAlpha = MAP_RECOGNITION.preview.maskOpacity;
    context.drawImage(props.recognitionMask, 0, 0, worldWidth(), worldHeight());
    context.restore();
  }
  context.save();
  context.lineWidth = MAP_RECOGNITION.preview.strokeWidth / props.viewport.scale;
  for (let index = 0; index < props.recognitionPaths.length; index += 1) {
    const excluded = props.recognitionExcluded[index] === true;
    tracePolygon(context, props.recognitionPaths[index]);
    if (excluded) {
      // Исключённое пятно заливается серым: на плотной карте пунктира мало,
      // чтобы понять, какие объекты не будут созданы.
      context.fillStyle = MAP_RECOGNITION.preview.excludedFill;
      context.fill();
      context.strokeStyle = MAP_RECOGNITION.preview.excludedStroke;
      const dash = MAP_RECOGNITION.preview.dash / props.viewport.scale;
      context.setLineDash([dash, dash]);
    } else {
      context.strokeStyle = MAP_RECOGNITION.preview.stroke;
      context.setLineDash([]);
    }
    context.stroke();
  }
  context.restore();
}

/** Статичный слой подложки: пересобирается только при изменении сцены. */
function ensureStaticLayer(width: number, height: number, dpr: number): HTMLCanvasElement {
  staticCanvas = ensureLayer(staticCanvas, width, height, dpr);

  return staticCanvas;
}

function ensureLayer(
  existing: HTMLCanvasElement | null,
  width: number,
  height: number,
  dpr: number,
): HTMLCanvasElement {
  const layer = existing ?? document.createElement('canvas');
  const pixelWidth = Math.max(1, Math.round(width * dpr));
  const pixelHeight = Math.max(1, Math.round(height * dpr));
  if (layer.width !== pixelWidth || layer.height !== pixelHeight) {
    layer.width = pixelWidth;
    layer.height = pixelHeight;
    staticDirty = true;
  }

  return layer;
}

/** Меняет размер слоя под текущий кадр, не помечая статичный слой устаревшим. */
function resizeLayer(
  existing: HTMLCanvasElement | null,
  width: number,
  height: number,
  dpr: number,
): HTMLCanvasElement {
  const layer = existing ?? document.createElement('canvas');
  const pixelWidth = Math.max(1, Math.round(width * dpr));
  const pixelHeight = Math.max(1, Math.round(height * dpr));
  if (layer.width !== pixelWidth || layer.height !== pixelHeight) {
    layer.width = pixelWidth;
    layer.height = pixelHeight;
  }

  return layer;
}

function paintStaticLayer(width: number, height: number, dpr: number): void {
  const layer = ensureStaticLayer(width, height, dpr);
  const context = layer.getContext('2d');
  if (!context) return;

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
  const rect = visibleWorldRect();
  const margin = MAP_AREA_STROKE.maxWidth / (props.viewport.scale || 1);
  // Объекты собираются один раз: их пути рисуются после декора, а их полигоны
  // вырезают деревья из декора поверхности.
  const objectEntries = collectAreaEntries('object', rect, margin);
  drawAreaEntries(context, collectAreaEntries('surface', rect, margin));
  drawLineBodies(context);
  // Декор ложится поверх лент: крона у берега свисает над водой и дорогой,
  // а не обрезается их краем. Объекты рисуются позже и перекрывают декор.
  const hasSurfaceDecor = sortedAreas('surface').some((area) => area.decor?.set);
  drawDecor(context, 'surface', width, height, dpr, hasSurfaceDecor ? decorBlockers(objectEntries) : []);
  drawAreaEntries(context, objectEntries);
  drawDecor(context, 'object', width, height, dpr, []);
  drawRoute(context);
  context.restore();
}

/** Подписи областей в экранных координатах: свой слой поверх ряби. */
function paintLabelLayer(width: number, height: number, dpr: number): void {
  if (!labelCanvas) return;
  const context = labelCanvas.getContext('2d');
  if (!context) return;
  context.setTransform(dpr, 0, 0, dpr, 0, 0);
  context.clearRect(0, 0, width, height);
  drawAreaLabels(context);
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

interface RenderEntry {
  area: MapArea;
  geometry: AreaGeometry;
  path2d: Path2D;
  blocker: DecorBlocker;
}

/** Видимые области слоя вместе с готовыми путями: один проход на кадр. */
function collectAreaEntries(layer: 'surface' | 'object', rect: WorldRect, margin: number): RenderEntry[] {
  const entries: RenderEntry[] = [];
  const live = liveAreaId();
  for (const area of sortedAreas(layer)) {
    if (area.id === live) continue;
    const entry = geometryEntryOf(area);
    if (!entry || !intersects(entry.geometry, rect, margin)) continue;
    entries.push({ area, geometry: entry.geometry, path2d: entry.path2d, blocker: entry.blocker });
  }

  return entries;
}

function drawAreaEntries(context: CanvasRenderingContext2D, entries: RenderEntry[]): void {
  const map = rawMap();
  if (!map) return;
  const fillBase = map.fillOpacity;
  const scale = props.viewport.scale || 1;
  // Одинаково оформленные области рисуются одной пачкой: на 6000 объектах
  // шесть тысяч вызовов fill/stroke стоят сотни миллисекунд. Пачки не
  // сливаются через разные стили, чтобы порядок наложения сохранялся.
  const batches: AreaBatch[] = [];
  let batch: AreaBatch | null = null;
  let batchKey = '';
  for (const { area, path2d } of entries) {
    const fillOpacity = mapRenderService.areaFillOpacity(area, fillBase);
    const stroke = area.stroke ?? '';
    const strokeWidth = area.strokeWidth || MAP_AREA_STROKE.defaultWidth;
    const key = `${area.color}|${fillOpacity}|${stroke}|${strokeWidth}`;
    if (batch === null || key !== batchKey) {
      batch = {
        path: new Path2D(),
        alpha: fillOpacity / 100,
        fill: area.color,
        stroke,
        strokeWidth,
      };
      batchKey = key;
      batches.push(batch);
    }
    batch.path.addPath(path2d);
  }
  for (const item of batches) {
    context.globalAlpha = item.alpha;
    context.fillStyle = item.fill;
    context.fill(item.path);
    context.globalAlpha = 1;
    if (item.stroke) {
      context.strokeStyle = item.stroke;
      context.lineWidth = item.strokeWidth / scale;
      context.stroke(item.path);
    }
  }
}

interface AreaBatch {
  path: Path2D;
  alpha: number;
  fill: string;
  stroke: string;
  strokeWidth: number;
}

/** Обводит замкнутый контур в путь. */
function tracePath(path: Path2D, points: MapPoint[]): void {
  for (let index = 0; index < points.length; index += 1) {
    const point = points[index];
    if (index === 0) {
      path.moveTo(point[0], point[1]);
    } else {
      path.lineTo(point[0], point[1]);
    }
  }
  path.closePath();
}

interface BlockerGrid {
  minX: number;
  minY: number;
  cell: number;
  cols: number;
  rows: number;
  cells: DecorBlocker[][];
}

/** Равномерная сетка по рамкам блокеров: проверяются только соседи клетки. */
function buildBlockerGrid(blockers: DecorBlocker[]): BlockerGrid | null {
  if (blockers.length === 0) return null;
  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;
  for (const blocker of blockers) {
    minX = Math.min(minX, blocker.minX);
    minY = Math.min(minY, blocker.minY);
    maxX = Math.max(maxX, blocker.maxX);
    maxY = Math.max(maxY, blocker.maxY);
  }
  // Клетка подбирается по плотности: на разреженной карте крупнее, на плотной
  // мельче, чтобы в одной клетке было 1–3 блокера.
  const span = Math.max(1, maxX - minX) * Math.max(1, maxY - minY);
  const cell = Math.max(8, Math.min(64, Math.sqrt(span / blockers.length) * 1.5));
  const cols = Math.max(1, Math.ceil((maxX - minX) / cell) + 1);
  const rows = Math.max(1, Math.ceil((maxY - minY) / cell) + 1);
  const cells: DecorBlocker[][] = new Array(cols * rows);
  for (let index = 0; index < cells.length; index += 1) cells[index] = [];
  for (const blocker of blockers) {
    const startCol = Math.max(0, Math.min(cols - 1, Math.floor((blocker.minX - minX) / cell)));
    const endCol = Math.max(0, Math.min(cols - 1, Math.floor((blocker.maxX - minX) / cell)));
    const startRow = Math.max(0, Math.min(rows - 1, Math.floor((blocker.minY - minY) / cell)));
    const endRow = Math.max(0, Math.min(rows - 1, Math.floor((blocker.maxY - minY) / cell)));
    for (let row = startRow; row <= endRow; row += 1) {
      for (let col = startCol; col <= endCol; col += 1) cells[row * cols + col].push(blocker);
    }
  }

  return { minX, minY, cell, cols, rows, cells };
}

function insideAnyBlocker(x: number, y: number, grid: BlockerGrid | null): boolean {
  if (grid === null) return false;
  const col = Math.floor((x - grid.minX) / grid.cell);
  const row = Math.floor((y - grid.minY) / grid.cell);
  if (col < 0 || col >= grid.cols || row < 0 || row >= grid.rows) return false;
  for (const blocker of grid.cells[row * grid.cols + col]) {
    if (insideBlocker(x, y, blocker)) return true;
  }

  return false;
}

/**
 * Декор слоя: символы рассыпаются по полигонам и могут выходить за контур.
 *
 * Рисуется в отдельный слой, потому что под реками, дорогами и объектами его
 * надо вырезать. Прямой клип тут не годится: пересекающиеся маски при even-odd
 * снова открывают дырку, поэтому маски стираются `destination-out`.
 */
function drawDecor(
  context: CanvasRenderingContext2D,
  layer: 'surface' | 'object',
  width: number,
  height: number,
  dpr: number,
  blockers: DecorBlocker[],
): void {
  if (!rawMap()) return;

  const drawings: { area: MapArea; points: MapDecorPoint[] }[] = [];
  const rect = visibleWorldRect();
  const margin = 32 / (props.viewport.scale || 1);
  // Блокеров тысячи: без сетки фильтрация точек даёт миллионы проверок.
  const grid = buildBlockerGrid(blockers);
  const live = liveAreaId();
  for (const area of sortedAreas(layer)) {
    if (area.id === live) continue;
    if (!area.decor?.set) continue;
    const entry = geometryEntryOf(area);
    if (!entry || !intersects(entry.geometry, rect, margin)) continue;
    let layout = entry.decorRef === area.decor ? entry.decorPoints : undefined;
    if (layout === undefined) {
      layout = mapDecorService.layout(area.id, entry.geometry.path, area.decor);
      entry.decorRef = area.decor;
      entry.decorPoints = layout;
    }
    // Точка в русле или в доме не даёт дерева: лес не растёт из воды и из стен.
    const points = layout.filter((point) => !insideAnyBlocker(point.x, point.y, grid));
    if (points.length > 0) drawings.push({ area, points });
  }
  if (drawings.length === 0) return;

  const decor = resizeLayer(decorCanvas, width, height, dpr);
  decorCanvas = decor;
  const target = decor.getContext('2d');
  if (!target) return;

  target.setTransform(dpr, 0, 0, dpr, 0, 0);
  target.clearRect(0, 0, width, height);
  target.save();
  target.translate(props.viewport.offsetX, props.viewport.offsetY);
  target.scale(props.viewport.scale, props.viewport.scale);

  for (const { area, points } of drawings) {
    mapDecorPainter.paint(target, {
      points,
      color: area.color,
      minSize: 3 / props.viewport.scale,
    });
  }

  target.restore();
  if (layer === 'surface') eraseDecorMask(target, blockers);

  context.save();
  context.setTransform(1, 0, 0, 1, 0, 0);
  context.drawImage(decor, 0, 0);
  context.restore();
}

/** Полигон, под которым декор не сажается: русло, дорога или объект. */
interface DecorBlocker {
  polygon: MapPoint[];
  minX: number;
  minY: number;
  maxX: number;
  maxY: number;
  /** Объект рисуется поверх декора и полупрозрачен — его полигон надо вырезать. */
  erase: boolean;
  /** Готовый путь объекта: маска стирания собирается одним fill. */
  path2d?: Path2D;
}

/** Русла, дороги и объекты: под ними деревья не растут. */
function decorBlockers(objectEntries: RenderEntry[]): DecorBlocker[] {
  const blockers: DecorBlocker[] = [];
  for (const line of sortedLines()) {
    if (line.id === liveLineId()) continue;
    const band = mapRibbonService.ribbonBand(mapRibbonService.layout(line).flat, -1, 1);
    if (band.length >= 3) blockers.push(makeBlocker(band, false));
  }
  for (const entry of objectEntries) blockers.push(entry.blocker);

  return blockers;
}

function makeBlocker(polygon: MapPoint[], erase: boolean): DecorBlocker {
  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;
  for (const [x, y] of polygon) {
    minX = Math.min(minX, x);
    minY = Math.min(minY, y);
    maxX = Math.max(maxX, x);
    maxY = Math.max(maxY, y);
  }

  return { polygon, minX, minY, maxX, maxY, erase };
}

/** Точка внутри блокера; сначала дешёвая проверка по прямоугольнику. */
function insideBlocker(x: number, y: number, blocker: DecorBlocker): boolean {
  if (x < blocker.minX || x > blocker.maxX || y < blocker.minY || y > blocker.maxY) return false;

  return mapGeometryService.pointInPolygon([x, y], blocker.polygon);
}

/** Стирает из готового декора объекты: они выше и полупрозрачны. */
function eraseDecorMask(context: CanvasRenderingContext2D, blockers: DecorBlocker[]): void {
  const mask = blockers.filter((blocker) => blocker.erase);
  if (mask.length === 0) return;

  context.save();
  context.translate(props.viewport.offsetX, props.viewport.offsetY);
  context.scale(props.viewport.scale, props.viewport.scale);
  context.globalCompositeOperation = 'destination-out';
  context.fillStyle = '#000';
  const maskPath = new Path2D();
  for (const blocker of mask) {
    if (blocker.path2d) maskPath.addPath(blocker.path2d);
    else tracePath(maskPath, blocker.polygon);
  }
  context.fill(maskPath);
  context.restore();
}

/** Заливка и берега лент — часть статичного слоя. */
function drawLineBodies(context: CanvasRenderingContext2D): void {
  const map = rawMap();
  if (!map || !rawVariant()) return;
  for (const line of sortedLines()) {
    // Объект под перетаскиванием живёт в динамическом слое: статичный слой
    // собран без него, иначе на карте остался бы старый след.
    if (line.id === liveLineId()) continue;
    const layout = mapRibbonService.layout(line);
    const fillOpacity = mapRenderService.lineFillOpacity(line, map.fillOpacity);
    drawLineFill(context, line, layout, map.fillOpacity);
    drawLineDepth(
      context,
      layout,
      mapRenderService.lineDepth(line),
      mapRenderService.lineDepthWidth(line),
      fillOpacity,
    );
    drawLineEdges(context, layout);
  }
}

/** Лента под перетаскиванием: пересобирается каждый кадр, но она одна. */
function drawLiveLine(context: CanvasRenderingContext2D): void {
  const id = liveLineId();
  const map = rawMap();
  if (id === null || map === null) return;
  const line = map.lines.find((candidate) => candidate.id === id && candidate.isPresent);
  if (!line) return;
  const layout = mapRibbonService.layout(line);
  const fillOpacity = mapRenderService.lineFillOpacity(line, map.fillOpacity);
  drawLineFill(context, line, layout, map.fillOpacity);
  drawLineDepth(
    context,
    layout,
    mapRenderService.lineDepth(line),
    mapRenderService.lineDepthWidth(line),
    fillOpacity,
  );
  drawLineEdges(context, layout);
}

/** Область под перетаскиванием: путь меняется каждый кадр, но она одна. */
function drawLiveArea(context: CanvasRenderingContext2D): void {
  const id = liveAreaId();
  const map = rawMap();
  if (id === null || map === null) return;
  const area = ensureAreaIndex().get(id);
  if (!area) return;
  const entry = geometryEntryOf(area);
  if (!entry) return;
  const scale = props.viewport.scale || 1;
  context.save();
  context.globalAlpha = mapRenderService.areaFillOpacity(area, map.fillOpacity) / 100;
  context.fillStyle = area.color;
  context.fill(entry.path2d);
  context.globalAlpha = 1;
  if (area.stroke) {
    context.strokeStyle = area.stroke;
    context.lineWidth = (area.strokeWidth || MAP_AREA_STROKE.defaultWidth) / scale;
    context.stroke(entry.path2d);
  }
  context.restore();
}

/** Рябь: единственная часть лент, которая перерисовывается каждый кадр. */
function drawFlow(context: CanvasRenderingContext2D): void {
  if (!rawMap() || !rawVariant()) return;
  for (const line of sortedLines()) {
    if (line.decor?.flow?.enabled !== true) continue;
    drawLineFlow(context, line, mapRibbonService.layout(line));
  }
}

/** Подсветка выбранного элемента поверх статичного слоя. */
function drawSelection(context: CanvasRenderingContext2D): void {
  const scale = props.viewport.scale || 1;
  if (props.selectedIds.length > 0) {
    const batch = new Path2D();
    let count = 0;
    for (const id of props.selectedIds) {
      const area = ensureAreaIndex().get(id);
      if (!area) continue;
      const entry = geometryEntryOf(area);
      if (!entry) continue;
      batch.addPath(entry.path2d);
      count += 1;
    }
    if (count > 0) {
      context.save();
      context.strokeStyle = '#1e88e5';
      context.lineWidth = (MAP_CANVAS_CONFIG.areaStrokeWidth + 1) / scale;
      context.stroke(batch);
      context.restore();
    }
  }

  const selected = props.selected;
  if (!selected) return;

  if (selected.kind === 'area') {
    const path = flatShapeOf(selected.id);
    if (!path || path.length < 3) return;
    context.beginPath();
    path.forEach((point, index) => {
      if (index === 0) {
        context.moveTo(point[0], point[1]);
      } else {
        context.lineTo(point[0], point[1]);
      }
    });
    context.closePath();
    context.strokeStyle = '#1e88e5';
    context.lineWidth = (MAP_CANVAS_CONFIG.areaStrokeWidth + 2) / props.viewport.scale;
    context.stroke();

    return;
  }

  if (selected.kind === 'line') {
    const line = sortedLines().find((candidate) => candidate.id === selected.id);
    if (!line) return;
    drawPolyline(context, mapRibbonService.layout(line).flat.points, '#1e88e5', 2 / props.viewport.scale);
  }
}

/** Рамка массового выделения. */
function drawMarquee(context: CanvasRenderingContext2D): void {
  const marquee = props.marquee;
  if (!marquee) return;
  const scale = props.viewport.scale || 1;
  const minX = Math.min(marquee.start[0], marquee.end[0]);
  const minY = Math.min(marquee.start[1], marquee.end[1]);
  const width = Math.abs(marquee.end[0] - marquee.start[0]);
  const height = Math.abs(marquee.end[1] - marquee.start[1]);
  context.save();
  context.fillStyle = 'rgba(30, 136, 229, 0.12)';
  context.fillRect(minX, minY, width, height);
  context.strokeStyle = '#1e88e5';
  context.lineWidth = 1 / scale;
  context.setLineDash([4 / scale, 3 / scale]);
  context.strokeRect(minX, minY, width, height);
  context.restore();
}

/** Заливка непрерывных участков цвета и градиентных переходов. */
function drawLineFill(
  context: CanvasRenderingContext2D,
  line: MapLine,
  layout: RibbonLayout,
  mapOpacity: number,
): void {
  context.globalAlpha = mapRenderService.lineFillOpacity(line, mapOpacity) / 100;
  for (const run of layout.runs) {
    fillRibbonRange(context, layout, run.from, run.to, run.color);
  }
  for (const transition of layout.transitions) {
    fillRibbonTransition(context, layout, transition);
  }
  context.globalAlpha = 1;
}

/** Профиль русла: затемнение к центру и мелководье у берегов. */
function drawLineDepth(
  context: CanvasRenderingContext2D,
  layout: RibbonLayout,
  depth: number,
  depthWidth: number,
  fillOpacity: number,
): void {
  if (depth <= 0 || fillOpacity <= 0) return;
  const widthSum = layout.flat.widths.reduce((sum, width) => sum + width, 0);
  if (widthSum / Math.max(1, layout.flat.widths.length) < MAP_LINE_DEPTH.minWidth) return;
  // При почти прозрачной заливке профиль гаснет вместе с ней, но на обычных
  // 20–100 % работает в полную силу, иначе глубина теряется.
  const strength = (depth / MAP_LINE_DEPTH.max) * Math.min(1, fillOpacity / 20);
  const widthRatio = Math.min(1, depthWidth / MAP_LINE_DEPTH.max);

  context.save();
  context.globalCompositeOperation = 'multiply';
  for (const band of MAP_LINE_DEPTH.centerBands) {
    const edge = widthRatio * band.factor;
    fillRibbonBand(context, layout, { from: -edge, to: edge }, MAP_LINE_DEPTH.centerColor, band.alpha * strength);
  }

  if (widthRatio < 1) {
    context.globalCompositeOperation = 'screen';
    for (const side of [-1, 1] as const) {
      fillRibbonBand(
        context,
        layout,
        { from: side * 1, to: side * widthRatio },
        MAP_LINE_DEPTH.shallowColor,
        MAP_LINE_DEPTH.shallowAlpha * strength,
      );
      const inner = Math.max(widthRatio, MAP_LINE_DEPTH.shallowInnerRatio);
      if (inner > widthRatio + 0.01) {
        fillRibbonBand(
          context,
          layout,
          { from: side * 1, to: side * inner },
          MAP_LINE_DEPTH.shallowColor,
          MAP_LINE_DEPTH.shallowInnerAlpha * strength,
        );
      }
    }
  }
  context.restore();
  context.globalAlpha = 1;
}

function fillRibbonBand(
  context: CanvasRenderingContext2D,
  layout: RibbonLayout,
  band: { from: number; to: number },
  color: string,
  alpha: number,
): void {
  const points = mapRibbonService.ribbonBand(layout.flat, band.from, band.to);
  if (points.length < 3) return;
  tracePolygon(context, points);
  context.fillStyle = color;
  context.globalAlpha = Math.max(0, Math.min(1, alpha));
  context.fill();
  context.globalAlpha = 1;
}

function fillRibbonRange(
  context: CanvasRenderingContext2D,
  layout: RibbonLayout,
  from: number,
  to: number,
  fill: string | CanvasGradient,
): void {
  traceRibbonRange(context, layout.edges, from, to);
  context.fillStyle = fill;
  context.fill();
}

function fillRibbonTransition(
  context: CanvasRenderingContext2D,
  layout: RibbonLayout,
  transition: { from: number; to: number; fromColor: string; toColor: string },
): void {
  const gradient = transitionGradient(context, layout, transition);
  if (!gradient) return;
  fillRibbonRange(context, layout, transition.from, transition.to, gradient);
}

function transitionGradient(
  context: CanvasRenderingContext2D,
  layout: RibbonLayout,
  transition: { from: number; to: number; fromColor: string; toColor: string },
  shadeRatio = 0,
): CanvasGradient | null {
  const start = mapRibbonService.centerPoint(layout.flat, transition.from);
  const end = mapRibbonService.centerPoint(layout.flat, transition.to);
  if (Math.hypot(end[0] - start[0], end[1] - start[1]) < 1e-6) return null;
  const gradient = context.createLinearGradient(start[0], start[1], end[0], end[1]);
  const from = shadeRatio > 0 ? mapRibbonService.shade(transition.fromColor, shadeRatio) : transition.fromColor;
  const to = shadeRatio > 0 ? mapRibbonService.shade(transition.toColor, shadeRatio) : transition.toColor;
  gradient.addColorStop(0, from);
  gradient.addColorStop(1, to);

  return gradient;
}

/** Контур ленты: левый край вперёд, правый назад. */
function traceRibbonRange(context: CanvasRenderingContext2D, edges: RibbonEdges, from: number, to: number): void {
  const left = sliceEdge(edges.left, from, to);
  const right = sliceEdge(edges.right, from, to);
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
}

/** Кусок края между дробными индексами сэмплов. */
function sliceEdge(edge: MapPoint[], from: number, to: number): MapPoint[] {
  const points: MapPoint[] = [mapRibbonService.edgePoint(edge, from)];
  const start = Math.floor(from);
  const end = Math.ceil(to);
  for (let index = start + 1; index < end; index += 1) {
    if (index > from && index < to) points.push(edge[index]);
  }
  const last = mapRibbonService.edgePoint(edge, to);
  const previous = points[points.length - 1];
  if (Math.hypot(last[0] - previous[0], last[1] - previous[1]) > 1e-6) {
    points.push(last);
  }

  return points;
}

/** Рябь внутри клипа ленты: мягкие линзы, которые едут и мерцают. */
function drawLineFlow(context: CanvasRenderingContext2D, line: MapLine, layout: RibbonLayout): void {
  const flow = line.decor?.flow;
  if (!flow?.enabled || layout.flat.points.length < 2) return;
  const particles = mapRibbonService.flowParticles(line, layout);
  if (particles.length === 0) return;
  const glints = mapRibbonService.flowGlints(
    particles,
    layout.flat,
    drawTime,
    flow.speed,
    flow.direction,
    reducedMotion,
  );
  if (glints.length === 0) return;

  context.save();
  traceRibbonRange(context, layout.edges, 0, layout.flat.points.length - 1);
  context.clip();
  for (const glint of glints) {
    context.fillStyle = glint.color;
    context.globalAlpha = glint.alpha * 0.4;
    tracePolygon(context, glint.halo);
    context.fill();
    context.globalAlpha = glint.alpha * 0.7;
    tracePolygon(context, glint.points);
    context.fill();
  }
  context.restore();
  context.globalAlpha = 1;
}

/** Замыкает полигон по точкам. */
function tracePolygon(context: CanvasRenderingContext2D, points: MapPoint[]): void {
  if (points.length < 3) return;
  context.beginPath();
  points.forEach((point, index) => {
    if (index === 0) {
      context.moveTo(point[0], point[1]);
    } else {
      context.lineTo(point[0], point[1]);
    }
  });
  context.closePath();
}

/** Обводка только берегов; торцевые сечения не рисуются. */
function drawLineEdges(context: CanvasRenderingContext2D, layout: RibbonLayout): void {
  context.lineWidth = MAP_CANVAS_CONFIG.lineStrokeWidth / props.viewport.scale;
  context.lineJoin = 'round';
  context.lineCap = 'round';
  for (const run of layout.runs) {
    const color = mapRibbonService.shade(run.color, MAP_LINE_DEPTH.edgeShade);
    strokeEdgeRange(context, layout.edges.left, run.from, run.to, color);
    strokeEdgeRange(context, layout.edges.right, run.from, run.to, color);
  }
  for (const transition of layout.transitions) {
    const gradient = transitionGradient(context, layout, transition, MAP_LINE_DEPTH.edgeShade);
    if (!gradient) continue;
    strokeEdgeRange(context, layout.edges.left, transition.from, transition.to, gradient);
    strokeEdgeRange(context, layout.edges.right, transition.from, transition.to, gradient);
  }
}

function strokeEdgeRange(
  context: CanvasRenderingContext2D,
  edge: MapPoint[],
  from: number,
  to: number,
  color: string | CanvasGradient,
): void {
  const points = sliceEdge(edge, from, to);
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
  context.stroke();
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
  drawPolyline(context, smooth, '#d81b60', MAP_CANVAS_CONFIG.routeWidth / props.viewport.scale);
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
  const map = rawMap();
  if (!map) return;
  context.font = '12px Roboto, sans-serif';
  context.textAlign = 'center';
  context.textBaseline = 'middle';
  context.lineWidth = 3;
  context.strokeStyle = 'rgba(255, 255, 255, 0.85)';
  context.fillStyle = '#263238';
  const rect = visibleWorldRect();
  const selectedId = props.selected?.kind === 'area' ? props.selected.id : null;
  const live = liveAreaId();
  const scale = props.viewport.scale || 1;
  for (const area of map.areas) {
    if (area.id === live) continue;
    const label = area.label.trim();
    if (label === '') continue;
    const geometry = geometryOf(area);
    if (!geometry || !intersects(geometry, rect, 0)) continue;
    // Место и выделение подписаны всегда; остальные — когда область на экране
    // достаточно крупная, иначе тысячи подписей превращают карту в кашу.
    if (!props.showLabels && area.nodeId === null && area.id !== selectedId) {
      const extent = Math.max(geometry.maxX - geometry.minX, geometry.maxY - geometry.minY) * scale;
      if (extent < MAP_CANVAS_CONFIG.labelMinSize) continue;
    }
    const point = toScreen(mapGeometryService.polygonCentroid(geometry.path));
    context.strokeText(label, point.x, point.y);
    context.fillText(label, point.x, point.y);
  }
  context.textAlign = 'start';
}

function drawNodes(context: CanvasRenderingContext2D): void {
  const map = rawMap();
  const variant = rawVariant();
  if (!map || !variant) return;
  const points = placements();
  const linked = areaNodeIds();
  for (const node of map.nodes) {
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
      (candidate) => candidate.variantId === variant.id && candidate.isPresent,
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
  if (rawMap()?.canEdit !== true) return;
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
          drawBezierHandle(
            context,
            point,
            [point[0] + handle.out[0], point[1] + handle.out[1]],
            '#b39ddb',
            false,
            false,
          );
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
          drawBezierHandle(
            context,
            point,
            [point[0] + handle.out[0], point[1] + handle.out[1]],
            '#b39ddb',
            false,
            false,
          );
        }
      }
    });

    line.points.forEach((point, index) => {
      drawVertex(context, point, target?.kind === 'line-vertex' && target.id === line.id && target.index === index);
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
function drawWidthHandle(context: CanvasRenderingContext2D, base: MapPoint, target: MapPoint, active: boolean): void {
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
  emit('pointer-down', toWorld(screen), screen, event.button, event.altKey, event.shiftKey);
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
  () => [props.map, props.variant] as const,
  () => {
    renderMap = props.map === null ? null : (toRaw(props.map) as MapDetail);
    renderVariant = props.variant === null ? null : (toRaw(props.variant) as MapVariant);
  },
  { immediate: true },
);
// Карта и вариант меняются ссылкой; правки внутри черновика ловит revision.
watch(() => [props.map, props.variant, props.route], invalidateStatic);
watch(() => props.revision, invalidateStatic);
watch(() => props.liveDrag, (value) => {
  invalidateStatic();
  if (value !== null) {
    stopFlow();
  } else if (flowAvailable.value) {
    startFlow();
  }
});
watch(() => [props.selectedIds, props.marquee], () => scheduleDraw());
watch(() => props.showLabels, invalidateStatic);
// Вид — отдельно и неглубоко: панорама не должна обходить весь граф карты.
watch(
  () => [props.viewport.scale, props.viewport.offsetX, props.viewport.offsetY] as const,
  (next, previous) => {
    if (previous === undefined) {
      invalidateStatic();

      return;
    }
    staticDirty = true;
    // Изменился масштаб — показываем растянутый прошлый кадр и перерисовываем
    // чётко после затишья. Сдвиг без масштаба рисуем сразу с отсечением.
    if (next[0] !== previous[0]) scheduleZoomRepaint();
    scheduleDraw();
  },
);

watch(baseImage, invalidateStatic);
watch(
  () => [props.recognitionMask, props.recognitionPaths, props.recognitionExcluded],
  () => scheduleDraw(),
);

watch(
  () => [
    props.selected,
    props.selectedTarget,
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

watch(
  flowAvailable,
  (value) => {
    if (value) {
      startFlow();
    } else {
      stopFlow();
    }
  },
  { immediate: true },
);

function mount(): void {
  const container = wrapper.value;
  if (!container) return;
  resizeObserver = new ResizeObserver(resizeCanvas);
  resizeObserver.observe(container);
  document.addEventListener('visibilitychange', onVisibilityChange);
  resizeCanvas();
}

function unmount(): void {
  document.removeEventListener('visibilitychange', onVisibilityChange);
  stopFlow();
  resizeObserver?.disconnect();
  resizeObserver = null;
  if (zoomTimer !== null) {
    clearTimeout(zoomTimer);
    zoomTimer = null;
  }
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
