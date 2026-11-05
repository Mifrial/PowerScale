<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import {
  clampViewport,
  cropFromViewport,
  minScale,
  viewportFromCrop,
  zoomOf,
  zoomTo,
  type CropImageSize,
  type CropViewport,
} from '@/modules/Core/User/Service/AvatarCropMath';

const props = withDefaults(
  defineProps<{
    src: string;
    initialCrop?: AvatarCrop | null;
  }>(),
  { initialCrop: null },
);

const emit = defineEmits<{
  change: [crop: AvatarCrop | null];
}>();

const stageEl = ref<HTMLElement | null>(null);
const imageEl = ref<HTMLImageElement | null>(null);
const stageSize = ref(320);
const imageSize = ref<CropImageSize | null>(null);
const viewport = ref<CropViewport>({ scale: 1, offsetX: 0, offsetY: 0 });
const zoom = ref(1);
const ready = ref(false);

let observer: ResizeObserver | null = null;
let dragStart: { x: number; y: number; offsetX: number; offsetY: number } | null = null;

function applyViewport(next: CropViewport): void {
  if (!imageSize.value) return;

  viewport.value = clampViewport(next, stageSize.value, imageSize.value);
  zoom.value = zoomOf(viewport.value, stageSize.value, imageSize.value);
  emit('change', cropFromViewport(viewport.value, stageSize.value, imageSize.value));
}

function setupImage(image: HTMLImageElement): void {
  if (image.naturalWidth < 1 || image.naturalHeight < 1) return;

  imageSize.value = { width: image.naturalWidth, height: image.naturalHeight };
  ready.value = true;
  const initialCrop = props.initialCrop;
  applyViewport(
    initialCrop && initialCrop.size > 0
      ? viewportFromCrop(initialCrop, stageSize.value, imageSize.value)
      : { scale: minScale(stageSize.value, imageSize.value), offsetX: 0, offsetY: 0 },
  );
}

function onImageLoad(event: Event): void {
  setupImage(event.target as HTMLImageElement);
}

function onPointerDown(event: PointerEvent): void {
  if (!ready.value) return;

  dragStart = {
    x: event.clientX,
    y: event.clientY,
    offsetX: viewport.value.offsetX,
    offsetY: viewport.value.offsetY,
  };
  (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
}

function onPointerMove(event: PointerEvent): void {
  if (!dragStart) return;

  applyViewport({
    ...viewport.value,
    offsetX: dragStart.offsetX + event.clientX - dragStart.x,
    offsetY: dragStart.offsetY + event.clientY - dragStart.y,
  });
}

function onPointerUp(): void {
  dragStart = null;
}

function onWheel(event: WheelEvent): void {
  if (!ready.value || !imageSize.value) return;

  event.preventDefault();
  applyViewport(zoomTo(viewport.value, zoom.value - event.deltaY * 0.002, stageSize.value, imageSize.value));
}

function onZoomInput(value: number | number[]): void {
  if (!imageSize.value) return;

  applyViewport(zoomTo(viewport.value, Array.isArray(value) ? value[0] : value, stageSize.value, imageSize.value));
}

const imageStyle = computed(() => {
  const size = imageSize.value;
  if (!size) return {};

  const width = size.width * viewport.value.scale;
  const height = size.height * viewport.value.scale;

  return {
    width: `${width}px`,
    height: `${height}px`,
    left: `${stageSize.value / 2 + viewport.value.offsetX - width / 2}px`,
    top: `${stageSize.value / 2 + viewport.value.offsetY - height / 2}px`,
  };
});

function measure(): void {
  const element = stageEl.value;
  if (!element) return;

  const width = element.clientWidth;
  if (width < 1 || width === stageSize.value) return;

  stageSize.value = width;
  if (imageSize.value) applyViewport(viewport.value);
}

onMounted(() => {
  measure();
  const element = stageEl.value;
  const image = imageEl.value;
  if (typeof ResizeObserver !== 'undefined' && element) {
    observer = new ResizeObserver(measure);
    observer.observe(element);
  }
  if (image?.complete) setupImage(image);
});

onBeforeUnmount(() => {
  observer?.disconnect();
});
</script>

<template>
  <div class="avatar-crop">
    <div
      ref="stageEl"
      class="avatar-crop__stage"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      @wheel.prevent="onWheel"
    >
      <img
        ref="imageEl"
        :src="src"
        alt=""
        class="avatar-crop__image"
        :style="imageStyle"
        draggable="false"
        @load="onImageLoad"
      />
      <div class="avatar-crop__circle" />
    </div>

    <v-slider
      v-model="zoom"
      :min="1"
      :max="8"
      :step="0.01"
      hide-details
      class="mt-3"
      prepend-icon="mdi-magnify-minus-outline"
      append-icon="mdi-magnify-plus-outline"
      @update:model-value="onZoomInput"
    />
  </div>
</template>

<style scoped>
.avatar-crop__stage {
  position: relative;
  width: 100%;
  max-width: 360px;
  aspect-ratio: 1;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 12px;
  background: #111;
  cursor: grab;
  touch-action: none;
  user-select: none;
}

.avatar-crop__stage:active {
  cursor: grabbing;
}

.avatar-crop__image {
  position: absolute;
  max-width: none;
  pointer-events: none;
}

.avatar-crop__circle {
  position: absolute;
  inset: 0;
  border: 1px solid rgb(255 255 255 / 80%);
  border-radius: 50%;
  box-shadow: 0 0 0 9999px rgb(0 0 0 / 55%);
  pointer-events: none;
}
</style>
