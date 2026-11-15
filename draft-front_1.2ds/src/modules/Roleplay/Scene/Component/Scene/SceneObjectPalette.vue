<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import { sceneObjectTemplateProps } from '@/modules/Roleplay/Scene/Service/SceneObjectTemplate';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

const FileImage = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileImage.vue'));

/**
 * Палитра готовых объектов: ассеты с шаблоном физики.
 *
 * Клик вооружает постановку: следующий клик по карте создаёт декор с
 * основанием, силуэтом и скоростью из шаблона. Это не ссылка на объект —
 * постановка копирует числа, поэтому правка шаблона не переписывает карты.
 */
const props = defineProps<{
  objects: AssetItem[];
  loading: boolean;
  error: string | null;
  /** Объект, который ждёт клика по карте. */
  pendingId: number | null;
  resolution: number;
}>();

const emit = defineEmits<{
  pick: [item: AssetItem];
  refresh: [];
  cancel: [];
}>();

/** Подпись основания: размер круга или число точек полигона. */
function footprintLabel(item: AssetItem): string {
  const template = sceneObjectTemplateProps(item.meta, props.resolution);
  if (template === null || template.footprint === undefined) {
    return 'без основания';
  }

  if (template.footprint.shape === 'circle') {
    return `⌀ ${sceneUnits.formatUnits(template.footprint.radius * 2, props.resolution)} ипари`;
  }

  return `полигон · ${template.footprint.points.length} точек`;
}

const hasObjects = computed(() => props.objects.length > 0);
</script>

<template>
  <div class="scene-object-palette">
    <div class="scene-object-palette__actions">
      <v-btn size="small" variant="tonal" prepend-icon="mdi-refresh" :loading="props.loading" @click="emit('refresh')">
        Обновить
      </v-btn>
      <v-btn v-if="props.pendingId !== null" size="small" variant="text" @click="emit('cancel')">Отмена</v-btn>
    </div>

    <v-alert v-if="props.error" type="error" density="compact">{{ props.error }}</v-alert>

    <v-alert v-if="props.pendingId !== null" type="info" density="compact">
      Кликните по карте — объект встанет в эту точку. Esc отменяет.
    </v-alert>

    <v-progress-linear v-if="props.loading && !hasObjects" indeterminate class="my-2" />

    <div v-if="hasObjects" class="scene-object-palette__list">
      <button
        v-for="item in props.objects"
        :key="item.id"
        type="button"
        class="scene-object-palette__item"
        :class="{ 'scene-object-palette__item--active': item.id === props.pendingId }"
        @click="emit('pick', item)"
      >
        <span class="scene-object-palette__preview">
          <FileImage v-if="item.filePublicId" :public-id="item.filePublicId" width="56" height="56" cover />
          <v-icon v-else icon="mdi-cube-outline" size="28" />
        </span>
        <span class="scene-object-palette__text">
          <span class="scene-object-palette__name">{{ item.name }}</span>
          <span class="text-caption text-medium-emphasis">{{ footprintLabel(item) }}</span>
        </span>
      </button>
    </div>

    <div v-else-if="!props.loading" class="text-caption text-medium-emphasis">
      Объектов пока нет. Выберите декор и нажмите «Сохранить как объект».
    </div>
  </div>
</template>

<style scoped>
.scene-object-palette {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.scene-object-palette__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.scene-object-palette__list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 320px;
  overflow-y: auto;
}

.scene-object-palette__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px;
  text-align: left;
  cursor: pointer;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
}

.scene-object-palette__item:hover,
.scene-object-palette__item--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

.scene-object-palette__preview {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  background: rgba(var(--v-theme-surface-variant), 0.35);
}

.scene-object-palette__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.scene-object-palette__name {
  overflow: hidden;
  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
