<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import type { ISceneRenderer } from '@/modules/Roleplay/Scene/Interface/ISceneRenderer';
import { createPixiSceneRenderer } from '@/modules/Roleplay/Scene/Service/Instance/pixiSceneRenderer';
import { sceneHitTestService } from '@/modules/Roleplay/Scene/Service/Instance/sceneHitTestService';
import { sceneViewportService } from '@/modules/Roleplay/Scene/Service/Instance/sceneViewportService';

const props = withDefaults(
  defineProps<{
    model: SceneRenderModel;
    board: SceneBoard | null;
    tool?: SceneTool;
    interactive?: boolean;
  }>(),
  { tool: 'select', interactive: false },
);

const emit = defineEmits<{
  selection: [ids: number[]];
  viewport: [viewport: SceneViewport];
  draw: [from: ScenePoint, to: ScenePoint];
  move: [delta: ScenePoint];
}>();

const host = ref<HTMLElement | null>(null);
const selection = ref<number[]>([]);
const renderError = ref<string | null>(null);
const legendOpen = ref(false);
let renderer: ISceneRenderer | null = null;
let viewport: SceneViewport | null = null;
let observer: ResizeObserver | null = null;
let gesture: 'none' | 'pan' | 'draw' | 'move' = 'none';
let lastX = 0;
let lastY = 0;
let drawStart: ScenePoint | null = null;
let moveLast: ScenePoint | null = null;
let panMoved = false;

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
});

onBeforeUnmount(() => {
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
  },
);

watch(
  () => props.board?.id,
  () => {
    if (renderer === null || host.value === null) return;
    viewport = fitViewport(sizeOf(host.value));
    renderer.setViewport(viewport);
    emit('viewport', viewport);
  },
);

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

  if (props.tool === 'select') {
    startSelectGesture(event, scenePoint);
  } else {
    gesture = 'draw';
    drawStart = scenePoint;
    capture(event);
  }
}

function startSelectGesture(event: PointerEvent, scenePoint: ScenePoint): void {
  if (viewport === null) return;
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
  capture(event);
}

function onPointerMove(event: PointerEvent): void {
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

  if (gesture === 'move' && moveLast !== null) {
    const deltaX = Math.round(scenePoint[0] - moveLast[0]);
    const deltaY = Math.round(scenePoint[1] - moveLast[1]);
    if (deltaX !== 0 || deltaY !== 0) {
      moveLast = scenePoint;
      emit('move', [deltaX, deltaY]);
    }

    return;
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
  if (gesture === 'draw' && drawStart !== null) {
    const end = scenePointOf(event);
    if (end !== null) {
      emit('draw', drawStart, end);
    }
  }

  if (gesture === 'pan' && !props.interactive && !panMoved && viewport !== null) {
    const hit = sceneHitTestService.hit(props.model, viewport, screenPointOf(event));
    applySelection(hit === null ? [] : [hit.id]);
  }

  gesture = 'none';
  drawStart = null;
  moveLast = null;
  panMoved = false;
}

function cancelPointer(): void {
  gesture = 'none';
  drawStart = null;
  moveLast = null;
  panMoved = false;
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

function screenPointOf(event: PointerEvent | WheelEvent): { x: number; y: number } {
  const rect = host.value?.getBoundingClientRect();

  return { x: event.clientX - (rect?.left ?? 0), y: event.clientY - (rect?.top ?? 0) };
}

function scenePointOf(event: PointerEvent): ScenePoint | null {
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
