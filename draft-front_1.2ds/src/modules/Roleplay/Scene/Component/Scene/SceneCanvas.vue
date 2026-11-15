<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneHandleTarget } from '@/modules/Roleplay/Scene/Dto/SceneHandleTarget';
import type { SceneLevelMenu } from '@/modules/Roleplay/Scene/Dto/SceneLevelMenu';
import type { SceneNavRoute } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { SceneOverlay } from '@/modules/Roleplay/Scene/Dto/SceneOverlay';
import type { SceneOverlayPaint } from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneVertexPart, SceneVertexTarget } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import type { ISceneRenderer } from '@/modules/Roleplay/Scene/Interface/ISceneRenderer';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';
import { SceneOverlayService } from '@/modules/Roleplay/Scene/Service/SceneOverlayService';
import { SceneFogRefreshService } from '@/modules/Roleplay/Scene/Service/SceneFogRefreshService';
import { sceneFootprintShape, sceneSilhouetteShape } from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';
import { SceneToolShape } from '@/modules/Roleplay/Scene/Service/SceneToolShape';
import { createPixiSceneRenderer } from '@/modules/Roleplay/Scene/Service/Instance/pixiSceneRenderer';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneHitTestService } from '@/modules/Roleplay/Scene/Service/Instance/sceneHitTestService';
import { sceneVertexService } from '@/modules/Roleplay/Scene/Service/Instance/sceneVertexService';
import { sceneViewportService } from '@/modules/Roleplay/Scene/Service/Instance/sceneViewportService';

const props = withDefaults(
  defineProps<{
    model: SceneRenderModel;
    board: SceneBoard | null;
    tool?: SceneTool;
    interactive?: boolean;
    /** Ширина ленты для превью, единицы сцены. */
    pathWidth?: number;
    /** Пользовательская настройка анимации воды. */
    waterAnimation?: boolean;
    /** Акторы предпросмотра: позиции и выделение приходят снаружи. */
    actors?: SceneActor[];
    /** Выбранный актор; от него считается туман и радиус обзора. */
    selectedActorId?: number | null;
    /** Маршрут выбранного актора. */
    route?: SceneNavRoute | null;
    /** Тактические оверлеи предпросмотра; null — выключены. */
    overlay?: SceneOverlay | null;
    /** Меню выбора уровня под точкой клика; null — скрыто. */
    levelMenu?: SceneLevelMenu | null;
    /** Принудительный сбор полигона кликами: обводка основания декора. */
    shapeOverride?: 'polygon' | null;
  }>(),
  {
    tool: 'select',
    interactive: false,
    pathWidth: 256,
    waterAnimation: true,
    actors: () => [],
    selectedActorId: null,
    route: null,
    overlay: null,
    levelMenu: null,
    shapeOverride: null,
  },
);

const emit = defineEmits<{
  selection: [ids: number[]];
  viewport: [viewport: SceneViewport];
  draw: [from: ScenePoint, to: ScenePoint];
  shape: [shape: SceneShape];
  path: [points: ScenePoint[]];
  move: [delta: ScenePoint];
  vertex: [elementId: number, index: number, point: ScenePoint, part: SceneVertexPart];
  handle: [
    elementId: number,
    index: number,
    side: SceneHandleSide,
    offset: ScenePoint,
    mirror: boolean,
    part: SceneVertexPart,
  ];
  width: [elementId: number, index: number, width: number];
  insertVertex: [elementId: number, point: ScenePoint, tolerance: number, part: SceneVertexPart];
  removeVertex: [elementId: number, index: number, part: SceneVertexPart];
  editStart: [];
  editEnd: [];
  point: [point: ScenePoint, under: boolean, at: ScenePoint];
  level: [level: number];
}>();

const host = ref<HTMLElement | null>(null);
const selection = ref<number[]>([]);
const renderError = ref<string | null>(null);
const legendOpen = ref(false);
const shapeBuilder = new SceneToolShape();
let renderer: ISceneRenderer | null = null;
let viewport: SceneViewport | null = null;
let observer: ResizeObserver | null = null;
let gesture: 'none' | 'pan' | 'draw' | 'move' | 'vertex' | 'handle' | 'width' = 'none';
let lastX = 0;
let lastY = 0;
let drawStart: ScenePoint | null = null;
let moveLast: ScenePoint | null = null;
let panMoved = false;
let clickPoints: ScenePoint[] = [];
let clickHover: ScenePoint | null = null;
let vertexTarget: SceneVertexTarget | null = null;
let handleTarget: SceneHandleTarget | null = null;
let widthTarget: SceneVertexTarget | null = null;

const overlays = new SceneOverlayService();
const fogRefresh = new SceneFogRefreshService();
let fogTimer = 0;

/** Статья легенды: цвет и подпись. */
interface LegendEntry {
  label: string;
  color: string;
}

const BASE_LEGEND: LegendEntry[] = [
  { label: 'Препятствие', color: '#475569' },
  { label: 'Опора', color: '#b45309' },
  { label: 'Террейн', color: '#4d7c0f' },
  { label: 'Проём', color: '#dc2626' },
  { label: 'Декор', color: '#a78bfa' },
  { label: 'Маркер', color: '#f59e0b' },
];

const COST_LEGEND: LegendEntry[] = [
  { label: 'Луг 100', color: cssColor(SCENE_OVERLAY.cost.grass.color) },
  { label: 'Дорога 150', color: cssColor(SCENE_OVERLAY.cost.dirt.color) },
  { label: 'Мост 150', color: cssColor(SCENE_OVERLAY.cost.bridge.color) },
  { label: 'Река 20', color: cssColor(SCENE_OVERLAY.cost.water.color) },
  { label: 'Песок 80', color: cssColor(SCENE_OVERLAY.cost.sand.color) },
  { label: 'Грязь 50', color: cssColor(SCENE_OVERLAY.cost.mud.color) },
  { label: 'Непроходимо', color: cssColor(SCENE_OVERLAY.impassable.color) },
];

const legendEntries = computed<LegendEntry[]>(() => {
  const entries: LegendEntry[] = [];
  if (props.overlay?.costs) {
    entries.push(...COST_LEGEND);
  }
  if (props.overlay?.visibility) {
    entries.push({ label: 'Скрыто', color: cssColor(SCENE_OVERLAY.hidden.color) });
  }

  return entries.length > 0 ? entries : BASE_LEGEND;
});

/** Цвет числа Pixi в CSS-строку для легенды. */
function cssColor(color: number): string {
  return `#${color.toString(16).padStart(6, '0')}`;
}

const selectedActor = computed(() => (props.actors ?? []).find((actor) => actor.id === props.selectedActorId) ?? null);

/** Актор, с которого строится обзор: выбранный, иначе первый. */
function observerActor(): SceneActor | null {
  return selectedActor.value ?? props.actors?.[0] ?? null;
}

/** Готовые полосы оверлеев; расчёт остаётся вне Pixi. */
function overlayPaint(): SceneOverlayPaint | null {
  const overlay = props.overlay;
  const board = props.board;
  if (overlay === null || board === null || props.model.mode === 'edit') {
    return null;
  }

  const actor = observerActor();
  const sightRadius = actor?.sightRadius;
  const effective =
    overlay.radius === null && sightRadius !== undefined ? { ...overlay, radius: sightRadius } : overlay;
  const observer: ScenePoint | null = actor === null ? null : [actor.x, actor.y];

  return overlays.paint(
    board,
    props.model.elements,
    props.model.resolution,
    effective,
    observer,
    actor?.level ?? 0,
    actor?.height,
  );
}

/** Пересобирает оверлей под текущие настройки и данные сцены. */
function refreshOverlay(): void {
  renderer?.setOverlayPaint(overlayPaint());
}

/** Точка наблюдателя, от которой считается обзор. */
function observerPoint(): ScenePoint | null {
  const actor = observerActor();

  return actor === null ? null : [actor.x, actor.y];
}

/**
 * Пересобирает туман по смене грубой клетки наблюдателя.
 *
 * Пока актор идёт, `props.actors` меняется каждый кадр. Точный расчёт видимости
 * на каждое такое изменение — это микро-фризы: луч на каждую клетку обзора
 * стоит десятки миллисекунд, а сдвиг внутри клетки меняет маску на считанные
 * тексели.
 */
function refreshFog(): void {
  if (!props.overlay?.visibility) {
    return;
  }

  const now = performance.now();
  if (fogRefresh.isDue(observerPoint(), props.model.resolution, now)) {
    refreshOverlay();

    return;
  }

  if (fogRefresh.isCellChanged(observerPoint(), props.model.resolution)) {
    scheduleFogRefresh();
  }
}

/**
 * Досчитывает туман после остановки.
 *
 * Если актор перешёл в новую клетку раньше минимального интервала, расчёт
 * откладывается: иначе после остановки маска осталась бы сдвинутой на клетку.
 */
function scheduleFogRefresh(): void {
  if (fogTimer !== 0) {
    return;
  }

  fogTimer = window.setTimeout(() => {
    fogTimer = 0;
    refreshFog();
  }, SCENE_OVERLAY.fogIntervalMs);
}

/** Пересобирает оверлей принудительно: настройки, радиус, данные сцены. */
function refreshOverlayNow(): void {
  fogRefresh.reset();
  if (fogTimer !== 0) {
    window.clearTimeout(fogTimer);
    fogTimer = 0;
  }
  refreshOverlay();
}

onMounted(async () => {
  const element = host.value;
  if (element === null) return;

  const sceneRenderer = createPixiSceneRenderer();
  renderer = sceneRenderer;
  try {
    await sceneRenderer.attach(element);
  } catch {
    renderError.value = 'WebGL недоступен: браузер не создал контекст рендера.';

    return;
  }

  const size = sizeOf(element);
  viewport = fitViewport(size);
  sceneRenderer.resize(size.width, size.height, window.devicePixelRatio || 1);
  sceneRenderer.setViewport(viewport);
  await sceneRenderer.setScene(props.model);
  sceneRenderer.setWaterAnimation(props.waterAnimation);
  sceneRenderer.setSelection(props.model.selection);
  sceneRenderer.setActors(props.actors ?? []);
  sceneRenderer.setRoute(props.route ?? null);
  refreshOverlay();
  observer = new ResizeObserver(() => handleResize());
  observer.observe(element);
  window.addEventListener('keydown', onClickKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onClickKeydown);
  if (fogTimer !== 0) {
    window.clearTimeout(fogTimer);
    fogTimer = 0;
  }
  observer?.disconnect();
  observer = null;
  renderer?.destroy();
  renderer = null;
});

watch(
  [
    () => props.model.activeBoardId,
    () => props.model.mode,
    () => props.model.boards,
    () => props.model.elements,
    () => props.model.enclosures,
  ],
  async () => {
    if (renderer === null) return;
    await renderer.setScene(props.model);
    renderer.setSelection(selection.value);
    renderer.setVertexFocus(vertexTarget);
    if (viewport !== null) {
      renderer.setViewport(viewport);
    }

    renderer.setActors(props.actors ?? []);
    renderer.setRoute(props.route ?? null);
    refreshOverlayNow();
  },
);

watch(
  () => props.actors,
  () => {
    renderer?.setActors(props.actors ?? []);
    refreshFog();
  },
);

watch(
  () => props.route,
  () => renderer?.setRoute(props.route ?? null),
);

watch(
  () => [
    props.selectedActorId,
    selectedActor.value?.sightRadius,
    selectedActor.value?.level,
    selectedActor.value?.height,
    props.overlay?.visibility,
  ],
  () => {
    if (props.overlay?.visibility) {
      refreshOverlayNow();
    }
  },
);

watch(
  () => props.overlay,
  () => refreshOverlayNow(),
);

watch(
  () => [props.overlay?.costs ?? false, props.overlay?.visibility ?? false] as const,
  ([costs, visibility]) => {
    if (costs || visibility) {
      legendOpen.value = true;
    }
  },
);

watch(
  () => props.model.selection,
  (ids) => {
    selection.value = [...ids];
    renderer?.setSelection(selection.value);
    if (vertexTarget !== null && !ids.includes(vertexTarget.elementId)) {
      setFocus(null);
    }
  },
);

watch(
  () => props.waterAnimation,
  (enabled) => {
    renderer?.setWaterAnimation(enabled);
  },
);

watch(
  () => props.tool,
  () => {
    cancelClickDraft();
    setFocus(null);
  },
);

watch(
  () => props.board?.id,
  () => {
    cancelClickDraft();
    setFocus(null);
    if (renderer === null || host.value === null) return;
    viewport = fitViewport(sizeOf(host.value));
    renderer.setViewport(viewport);
    emit('viewport', viewport);
  },
);

function toolDefinition() {
  return SCENE_TOOLS.find((item) => item.id === props.tool) ?? null;
}

function clickShape(): 'polygon' | 'path' | null {
  if (props.shapeOverride !== null) {
    return props.shapeOverride;
  }

  const shapeType = toolDefinition()?.shapeType ?? null;
  if (shapeType === 'polygon' || shapeType === 'path') {
    return shapeType;
  }

  return null;
}

function boardBounds(): { x: number; y: number; width: number; height: number } | null {
  const board = props.board;
  if (board === null) {
    return null;
  }

  return { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
}

function clampPoint(point: ScenePoint): ScenePoint {
  const bounds = boardBounds();
  if (bounds === null) {
    return [Math.round(point[0]), Math.round(point[1])];
  }

  return [
    Math.min(bounds.width, Math.max(0, Math.round(point[0]))),
    Math.min(bounds.height, Math.max(0, Math.round(point[1]))),
  ];
}

/** Запоминает активную вершину и показывает её ручки в рендерере. */
function setFocus(target: SceneVertexTarget | null): void {
  vertexTarget = target;
  renderer?.setVertexFocus(target);
}

/** Сбрасывает незавершённую кликовую фигуру и превью. */
function cancelClickDraft(): void {
  clickPoints = [];
  clickHover = null;
  renderer?.setDraft(null);
}

/** Обновляет превью полигона или ленты по накопленным точкам и курсору. */
function refreshClickDraft(): void {
  if (renderer === null) {
    return;
  }

  if (clickShape() === null || clickPoints.length === 0) {
    renderer.setDraft(null);

    return;
  }

  const points = clickHover === null ? clickPoints : [...clickPoints, clickHover];
  if (clickShape() === 'path') {
    renderer.setDraft({
      type: 'path',
      points,
      widths: points.map(() => Math.max(1, props.pathWidth)),
      handles: sceneCurveService.autoHandles(points),
    });

    return;
  }

  renderer.setDraft({ type: 'polygon', points });
}

/** Превью прямоугольника, круга или стены во время протяжки. */
function refreshDragDraft(point: ScenePoint): void {
  const definition = toolDefinition();
  const bounds = boardBounds();
  if (
    renderer === null ||
    definition === null ||
    definition.shapeType === null ||
    drawStart === null ||
    bounds === null
  ) {
    renderer?.setDraft(null);

    return;
  }

  renderer.setDraft(shapeBuilder.build(definition.shapeType, drawStart, point, bounds));
}

function addClickPoint(point: ScenePoint): void {
  const shape = clickShape();
  if (shape === null) {
    return;
  }

  const next = clampPoint(point);
  const minimum = shape === 'polygon' ? 3 : 2;
  if (clickPoints.length >= minimum && viewport !== null) {
    const first = clickPoints[0];
    const tolerance = 10 / viewport.zoom;
    if (Math.hypot(first[0] - next[0], first[1] - next[1]) <= tolerance) {
      finishClickShape();

      return;
    }
  }

  clickPoints = [...clickPoints, next];
  refreshClickDraft();
}

function finishClickShape(): void {
  const shape = clickShape();
  const points = clickPoints.filter(
    (point, index) =>
      index === 0 || Math.hypot(point[0] - clickPoints[index - 1][0], point[1] - clickPoints[index - 1][1]) > 0,
  );
  cancelClickDraft();
  if (shape === 'polygon' && points.length >= 3) {
    emit('shape', { type: 'polygon', points });
  } else if (shape === 'path' && points.length >= 2) {
    emit('path', points);
  }
}

/** Двойной клик замыкает полигон или завершает ленту. */
function onDoubleClick(event: MouseEvent): void {
  if (clickShape() !== null) {
    finishClickShape();

    return;
  }

  if (props.tool !== 'select') {
    return;
  }

  const scenePoint = scenePointOf(event);
  if (scenePoint === null || viewport === null) {
    return;
  }

  const insertion = selectionInsertion(scenePoint, 10 / viewport.zoom);
  if (insertion !== null) {
    emit('insertVertex', insertion.elementId, scenePoint, 10 / viewport.zoom, insertion.part);
  }
}

function onClickKeydown(event: KeyboardEvent): void {
  if (clickShape() === null || clickPoints.length === 0) {
    return;
  }

  if (event.key === 'Escape') {
    cancelClickDraft();

    return;
  }

  if (event.key === 'Enter') {
    event.preventDefault();
    finishClickShape();
  }
}

/** Контур, доступный редактированию вершин. */
interface EditableContour {
  elementId: number;
  part: SceneVertexPart;
  shape: SceneShape;
}

/** Ближайшая вставка вершины у выделенной фигуры или основания. */
function selectionInsertion(point: ScenePoint, tolerance: number): { elementId: number; part: SceneVertexPart } | null {
  for (const contour of selectedContours()) {
    if (sceneVertexService.insertion(contour.shape, point, tolerance) !== null) {
      return { elementId: contour.elementId, part: contour.part };
    }
  }

  return null;
}

/** Выделенные контуры: фигуры и полигоны оснований. */
function selectedContours(): EditableContour[] {
  const contours: EditableContour[] = [];
  for (const element of props.model.elements) {
    if (element.boardId !== props.model.activeBoardId || !props.model.selection.includes(element.id)) {
      continue;
    }

    if (element.shape.type === 'polygon' || element.shape.type === 'path') {
      contours.push({ elementId: element.id, part: 'shape', shape: element.shape });
    }

    if (element.kind === 'decor' && element.shape.type === 'point') {
      const footprint = sceneFootprintShape(element, props.model.resolution);
      if (footprint !== null) {
        contours.push({ elementId: element.id, part: 'footprint', shape: footprint });
      }

      // Неявный силуэт совпадает с основанием: его вершины лежат на вершинах
      // основания, и перетаскивание попало бы в чужую зону. Правим только
      // отделённую копию.
      const silhouette =
        element.props.blocksVision === false || element.props.silhouette === undefined
          ? null
          : sceneSilhouetteShape(element, props.model.resolution);
      if (silhouette !== null) {
        contours.push({ elementId: element.id, part: 'silhouette', shape: silhouette });
      }
    }
  }

  return contours;
}

/** Контур по элементу и части; null — у части нет вершин. */
function contourShape(elementId: number, part: SceneVertexPart): SceneShape | null {
  const element = props.model.elements.find((item) => item.id === elementId);
  if (element === undefined) {
    return null;
  }

  if (part === 'footprint') {
    return sceneFootprintShape(element, props.model.resolution);
  }

  if (part === 'silhouette') {
    return sceneSilhouetteShape(element, props.model.resolution);
  }

  return element.shape.type === 'polygon' || element.shape.type === 'path' ? element.shape : null;
}

/** Ручка Безье под точкой у активной вершины. */
function handleTargetAt(point: ScenePoint, tolerance: number, stub: number): SceneHandleTarget | null {
  if (vertexTarget === null) {
    return null;
  }

  const part = vertexTarget.part ?? 'shape';
  const shape = contourShape(vertexTarget.elementId, part);
  if (shape === null) {
    return null;
  }

  const handle = sceneVertexService.handleAt(shape, point, tolerance, stub);
  if (handle === null || handle.index !== vertexTarget.index) {
    return null;
  }

  return { elementId: vertexTarget.elementId, part, index: handle.index, side: handle.side };
}

function widthTargetAt(point: ScenePoint, tolerance: number, offset: number): SceneVertexTarget | null {
  if (vertexTarget === null) {
    return null;
  }

  const element = props.model.elements.find((item) => item.id === vertexTarget?.elementId);
  if (element === undefined || element.shape.type !== 'path') {
    return null;
  }

  const index = sceneVertexService.widthAt(element.shape, point, tolerance, offset);

  return index === vertexTarget.index ? { elementId: element.id, part: 'shape', index } : null;
}

function vertexTargetAt(point: ScenePoint, tolerance: number): SceneVertexTarget | null {
  for (const contour of selectedContours()) {
    const index = sceneVertexService.vertexAt(contour.shape, point, tolerance);
    if (index !== null) {
      return { elementId: contour.elementId, part: contour.part, index };
    }
  }

  return null;
}

function handleResize(): void {
  if (renderer === null || host.value === null) return;
  const size = sizeOf(host.value);
  viewport = viewport === null ? fitViewport(size) : sceneViewportService.resize(viewport, size);
  renderer.resize(size.width, size.height, window.devicePixelRatio || 1);
  renderer.setViewport(viewport);
  emit('viewport', viewport);
}

function sizeOf(element: HTMLElement): { width: number; height: number } {
  return { width: Math.max(element.clientWidth, 1), height: Math.max(element.clientHeight, 1) };
}

function fitViewport(size: { width: number; height: number }): SceneViewport {
  const board = props.board;
  if (board === null) {
    return { x: 0, y: 0, zoom: 1, ...size };
  }

  return sceneViewportService.fit({ x: 0, y: 0, width: board.boundWidth, height: board.boundHeight }, size);
}

function fit(): void {
  if (renderer === null || host.value === null) return;
  viewport = fitViewport(sizeOf(host.value));
  renderer.setViewport(viewport);
  emit('viewport', viewport);
}

function zoomBy(factor: number): void {
  if (renderer === null || viewport === null || host.value === null) return;
  const size = sizeOf(host.value);
  viewport = sceneViewportService.zoomAt(viewport, { x: size.width / 2, y: size.height / 2 }, factor);
  renderer.setViewport(viewport);
  emit('viewport', viewport);
}

function onPointerDown(event: PointerEvent): void {
  if (viewport === null || host.value === null) return;
  lastX = event.clientX;
  lastY = event.clientY;

  if (event.button === 1 || !props.interactive) {
    gesture = 'pan';
    panMoved = false;
    capture(event);

    return;
  }

  if (event.button !== 0) return;
  const scenePoint = scenePointOf(event);
  if (scenePoint === null) return;

  if (clickShape() !== null) {
    addClickPoint(scenePoint);

    return;
  }

  if (props.tool === 'select') {
    startSelectGesture(event, scenePoint);
  } else {
    gesture = 'draw';
    drawStart = scenePoint;
    refreshDragDraft(scenePoint);
    capture(event);
  }
}

/** Жест меняет геометрию: на это время рендерер не пересобирает траву. */
function startEditing(): void {
  renderer?.setEditing(true);
  emit('editStart');
}

/** Жест завершён: трава возвращается на место. */
function stopEditing(): void {
  renderer?.setEditing(false);
  emit('editEnd');
}

function startSelectGesture(event: PointerEvent, scenePoint: ScenePoint): void {
  if (viewport === null) return;
  const tolerance = 10 / viewport.zoom;
  const stub = 16 / viewport.zoom;

  const width = widthTargetAt(scenePoint, tolerance, 10 / viewport.zoom);
  if (width !== null) {
    widthTarget = width;
    setFocus({ elementId: width.elementId, part: 'shape', index: width.index });
    gesture = 'width';
    startEditing();
    capture(event);

    return;
  }

  const handle = handleTargetAt(scenePoint, tolerance, stub);
  if (handle !== null) {
    handleTarget = handle;
    setFocus({ elementId: handle.elementId, part: handle.part, index: handle.index });
    gesture = 'handle';
    startEditing();
    capture(event);

    return;
  }

  const vertex = vertexTargetAt(scenePoint, tolerance);
  if (vertex !== null) {
    setFocus(vertex);
    if (event.altKey) {
      emit('removeVertex', vertex.elementId, vertex.index, vertex.part ?? 'shape');

      return;
    }

    gesture = 'vertex';
    startEditing();
    capture(event);

    return;
  }

  setFocus(null);
  const hit = sceneHitTestService.hit(
    props.model,
    viewport,
    screenPointOf(event),
    8,
    (element) => renderer?.assetAspect(element.asset?.publicId ?? null) ?? null,
  );
  if (hit === null) {
    gesture = 'pan';
    panMoved = false;
    applySelection([]);
    capture(event);

    return;
  }

  const additive = event.shiftKey || event.ctrlKey || event.metaKey;
  const nextIds = additive
    ? selection.value.includes(hit.id)
      ? selection.value.filter((id) => id !== hit.id)
      : [...selection.value, hit.id]
    : [hit.id];
  applySelection(nextIds);
  gesture = 'move';
  moveLast = scenePoint;
  startEditing();
  capture(event);
}

function onPointerMove(event: PointerEvent): void {
  if (gesture === 'none' && clickShape() !== null && clickPoints.length > 0) {
    const scenePoint = scenePointOf(event);
    if (scenePoint !== null) {
      clickHover = clampPoint(scenePoint);
      refreshClickDraft();
    }

    return;
  }

  if (gesture === 'none' || viewport === null || renderer === null) return;

  if (gesture === 'pan') {
    const deltaX = event.clientX - lastX;
    const deltaY = event.clientY - lastY;
    if (Math.abs(deltaX) + Math.abs(deltaY) > 2) {
      panMoved = true;
    }

    panBy(event);

    return;
  }

  const scenePoint = scenePointOf(event);
  if (scenePoint === null) return;

  if (gesture === 'vertex' && vertexTarget !== null) {
    emit('vertex', vertexTarget.elementId, vertexTarget.index, clampPoint(scenePoint), vertexTarget.part ?? 'shape');

    return;
  }

  if (gesture === 'handle' && handleTarget !== null) {
    const part = handleTarget.part ?? 'shape';
    const shape = contourShape(handleTarget.elementId, part);
    if (shape === null || (shape.type !== 'polygon' && shape.type !== 'path')) {
      return;
    }

    const vertex = shape.points[handleTarget.index];
    if (vertex === undefined) {
      return;
    }

    const offset: ScenePoint = [Math.round(scenePoint[0] - vertex[0]), Math.round(scenePoint[1] - vertex[1])];
    emit('handle', handleTarget.elementId, handleTarget.index, handleTarget.side, offset, !event.altKey, part);

    return;
  }

  if (gesture === 'width' && widthTarget !== null) {
    const element = props.model.elements.find((item) => item.id === widthTarget?.elementId);
    if (element === undefined || element.shape.type !== 'path') {
      return;
    }

    const vertex = element.shape.points[widthTarget.index];
    if (vertex === undefined) {
      return;
    }

    const width = Math.max(2, Math.round(Math.hypot(scenePoint[0] - vertex[0], scenePoint[1] - vertex[1]) * 2));
    emit('width', widthTarget.elementId, widthTarget.index, width);
    setFocus({ elementId: widthTarget.elementId, part: 'shape', index: widthTarget.index });

    return;
  }

  if (gesture === 'move' && moveLast !== null) {
    const deltaX = Math.round(scenePoint[0] - moveLast[0]);
    const deltaY = Math.round(scenePoint[1] - moveLast[1]);
    if (deltaX !== 0 || deltaY !== 0) {
      moveLast = scenePoint;
      emit('move', [deltaX, deltaY]);
    }

    return;
  }

  if (gesture === 'draw') {
    refreshDragDraft(scenePoint);
  }
}

function panBy(event: PointerEvent): void {
  if (viewport === null || renderer === null) return;
  const deltaX = event.clientX - lastX;
  const deltaY = event.clientY - lastY;
  lastX = event.clientX;
  lastY = event.clientY;
  viewport = sceneViewportService.pan(viewport, deltaX, deltaY);
  renderer.setViewport(viewport);
  emit('viewport', viewport);
}

function onPointerUp(event: PointerEvent): void {
  const editing = gesture === 'vertex' || gesture === 'handle' || gesture === 'width' || gesture === 'move';

  if (gesture === 'draw' && drawStart !== null) {
    const end = scenePointOf(event);
    renderer?.setDraft(null);
    if (end !== null) {
      emit('draw', drawStart, end);
    }
  }

  if (gesture === 'pan' && !props.interactive && !panMoved && viewport !== null) {
    const hit = sceneHitTestService.hit(
      props.model,
      viewport,
      screenPointOf(event),
      8,
      (element) => renderer?.assetAspect(element.asset?.publicId ?? null) ?? null,
    );
    applySelection(hit === null ? [] : [hit.id]);
    const point = scenePointOf(event);
    if (point !== null) {
      const at = screenPointOf(event);
      emit('point', point, event.altKey, [at.x, at.y]);
    }
  }

  if (editing) {
    stopEditing();
  }

  gesture = 'none';
  drawStart = null;
  moveLast = null;
  handleTarget = null;
  widthTarget = null;
  panMoved = false;
}

function cancelPointer(): void {
  const editing = gesture === 'vertex' || gesture === 'handle' || gesture === 'width' || gesture === 'move';
  gesture = 'none';
  drawStart = null;
  moveLast = null;
  handleTarget = null;
  widthTarget = null;
  panMoved = false;
  renderer?.setDraft(null);
  if (editing) {
    stopEditing();
  }
}

function onWheel(event: WheelEvent): void {
  event.preventDefault();
  if (viewport === null || renderer === null || host.value === null) return;
  viewport = sceneViewportService.zoomAt(viewport, screenPointOf(event), event.deltaY < 0 ? 1.12 : 0.9);
  renderer.setViewport(viewport);
  emit('viewport', viewport);
}

function applySelection(ids: number[]): void {
  selection.value = ids;
  renderer?.setSelection(ids);
  emit('selection', ids);
}

function capture(event: PointerEvent): void {
  try {
    (event.currentTarget as HTMLElement | null)?.setPointerCapture(event.pointerId);
  } catch {
    // Синтетическое событие или потерянный pointerId: жест всё равно работает.
  }
}

function screenPointOf(event: PointerEvent | WheelEvent | MouseEvent): { x: number; y: number } {
  const rect = host.value?.getBoundingClientRect();

  return { x: event.clientX - (rect?.left ?? 0), y: event.clientY - (rect?.top ?? 0) };
}

function scenePointOf(event: PointerEvent | MouseEvent): ScenePoint | null {
  if (viewport === null) return null;

  return sceneViewportService.screenToScene(viewport, screenPointOf(event));
}

defineExpose({ fit, zoomBy });
</script>

<template>
  <div
    ref="host"
    class="scene-canvas"
    :class="{ 'scene-canvas--edit': props.interactive }"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointerleave="cancelPointer"
    @dblclick="onDoubleClick"
    @wheel="onWheel"
  >
    <div v-if="renderError" class="scene-canvas__error">{{ renderError }}</div>

    <div class="scene-canvas__legend" @pointerdown.stop @wheel.stop>
      <button class="scene-canvas__legend-toggle" type="button" @click="legendOpen = !legendOpen">?</button>
      <ul v-if="legendOpen" class="scene-canvas__legend-list">
        <li v-for="entry in legendEntries" :key="entry.label">
          <span class="scene-canvas__swatch" :style="{ background: entry.color }" />{{ entry.label }}
        </li>
      </ul>
    </div>

    <div
      v-if="props.levelMenu"
      class="scene-canvas__levels"
      :style="{ left: `${props.levelMenu.x}px`, top: `${props.levelMenu.y}px` }"
      @pointerdown.stop
      @wheel.stop
    >
      <button
        v-for="option in props.levelMenu.options"
        :key="option.level"
        type="button"
        class="scene-canvas__level"
        @click="emit('level', option.level)"
      >
        {{ option.label }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.scene-canvas {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  touch-action: none;
  background: #0f172a;
}

.scene-canvas--edit {
  cursor: crosshair;
}

.scene-canvas__error {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  color: #fecaca;
  text-align: center;
}

.scene-canvas__legend {
  position: absolute;
  left: 12px;
  bottom: 12px;
  z-index: 2;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 8px 10px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.86);
  color: #e2e8f0;
  font-size: 0.72rem;
  pointer-events: none;
}

.scene-canvas__legend-toggle {
  pointer-events: auto;
  width: 22px;
  height: 22px;
  border: 1px solid rgba(148, 163, 184, 0.45);
  border-radius: 999px;
  background: transparent;
  color: inherit;
  font-size: 0.75rem;
  line-height: 1;
  cursor: pointer;
}

.scene-canvas__legend-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
  margin: 2px 0 0;
  padding: 0;
  list-style: none;
}

.scene-canvas__legend-list li {
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}

.scene-canvas__swatch {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  border: 1px solid rgba(255, 255, 255, 0.7);
}

.scene-canvas__levels {
  position: absolute;
  z-index: 4;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 4px;
  border: 1px solid rgba(148, 163, 184, 0.45);
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.94);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
  transform: translate(4px, 4px);
}

.scene-canvas__level {
  padding: 4px 10px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: #e2e8f0;
  font-size: 0.75rem;
  line-height: 1.2;
  text-align: left;
  white-space: nowrap;
  cursor: pointer;
}

.scene-canvas__level:hover {
  background: rgba(96, 165, 250, 0.2);
}
</style>
