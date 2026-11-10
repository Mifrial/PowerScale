<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneHandleTarget } from '@/modules/Roleplay/Scene/Dto/SceneHandleTarget';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneVertexTarget } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import type { ISceneRenderer } from '@/modules/Roleplay/Scene/Interface/ISceneRenderer';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';
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
  }>(),
  { tool: 'select', interactive: false, pathWidth: 256 },
);

const emit = defineEmits<{
  selection: [ids: number[]];
  viewport: [viewport: SceneViewport];
  draw: [from: ScenePoint, to: ScenePoint];
  shape: [shape: SceneShape];
  path: [points: ScenePoint[]];
  move: [delta: ScenePoint];
  vertex: [elementId: number, index: number, point: ScenePoint];
  handle: [elementId: number, index: number, side: SceneHandleSide, offset: ScenePoint, mirror: boolean];
  width: [elementId: number, index: number, width: number];
  insertVertex: [elementId: number, point: ScenePoint, tolerance: number];
  removeVertex: [elementId: number, index: number];
  editStart: [];
  editEnd: [];
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
  sceneRenderer.setSelection(props.model.selection);
  observer = new ResizeObserver(() => handleResize());
  observer.observe(element);
  window.addEventListener('keydown', onClickKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onClickKeydown);
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
    emit('insertVertex', insertion.elementId, scenePoint, 10 / viewport.zoom);
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

/** Ближайшая вставка вершины у выделенной фигуры. */
function selectionInsertion(point: ScenePoint, tolerance: number): { elementId: number } | null {
  for (const element of selectedShapes()) {
    if (sceneVertexService.insertion(element.shape, point, tolerance) !== null) {
      return { elementId: element.id };
    }
  }

  return null;
}

/** Выделенные фигуры текущего поля, у которых есть вершины. */
function selectedShapes() {
  return props.model.elements.filter(
    (element) =>
      element.boardId === props.model.activeBoardId &&
      props.model.selection.includes(element.id) &&
      (element.shape.type === 'polygon' || element.shape.type === 'path'),
  );
}

/** Ручка Безье под точкой у активной вершины. */
function handleTargetAt(point: ScenePoint, tolerance: number, stub: number): SceneHandleTarget | null {
  if (vertexTarget === null) {
    return null;
  }

  const element = props.model.elements.find((item) => item.id === vertexTarget?.elementId);
  if (element === undefined) {
    return null;
  }

  const handle = sceneVertexService.handleAt(element.shape, point, tolerance, stub);
  if (handle === null || handle.index !== vertexTarget.index) {
    return null;
  }

  return { elementId: element.id, index: handle.index, side: handle.side };
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

  return index === vertexTarget.index ? { elementId: element.id, index } : null;
}

function vertexTargetAt(point: ScenePoint, tolerance: number): SceneVertexTarget | null {
  for (const element of selectedShapes()) {
    const index = sceneVertexService.vertexAt(element.shape, point, tolerance);
    if (index !== null) {
      return { elementId: element.id, index };
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

function startSelectGesture(event: PointerEvent, scenePoint: ScenePoint): void {
  if (viewport === null) return;
  const tolerance = 10 / viewport.zoom;
  const stub = 16 / viewport.zoom;

  const width = widthTargetAt(scenePoint, tolerance, 10 / viewport.zoom);
  if (width !== null) {
    widthTarget = width;
    setFocus({ elementId: width.elementId, index: width.index });
    gesture = 'width';
    emit('editStart');
    capture(event);

    return;
  }

  const handle = handleTargetAt(scenePoint, tolerance, stub);
  if (handle !== null) {
    handleTarget = handle;
    setFocus({ elementId: handle.elementId, index: handle.index });
    gesture = 'handle';
    emit('editStart');
    capture(event);

    return;
  }

  const vertex = vertexTargetAt(scenePoint, tolerance);
  if (vertex !== null) {
    setFocus(vertex);
    if (event.altKey) {
      emit('removeVertex', vertex.elementId, vertex.index);

      return;
    }

    gesture = 'vertex';
    emit('editStart');
    capture(event);

    return;
  }

  setFocus(null);
  const hit = sceneHitTestService.hit(props.model, viewport, screenPointOf(event));
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
  emit('editStart');
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
    emit('vertex', vertexTarget.elementId, vertexTarget.index, clampPoint(scenePoint));

    return;
  }

  if (gesture === 'handle' && handleTarget !== null) {
    const element = props.model.elements.find((item) => item.id === handleTarget?.elementId);
    if (element === undefined || (element.shape.type !== 'polygon' && element.shape.type !== 'path')) {
      return;
    }

    const vertex = element.shape.points[handleTarget.index];
    if (vertex === undefined) {
      return;
    }

    const offset: ScenePoint = [Math.round(scenePoint[0] - vertex[0]), Math.round(scenePoint[1] - vertex[1])];
    emit('handle', handleTarget.elementId, handleTarget.index, handleTarget.side, offset, !event.altKey);

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
    setFocus({ elementId: widthTarget.elementId, index: widthTarget.index });

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
    const hit = sceneHitTestService.hit(props.model, viewport, screenPointOf(event));
    applySelection(hit === null ? [] : [hit.id]);
  }

  if (editing) {
    emit('editEnd');
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
    emit('editEnd');
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
  (event.currentTarget as HTMLElement | null)?.setPointerCapture(event.pointerId);
}

function screenPointOf(event: PointerEvent | WheelEvent | MouseEvent): { x: number; y: number } {
  const rect = host.value?.getBoundingClientRect();

  return { x: event.clientX - (rect?.left ?? 0), y: event.clientY - (rect?.top ?? 0) };
}

function scenePointOf(event: PointerEvent | MouseEvent): ScenePoint | null {
  if (viewport === null) return null;

  return sceneViewportService.screenToScene(viewport, screenPointOf(event));
}

defineExpose({
  fit,
  zoomBy,
  debug: () => ({ gesture, vertexTarget, handleTarget, widthTarget }),
});
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
        <li><span class="scene-canvas__swatch scene-canvas__swatch--obstacle" />Препятствие</li>
        <li><span class="scene-canvas__swatch scene-canvas__swatch--support" />Опора</li>
        <li><span class="scene-canvas__swatch scene-canvas__swatch--terrain" />Террейн</li>
        <li><span class="scene-canvas__swatch scene-canvas__swatch--opening" />Проём</li>
        <li><span class="scene-canvas__swatch scene-canvas__swatch--decor" />Декор</li>
        <li><span class="scene-canvas__swatch scene-canvas__swatch--marker" />Маркер</li>
      </ul>
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

.scene-canvas__swatch--obstacle {
  background: #475569;
}

.scene-canvas__swatch--support {
  background: #b45309;
}

.scene-canvas__swatch--terrain {
  background: #4d7c0f;
}

.scene-canvas__swatch--opening {
  background: #dc2626;
}

.scene-canvas__swatch--decor {
  background: #a78bfa;
}

.scene-canvas__swatch--marker {
  background: #f59e0b;
}
</style>
