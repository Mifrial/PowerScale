<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneHandleTarget } from '@/modules/Roleplay/Scene/Dto/SceneHandleTarget';
import type { SceneLevelMenu } from '@/modules/Roleplay/Scene/Dto/SceneLevelMenu';
import type { SceneNavRoute } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { SceneOverlay } from '@/modules/Roleplay/Scene/Dto/SceneOverlay';
import type { SceneOverlayPaint } from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneRectHandle } from '@/modules/Roleplay/Scene/Dto/SceneRectHandle';
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
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneHitTestService } from '@/modules/Roleplay/Scene/Service/Instance/sceneHitTestService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import { sceneVertexService } from '@/modules/Roleplay/Scene/Service/Instance/sceneVertexService';
import { sceneViewportService } from '@/modules/Roleplay/Scene/Service/Instance/sceneViewportService';
import { sceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/Instance/sceneFloorPlanService';
import { isSceneRoof } from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { SCENE_FLOOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_FLOOR';

const props = withDefaults(
  defineProps<{
    model: SceneRenderModel;
    board: SceneBoard | null;
    tool?: SceneTool;
    interactive?: boolean;
    /** Ширина ленты для превью, единицы сцены. */
    pathWidth?: number;
    /** Толщина стены для превью, единицы сцены. */
    wallWidth?: number;
    /** Пользовательская настройка анимации воды. */
    waterAnimation?: boolean;
    /** Крыши скрыты: тумблер обзора в редакторе и на карте. */
    roofsHidden?: boolean;
    /** Предпросмотр: наведение подсвечивает площадки перехода. */
    preview?: boolean;
    /** Акторы видимых этажей: позиции и выделение приходят снаружи. */
    actors?: SceneActor[];
    /** Плита активного этажа полупрозрачна: сквозь неё виден этаж ниже. */
    slabCutaway?: boolean;
    /** Выбранный актор; от него считается туман и радиус обзора. */
    selectedActorId?: number | null;
    /** Наблюдатель стоит на проходимой крыше: уровни навигации известны снаружи. */
    observerOnRoof?: boolean;
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
    wallWidth: 16,
    waterAnimation: true,
    roofsHidden: false,
    preview: false,
    actors: () => [],
    slabCutaway: true,
    selectedActorId: null,
    observerOnRoof: false,
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
  resize: [elementId: number, handle: SceneRectHandle, point: ScenePoint];
  rotate: [elementId: number, angle: number];
  insertVertex: [elementId: number, point: ScenePoint, tolerance: number, part: SceneVertexPart];
  removeVertex: [elementId: number, index: number, part: SceneVertexPart];
  editStart: [];
  editEnd: [];
  point: [point: ScenePoint, under: boolean, at: ScenePoint, boardId: number];
  level: [level: number];
  pick: [scope: 'room' | 'building', point: ScenePoint];
  building: [elementId: number];
}>();

const host = ref<HTMLElement | null>(null);
const selection = ref<number[]>([]);
const renderError = ref<string | null>(null);
const legendOpen = ref(false);
const shapeBuilder = new SceneToolShape();
let renderer: ISceneRenderer | null = null;
let viewport: SceneViewport | null = null;
let observer: ResizeObserver | null = null;
let gesture: 'none' | 'pan' | 'draw' | 'move' | 'vertex' | 'handle' | 'width' | 'resize' | 'rotate' = 'none';
let lastX = 0;
let lastY = 0;
let drawStart: ScenePoint | null = null;
let moveLast: ScenePoint | null = null;
let panMoved = false;
let clickPoints: ScenePoint[] = [];
let clickHover: ScenePoint | null = null;
let clickClosed = false;
let vertexTarget: SceneVertexTarget | null = null;
let handleTarget: SceneHandleTarget | null = null;
let widthTarget: SceneVertexTarget | null = null;
let rectTarget: { elementId: number; handle: SceneRectHandle } | null = null;
let rotateTarget: { elementId: number } | null = null;

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
  { label: 'Крыша 100', color: cssColor(SCENE_OVERLAY.cost.roof.color) },
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

/**
 * Верхний элемент под экранной точкой.
 *
 * Скрытая крыша не ловится кликом: её картинки нет, значит и цели нет. Данные
 * при этом целы — навигация и сохранение крышу видят.
 */
function hitAt(screenPoint: { x: number; y: number }): SceneElement | null {
  const currentViewport = localViewport() ?? viewport;
  if (currentViewport === null) {
    return null;
  }

  const hits = sceneHitTestService.hits(
    props.model,
    currentViewport,
    screenPoint,
    8,
    (element) => renderer?.assetAspect(element.asset?.publicId ?? null) ?? null,
  );

  return hits.find((element) => !(props.roofsHidden && isSceneRoof(element))) ?? null;
}

const selectedActor = computed(() => (props.actors ?? []).find((actor) => actor.id === props.selectedActorId) ?? null);

/** Актор, с которого строится обзор: выбранный, иначе первый на активном поле. */
function observerActor(): SceneActor | null {
  const activeBoardId = props.model.activeBoardId;

  return (
    selectedActor.value ??
    (props.actors ?? []).find((actor) => (actor.boardId ?? activeBoardId) === activeBoardId) ??
    null
  );
}

/** Готовые оверлеи по полям; расчёт остаётся вне Pixi. */
function overlayPaint(): readonly SceneOverlayPaint[] | null {
  const overlay = props.overlay;
  if (overlay === null || props.board === null || props.model.mode === 'edit') {
    return null;
  }

  const actor = observerActor();
  const sightRadius = actor?.sightRadius;
  const effective =
    overlay.radius === null && sightRadius !== undefined ? { ...overlay, radius: sightRadius } : overlay;
  // Туман считается на всех видимых полях, поэтому наблюдатель несёт своё
  // поле: иначе чужие этажи проверялись бы лучом из середины активного поля.
  const observer =
    actor === null
      ? null
      : {
          boardId: actor.boardId ?? props.model.activeBoardId,
          point: [actor.x, actor.y] as ScenePoint,
          level: actor.level ?? 0,
          onRoof: props.selectedActorId === null ? undefined : props.observerOnRoof,
          height: actor.height,
        };

  return overlays.paints(props.model, effective, observer);
}

/** Пересобирает оверлей под текущие настройки и данные сцены. */
function refreshOverlay(): void {
  renderer?.setOverlayPaint(overlayPaint());
}

/**
 * Пересобирает туман по каденции.
 *
 * Пока актор идёт, `props.actors` меняется каждый кадр. Маска считается от
 * точной позиции, но не чаще `fogIntervalMs`: за это время она сдвигается на
 * доли клетки, и туман идёт за моделью плавно, без прыжков.
 */
function refreshFog(): void {
  if (!props.overlay?.visibility) {
    return;
  }

  if (fogRefresh.isDue(performance.now())) {
    if (fogTimer !== 0) {
      window.clearTimeout(fogTimer);
      fogTimer = 0;
    }
    refreshOverlay();

    return;
  }

  scheduleFogRefresh();
}

/**
 * Досчитывает туман после остановки.
 *
 * Если актор остановился раньше каденции, расчёт откладывается: иначе маска
 * осталась бы на позиции последнего тика, пока наблюдатель уже сдвинулся.
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
  sceneRenderer.setRoofsHidden(props.roofsHidden);
  sceneRenderer.setSelection(props.model.selection);
  sceneRenderer.setActors(props.actors ?? []);
  sceneRenderer.setSlabCutaway(props.slabCutaway);
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
  () => props.slabCutaway,
  (enabled) => renderer?.setSlabCutaway(enabled),
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
    props.observerOnRoof,
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
  () => props.roofsHidden,
  (hidden) => {
    renderer?.setRoofsHidden(hidden);
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
    // Смена этажа — смена фокуса, а не мира: камера остаётся на месте.
    // Вписать заново можно кнопкой «Вписать».
    cancelClickDraft();
    setFocus(null);
  },
);

function toolDefinition() {
  return SCENE_TOOLS.find((item) => item.id === props.tool) ?? null;
}

function clickShape(): 'polygon' | 'path' | 'wall' | null {
  if (props.shapeOverride !== null) {
    return props.shapeOverride;
  }

  const definition = toolDefinition();
  const shapeType = definition?.shapeType ?? null;
  if (shapeType === 'polygon' || shapeType === 'path') {
    return shapeType;
  }

  if (shapeType === 'wall' && definition?.wallMode !== 'ring') {
    return 'wall';
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
  clickClosed = false;
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

  if (clickShape() === 'wall') {
    renderer.setDraft({ type: 'wall', points, width: Math.max(1, props.wallWidth) });

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

  renderer.setDraft(
    shapeBuilder.build(
      definition.shapeType,
      drawStart,
      point,
      bounds,
      definition.shapeType === 'wall' ? props.wallWidth : undefined,
      definition.wallMode,
    ),
  );
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
    const tolerance = 10 / localZoom();
    if (Math.hypot(first[0] - next[0], first[1] - next[1]) <= tolerance) {
      clickClosed = shape === 'wall';
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
  const closed = clickClosed;
  cancelClickDraft();
  if (shape === 'polygon' && points.length >= 3) {
    emit('shape', { type: 'polygon', points });
  } else if (shape === 'path' && points.length >= 2) {
    emit('path', points);
  } else if (shape === 'wall' && points.length >= 2) {
    const loop = closed ? [...points, points[0]] : points;
    emit('shape', { type: 'wall', points: loop, width: Math.max(1, props.wallWidth) });
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

  const insertion = selectionInsertion(scenePoint, 10 / localZoom());
  if (insertion !== null) {
    emit('insertVertex', insertion.elementId, scenePoint, 10 / localZoom(), insertion.part);
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

    if (
      element.shape.type === 'polygon' ||
      element.shape.type === 'path' ||
      (element.kind === 'obstacle' && element.shape.type === 'wall')
    ) {
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

  return element.shape.type === 'polygon' || element.shape.type === 'path' || element.shape.type === 'wall'
    ? element.shape
    : null;
}

/** Ручка прямоугольника или поворота под точкой у выделенной фигуры. */
function rectHandleAt(
  point: ScenePoint,
  tolerance: number,
): { elementId: number; handle: SceneRectHandle | 'rotate' } | null {
  if (viewport === null) {
    return null;
  }

  for (const element of props.model.elements) {
    if (
      element.boardId !== props.model.activeBoardId ||
      !props.model.selection.includes(element.id) ||
      element.shape.type !== 'rect'
    ) {
      continue;
    }

    const rotation = sceneRotation(element);
    for (const handle of sceneGeometryService.rectHandles(element.shape, rotation)) {
      if (Math.hypot(handle.point[0] - point[0], handle.point[1] - point[1]) <= tolerance) {
        return { elementId: element.id, handle: handle.handle };
      }
    }

    if (element.kind !== 'support') {
      continue;
    }

    const center: ScenePoint = [element.shape.x + element.shape.w / 2, element.shape.y + element.shape.h / 2];
    const grip = rotatePoint([center[0], element.shape.y - 24 / localZoom()], center, rotation);
    if (Math.hypot(grip[0] - point[0], grip[1] - point[1]) <= tolerance) {
      return { elementId: element.id, handle: 'rotate' };
    }
  }

  return null;
}

/** Поворот точки вокруг центра на угол в градусах. */
function rotatePoint(point: ScenePoint, center: ScenePoint, degrees: number): ScenePoint {
  const radians = (degrees * Math.PI) / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);
  const dx = point[0] - center[0];
  const dy = point[1] - center[1];

  return [center[0] + dx * cosine - dy * sine, center[1] + dx * sine + dy * cosine];
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

/**
 * Кадр активного этажа в мире корня.
 *
 * Камера живёт в координатах корня: этаж вписывается по своему плану, а
 * результат переводится преобразованием вложения. Смена этажа сама камеру не
 * трогает — вписывание остаётся кнопкой.
 */
function fitViewport(size: { width: number; height: number }): SceneViewport {
  const board = props.board;
  if (board === null) {
    return { x: 0, y: 0, zoom: 1, ...size };
  }

  const bounds = contentBounds(board.id) ?? { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
  const local = sceneViewportService.fit(bounds, size);
  const transform = sceneFloorPlanService.transform(props.model, board.id);
  const scale = transform.scaleX === 0 ? 1 : transform.scaleX;

  return {
    x: transform.x + local.x * scale,
    y: transform.y + local.y * transform.scaleY,
    zoom: local.zoom * scale,
    width: size.width,
    height: size.height,
  };
}

/** Габарит террейна поля с полями обзора: по нему вписывается план этажа. */
function contentBounds(boardId: number): { x: number; y: number; width: number; height: number } | null {
  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;
  for (const element of props.model.elements) {
    if (element.boardId !== boardId || element.kind !== 'terrain') {
      continue;
    }

    const box = sceneGeometryService.bounds(element.shape, sceneRotation(element));
    minX = Math.min(minX, box.x);
    minY = Math.min(minY, box.y);
    maxX = Math.max(maxX, box.x + box.width);
    maxY = Math.max(maxY, box.y + box.height);
  }

  if (!Number.isFinite(minX) || !Number.isFinite(minY)) {
    return null;
  }

  const margin = Math.max(0, props.model.resolution * SCENE_FLOOR.fitMarginIpari);

  return {
    x: minX - margin,
    y: minY - margin,
    width: maxX - minX + margin * 2,
    height: maxY - minY + margin * 2,
  };
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

  if (props.tool === 'room' || props.tool === 'building') {
    // Область выбирает грань и сразу готова её тащить: клик без движения
    // оставляет выделение, протяжка двигает.
    emit('pick', props.tool, scenePoint);
    gesture = 'move';
    moveLast = scenePoint;
    startEditing();
    capture(event);

    return;
  }

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

/** Нажатие внутри уже выделенного: группа тянется целиком. */
function insideSelection(point: ScenePoint): boolean {
  if (selection.value.length < 2) {
    return false;
  }

  const board = props.board;
  for (const element of props.model.elements) {
    if (!selection.value.includes(element.id) || element.boardId !== props.model.activeBoardId) {
      continue;
    }

    // Террейн на всё поле содержит любую точку и перехватил бы нажатия у всей
    // группы, поэтому в липкое выделение он не входит.
    if (
      board !== null &&
      element.shape.type === 'rect' &&
      element.shape.x === 0 &&
      element.shape.y === 0 &&
      element.shape.w === board.boundWidth &&
      element.shape.h === board.boundHeight
    ) {
      continue;
    }

    if (sceneHitTestService.contains(element, point, 0, props.model.resolution, null)) {
      return true;
    }
  }

  return false;
}

function startSelectGesture(event: PointerEvent, scenePoint: ScenePoint): void {
  if (viewport === null) return;
  const tolerance = 10 / localZoom();
  const stub = 16 / localZoom();

  const rect = rectHandleAt(scenePoint, tolerance);
  if (rect !== null) {
    setFocus(null);
    if (rect.handle === 'rotate') {
      rotateTarget = { elementId: rect.elementId };
      gesture = 'rotate';
    } else {
      rectTarget = { elementId: rect.elementId, handle: rect.handle };
      gesture = 'resize';
    }

    startEditing();
    capture(event);

    return;
  }

  const width = widthTargetAt(scenePoint, tolerance, 10 / localZoom());
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

  // Нажатие внутри уже выделенной группы тянет её целиком, а не верхний
  // объект под курсором. Иначе дом, выбранный со всем содержимым, терялся бы
  // от клика по стоящему в нём дереву.
  if (!event.shiftKey && !event.ctrlKey && !event.metaKey && insideSelection(scenePoint)) {
    setFocus(null);
    gesture = 'move';
    moveLast = scenePoint;
    startEditing();
    capture(event);

    return;
  }

  setFocus(null);
  const hit = hitAt(screenPointOf(event));
  if (hit === null) {
    gesture = 'pan';
    panMoved = false;
    applySelection([]);
    capture(event);

    return;
  }

  if (event.altKey && hit.kind === 'obstacle' && hit.shape.type === 'wall') {
    emit('building', hit.id);

    return;
  }

  const additive = event.shiftKey || event.ctrlKey || event.metaKey;
  if (additive) {
    const nextIds = selection.value.includes(hit.id)
      ? selection.value.filter((id) => id !== hit.id)
      : [...selection.value, hit.id];
    applySelection(nextIds);
    if (!nextIds.includes(hit.id)) {
      return;
    }
  } else if (!selection.value.includes(hit.id)) {
    applySelection([hit.id]);
  }

  gesture = 'move';
  moveLast = scenePoint;
  startEditing();
  capture(event);
}

function onPointerMove(event: PointerEvent): void {
  if (gesture === 'none' && props.preview) {
    const scenePoint = scenePointOf(event);
    renderer?.setHoverPoint(scenePoint);
  }

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

  if (gesture === 'resize' && rectTarget !== null) {
    emit('resize', rectTarget.elementId, rectTarget.handle, clampPoint(scenePoint));

    return;
  }

  if (gesture === 'rotate' && rotateTarget !== null) {
    const element = props.model.elements.find((item) => item.id === rotateTarget?.elementId);
    if (element !== undefined && element.shape.type === 'rect') {
      const center: ScenePoint = [element.shape.x + element.shape.w / 2, element.shape.y + element.shape.h / 2];
      const angle = (Math.atan2(scenePoint[1] - center[1], scenePoint[0] - center[0]) * 180) / Math.PI + 90;
      emit('rotate', rotateTarget.elementId, angle);
    }

    return;
  }

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
  const editing =
    gesture === 'vertex' ||
    gesture === 'handle' ||
    gesture === 'width' ||
    gesture === 'move' ||
    gesture === 'resize' ||
    gesture === 'rotate';

  if (gesture === 'draw' && drawStart !== null) {
    const end = scenePointOf(event);
    renderer?.setDraft(null);
    if (end !== null) {
      emit('draw', drawStart, end);
    }
  }

  if (gesture === 'pan' && !props.interactive && !panMoved && viewport !== null) {
    const target = previewTarget(event);
    // Выделение элемента живёт на активном поле: клик по другому этажу не
    // должен выбирать то, что на активном оказалось под той же точкой экрана.
    if (target !== null && target.boardId === props.model.activeBoardId) {
      const hit = hitAt(screenPointOf(event));
      applySelection(hit === null ? [] : [hit.id]);
    }

    if (target !== null) {
      const at = screenPointOf(event);
      emit('point', target.point, event.altKey, [at.x, at.y], target.boardId);
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
  rectTarget = null;
  rotateTarget = null;
  panMoved = false;
}

function cancelPointer(): void {
  const editing =
    gesture === 'vertex' ||
    gesture === 'handle' ||
    gesture === 'width' ||
    gesture === 'move' ||
    gesture === 'resize' ||
    gesture === 'rotate';
  gesture = 'none';
  drawStart = null;
  moveLast = null;
  handleTarget = null;
  widthTarget = null;
  rectTarget = null;
  rotateTarget = null;
  panMoved = false;
  renderer?.setDraft(null);
  renderer?.setHoverPoint(null);
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

/**
 * Поле и точка под курсором в предпросмотре.
 *
 * Клик может прийтись на видимый нижний или верхний этаж: берётся верхний
 * нарисованный этаж, накрывающий точку. Инструменты редактора этим не
 * пользуются — они работают в координатах активного поля.
 */
function previewTarget(event: PointerEvent | MouseEvent): { point: ScenePoint; boardId: number } | null {
  if (viewport === null) return null;

  const world = sceneViewportService.screenToScene(viewport, screenPointOf(event));
  const plan = sceneFloorPlanService.plan(props.model);
  for (let index = plan.floors.length - 1; index >= 0; index -= 1) {
    const floor = plan.floors[index];
    const local = sceneFloorPlanService.toLocal(floor.transform, world);
    if (local[0] < 0 || local[1] < 0 || local[0] > floor.board.boundWidth || local[1] > floor.board.boundHeight) {
      continue;
    }

    return { point: local, boardId: floor.boardId };
  }

  const fallback = scenePointOf(event);

  return fallback === null ? null : { point: fallback, boardId: props.model.activeBoardId };
}

/**
 * Точка события в координатах активного поля.
 *
 * Мир — корень локации, а инструменты и навигация работают в координатах
 * своего поля. Пока фокус на корне, преобразование единичное.
 */
function scenePointOf(event: PointerEvent | MouseEvent): ScenePoint | null {
  if (viewport === null) return null;

  const world = sceneViewportService.screenToScene(viewport, screenPointOf(event));
  const transform = sceneFloorPlanService.transform(props.model, props.model.activeBoardId);

  return sceneFloorPlanService.toLocal(transform, world);
}

/** Масштаб активного поля: экранный пиксель в координатах поля. */
function localZoom(): number {
  if (viewport === null) return 1;
  const transform = sceneFloorPlanService.transform(props.model, props.model.activeBoardId);
  const scale = transform.scaleX === 0 ? 1 : transform.scaleX;

  return viewport.zoom * scale;
}

/** Вид, в котором координаты сцены — координаты активного поля. */
function localViewport(): SceneViewport | null {
  if (viewport === null) return null;
  const transform = sceneFloorPlanService.transform(props.model, props.model.activeBoardId);
  const scale = transform.scaleX === 0 ? 1 : transform.scaleX;

  return {
    ...viewport,
    x: (viewport.x - transform.x) / scale,
    y: (viewport.y - transform.y) / scale,
    zoom: viewport.zoom * scale,
  };
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
