<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { ISceneRenderer } from '@/modules/Roleplay/Scene/Interface/ISceneRenderer';
import { createPixiSceneRenderer } from '@/modules/Roleplay/Scene/Service/Instance/pixiSceneRenderer';
import { sceneHitTestService } from '@/modules/Roleplay/Scene/Service/Instance/sceneHitTestService';
import { sceneViewportService } from '@/modules/Roleplay/Scene/Service/Instance/sceneViewportService';

const props = defineProps<{
  model: SceneRenderModel;
  board: SceneBoard | null;
}>();

const emit = defineEmits<{
  selection: [ids: number[]];
  viewport: [viewport: SceneViewport];
}>();

const host = ref<HTMLElement | null>(null);
const selection = ref<number[]>([]);
const renderError = ref<string | null>(null);
let renderer: ISceneRenderer | null = null;
let viewport: SceneViewport | null = null;
let observer: ResizeObserver | null = null;
let pointerDown = false;
let pointerMoved = false;
let lastX = 0;
let lastY = 0;

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
  sceneRenderer.setSelection(selection.value);
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
  if (event.button !== 0) return;
  pointerDown = true;
  pointerMoved = false;
  lastX = event.clientX;
  lastY = event.clientY;
  (event.currentTarget as HTMLElement | null)?.setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent): void {
  if (!pointerDown || viewport === null || renderer === null) return;
  const dx = event.clientX - lastX;
  const dy = event.clientY - lastY;
  if (Math.abs(dx) + Math.abs(dy) > 2) {
    pointerMoved = true;
  }

  lastX = event.clientX;
  lastY = event.clientY;
  viewport = sceneViewportService.pan(viewport, dx, dy);
  renderer.setViewport(viewport);
  emit('viewport', viewport);
}

function cancelPointer(): void {
  pointerDown = false;
  pointerMoved = false;
}

function onPointerUp(event: PointerEvent): void {
  if (!pointerDown) return;
  pointerDown = false;
  if (pointerMoved || viewport === null || renderer === null || host.value === null) return;
  const rect = host.value.getBoundingClientRect();
  const point = { x: event.clientX - rect.left, y: event.clientY - rect.top };
  const hit = sceneHitTestService.hit(props.model, viewport, point);
  selection.value = hit === null ? [] : [hit.id];
  renderer.setSelection(selection.value);
  emit('selection', selection.value);
}

function onWheel(event: WheelEvent): void {
  event.preventDefault();
  if (viewport === null || renderer === null || host.value === null) return;
  const rect = host.value.getBoundingClientRect();
  const point = { x: event.clientX - rect.left, y: event.clientY - rect.top };
  viewport = sceneViewportService.zoomAt(viewport, point, event.deltaY < 0 ? 1.12 : 0.9);
  renderer.setViewport(viewport);
  emit('viewport', viewport);
}

defineExpose({ fit, zoomBy });
</script>

<template>
  <div
    ref="host"
    class="scene-canvas"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointerleave="cancelPointer"
    @wheel="onWheel"
  >
    <div v-if="renderError" class="scene-canvas__error">{{ renderError }}</div>
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
</style>
