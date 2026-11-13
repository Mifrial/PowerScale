<script setup lang="ts">
import { defineAsyncComponent, ref, watch } from 'vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import AssetLibraryDialog from '@/modules/Library/Asset/Component/AssetLibraryDialog.vue';

const FileImage = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileImage.vue'));

const props = withDefaults(
  defineProps<{
    modelValue: string | null;
    collectionModule: string;
    collectionCode: string;
    collectionTitle: string;
    label?: string;
    disabled?: boolean;
  }>(),
  { label: 'Ассет', disabled: false },
);

const emit = defineEmits<{
  'update:modelValue': [value: string | null];
  select: [item: AssetItem];
}>();

const dialogOpen = ref(false);
const publicId = ref<string | null>(props.modelValue ?? null);

watch(
  () => props.modelValue,
  (value) => {
    publicId.value = value ?? null;
  },
);

function openDialog(): void {
  if (!props.disabled) dialogOpen.value = true;
}

function onSelect(item: AssetItem): void {
  if (item.filePublicId === null) return;
  publicId.value = item.filePublicId;
  emit('update:modelValue', item.filePublicId);
  emit('select', item);
}

function clear(): void {
  publicId.value = null;
  emit('update:modelValue', null);
}
</script>

<template>
  <div class="asset-field">
    <div class="asset-field__label text-caption">{{ props.label }}</div>
    <div class="asset-field__body">
      <div class="asset-field__preview">
        <FileImage v-if="publicId" :public-id="publicId" width="72" height="72" cover />
        <v-icon v-else icon="mdi-image-outline" size="32" />
      </div>
      <div class="asset-field__actions">
        <v-btn
          size="small"
          variant="tonal"
          prepend-icon="mdi-image-multiple"
          :disabled="props.disabled"
          @click="openDialog"
        >
          Выбрать из библиотеки
        </v-btn>
        <v-btn v-if="publicId" size="small" variant="text" prepend-icon="mdi-close" @click="clear">Очистить</v-btn>
      </div>
    </div>
    <AssetLibraryDialog
      v-model="dialogOpen"
      :collection-module="props.collectionModule"
      :collection-code="props.collectionCode"
      :collection-title="props.collectionTitle"
      @select="onSelect"
    />
  </div>
</template>

<style scoped>
.asset-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.asset-field__body {
  display: flex;
  align-items: center;
  gap: 12px;
}

.asset-field__preview {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 72px;
  height: 72px;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  background: rgba(var(--v-theme-surface-variant), 0.35);
}

.asset-field__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}
</style>
