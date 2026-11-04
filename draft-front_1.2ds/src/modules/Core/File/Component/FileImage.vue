<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { fileImageSource } from '@/modules/Core/File/Service/FileImageSource';

const props = withDefaults(
  defineProps<{
    publicId: string;
    alt?: string;
    archived?: boolean;
    width?: string | number;
    height?: string | number;
  }>(),
  { alt: 'Файл', archived: false, width: undefined, height: undefined },
);

const loading = ref(true);
const failed = ref(false);

const src = computed(() => {
  if (failed.value) return '';

  return fileImageSource(props.publicId, props.archived);
});

const imageStyle = computed(() => ({
  width: typeof props.width === 'number' ? `${props.width}px` : props.width,
  height: typeof props.height === 'number' ? `${props.height}px` : props.height,
}));

watch(
  () => [props.publicId, props.archived],
  () => {
    loading.value = true;
    failed.value = false;
  },
);

function onLoad(): void {
  loading.value = false;
}

function onError(): void {
  failed.value = true;
  loading.value = false;
}
</script>

<template>
  <div class="file-image">
    <v-icon v-if="archived" icon="mdi-archive" aria-label="Файл в архиве" />
    <v-icon v-else-if="failed" icon="mdi-file-broken" aria-label="Не удалось показать файл" />
    <template v-else-if="src">
      <v-progress-circular v-if="loading" indeterminate size="24" class="file-image__spinner" />
      <img
        :src="src"
        :alt="props.alt"
        :style="imageStyle"
        loading="lazy"
        decoding="async"
        @load="onLoad"
        @error="onError"
      />
    </template>
  </div>
</template>

<style scoped>
.file-image {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 100%;
}

.file-image__spinner {
  position: absolute;
  z-index: 1;
}

.file-image img {
  max-width: 100%;
  object-fit: contain;
}
</style>
