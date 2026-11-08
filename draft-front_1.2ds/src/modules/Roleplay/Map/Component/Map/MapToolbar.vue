<script setup lang="ts">
import { computed } from 'vue';
import type { MapDetailMode } from '@/modules/Roleplay/Map/Enum/MapDetailMode';
import type { MapToolMode } from '@/modules/Roleplay/Map/Enum/MapToolMode';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import { MAP_DETAIL_MODE_OPTIONS } from '@/modules/Roleplay/Map/Constant/Map/MAP_DETAIL_MODE_OPTIONS';
import { MAP_TOOL_OPTIONS } from '@/modules/Roleplay/Map/Constant/Map/MAP_TOOL_OPTIONS';

const props = defineProps<{
  mode: MapDetailMode;
  tool: MapToolMode;
  variantId: number | null;
  variants: MapVariant[];
  canEdit: boolean;
  saving: boolean;
  dirty: boolean;
  draftSize: number;
  canUndo: boolean;
  canRedo: boolean;
  /** Подписи всех областей, а не только крупных. */
  labels: boolean;
}>();

const emit = defineEmits<{
  'update:mode': [mode: MapDetailMode];
  'update:tool': [tool: MapToolMode];
  'update:variantId': [variantId: number];
  fit: [];
  zoom: [factor: number];
  refresh: [];
  save: [];
  discard: [];
  createVariant: [];
  editVariant: [];
  calibrate: [];
  finishDraft: [];
  cancelDraft: [];
  settings: [];
  backdrop: [];
  help: [];
  undo: [];
  redo: [];
  toggleLabels: [];
}>();

const variantOptions = computed(() => props.variants.map((variant) => ({ title: variant.label, value: variant.id })));

const variantModel = computed({
  get: () => props.variantId,
  set: (value: number | null) => {
    if (value !== null) emit('update:variantId', value);
  },
});

const canFinishDraft = computed(() => (props.tool === 'area' || props.tool === 'line') && props.draftSize >= 2);
const finishLabel = computed(() => (props.tool === 'area' ? 'Замкнуть' : 'Завершить'));

function changeMode(value: unknown): void {
  emit('update:mode', value as MapDetailMode);
}

function changeTool(value: unknown): void {
  emit('update:tool', value as MapToolMode);
}
</script>

<template>
  <div class="map-toolbar">
    <v-btn-toggle
      :model-value="props.mode"
      density="comfortable"
      variant="outlined"
      divided
      mandatory
      @update:model-value="changeMode"
    >
      <v-btn
        v-for="option in MAP_DETAIL_MODE_OPTIONS"
        :key="option.value"
        :value="option.value"
        :prepend-icon="option.icon"
        :disabled="option.value === 'edit' && !props.canEdit"
      >
        {{ option.title }}
      </v-btn>
    </v-btn-toggle>

    <v-select
      v-model="variantModel"
      :items="variantOptions"
      label="Вариант"
      variant="outlined"
      density="compact"
      hide-details
      class="map-toolbar__variant"
    />

    <template v-if="props.mode === 'edit' && props.canEdit">
      <v-select
        :model-value="props.tool"
        :items="MAP_TOOL_OPTIONS"
        item-title="title"
        item-value="value"
        label="Инструмент"
        variant="outlined"
        density="compact"
        hide-details
        class="map-toolbar__tool"
        @update:model-value="changeTool"
      />
      <v-btn v-if="canFinishDraft" size="small" variant="tonal" @click="emit('finishDraft')">{{ finishLabel }}</v-btn>
      <v-btn
        v-if="props.draftSize > 0 || props.tool !== 'view'"
        size="small"
        variant="text"
        @click="emit('cancelDraft')"
      >
        Отменить
      </v-btn>
      <v-btn size="small" variant="text" prepend-icon="mdi-image-outline" @click="emit('backdrop')">Подложка</v-btn>
      <v-btn size="small" variant="text" prepend-icon="mdi-ruler" @click="emit('calibrate')">Масштаб</v-btn>
      <v-btn
        icon="mdi-plus-box-outline"
        size="small"
        variant="text"
        title="Новый вариант"
        @click="emit('createVariant')"
      />
      <v-btn
        icon="mdi-cog-outline"
        size="small"
        variant="text"
        title="Настройки варианта"
        @click="emit('editVariant')"
      />
      <v-btn
        icon="mdi-undo"
        size="small"
        variant="text"
        title="Отменить (Ctrl+Z)"
        :disabled="!props.canUndo"
        @click="emit('undo')"
      />
      <v-btn
        icon="mdi-redo"
        size="small"
        variant="text"
        title="Повторить (Ctrl+Shift+Z)"
        :disabled="!props.canRedo"
        @click="emit('redo')"
      />
      <v-btn icon="mdi-help-circle-outline" size="small" variant="text" title="Как работать" @click="emit('help')" />
    </template>

    <v-spacer />
    <v-btn
      :variant="props.labels ? 'tonal' : 'text'"
      :color="props.labels ? 'primary' : undefined"
      icon="mdi-label-outline"
      size="small"
      title="Подписи объектов"
      @click="emit('toggleLabels')"
    />
    <v-chip v-if="props.dirty" size="small" color="warning" variant="tonal">Есть изменения</v-chip>
    <v-btn
      v-if="props.mode === 'edit' && props.canEdit"
      color="primary"
      variant="tonal"
      size="small"
      prepend-icon="mdi-content-save-outline"
      :loading="props.saving"
      :disabled="!props.dirty"
      @click="emit('save')"
    >
      Сохранить
    </v-btn>
    <v-btn
      v-if="props.mode === 'edit' && props.canEdit && props.dirty"
      color="error"
      variant="text"
      size="small"
      @click="emit('discard')"
    >
      Отменить правки
    </v-btn>
    <v-btn
      icon="mdi-magnify-minus-outline"
      size="small"
      variant="text"
      title="Уменьшить"
      @click="emit('zoom', 1 / 1.25)"
    />
    <v-btn icon="mdi-magnify-plus-outline" size="small" variant="text" title="Увеличить" @click="emit('zoom', 1.25)" />
    <v-btn icon="mdi-fit-to-page-outline" size="small" variant="text" title="Вписать" @click="emit('fit')" />
    <v-btn
      icon="mdi-cog-outline"
      size="small"
      variant="text"
      title="Настройки карты"
      :disabled="!props.canEdit"
      @click="emit('settings')"
    />
    <v-btn
      icon="mdi-refresh"
      size="small"
      variant="text"
      title="Обновить с сервера"
      :loading="props.saving"
      @click="emit('refresh')"
    />
  </div>
</template>

<style scoped>
.map-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.map-toolbar__variant {
  max-width: 220px;
}

.map-toolbar__tool {
  max-width: 220px;
}
</style>
