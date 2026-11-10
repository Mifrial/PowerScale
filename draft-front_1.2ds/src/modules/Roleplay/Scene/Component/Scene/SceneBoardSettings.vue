<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import SceneDistanceField from '@/modules/Roleplay/Scene/Component/Scene/SceneDistanceField.vue';
import { SCENE_TERRAIN_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_KIND_OPTIONS';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

const FileUploadField = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileUploadField.vue'));

const props = defineProps<{
  board: SceneDraftBoard;
  boardCount: number;
  index: number;
  resolution: number;
}>();

const emit = defineEmits<{
  rename: [boardId: number, name: string];
  update: [boardId: number, patch: Partial<Omit<SceneSaveBoard, 'id' | 'sortOrder'>>];
  backdrop: [boardId: number, publicId: string | null, width: number | null, height: number | null];
  terrain: [boardId: number, kind: string];
  move: [boardId: number, offset: number];
  remove: [boardId: number];
}>();

const name = ref(props.board.name);
const terrainKind = ref('grass');

watch(
  () => props.board.name,
  (value) => {
    name.value = value;
  },
);

const summary = computed(
  () =>
    `${sceneUnits.formatUnits(props.board.boundWidth, props.resolution)} × ` +
    `${sceneUnits.formatUnits(props.board.boundHeight, props.resolution)} ипари`,
);
const maxBound = computed(() => props.resolution * 200);
const maxCell = computed(() => Math.max(props.board.boundWidth, props.board.boundHeight));
const defaultCell = computed(() => Math.max(1, Math.floor(props.resolution / 4)));
const cellHint = computed(
  () => `Свет и укрытия. По умолчанию ${sceneUnits.formatUnits(defaultCell.value, props.resolution)} ипари.`,
);

const backdropFile = computed<FileRecord | null>(() =>
  props.board.filePublicId === null
    ? null
    : {
        id: 0,
        publicId: props.board.filePublicId,
        ownerId: 0,
        originalName: '',
        mime: 'image/*',
        size: 0,
        width: props.board.fileWidth,
        height: props.board.fileHeight,
        sha256: '',
        isArchived: false,
        isDerived: false,
        archivedAt: null,
        createdAt: 0,
      },
);

const aspectWarning = computed<boolean>(() => {
  const { fileWidth, fileHeight, boundWidth, boundHeight } = props.board;
  if (fileWidth === null || fileHeight === null || fileWidth === 0 || fileHeight === 0) {
    return false;
  }

  const imageRatio = fileWidth / fileHeight;
  const boardRatio = boundWidth / boundHeight;

  return Math.abs(imageRatio - boardRatio) > 0.02;
});

function commitName(): void {
  if (name.value.trim() !== '' && name.value !== props.board.name) {
    emit('rename', props.board.id, name.value);
  }
}

function onBackdrop(file: FileRecord | null): void {
  emit('backdrop', props.board.id, file?.publicId ?? null, file?.width ?? null, file?.height ?? null);
}
</script>

<template>
  <v-card variant="outlined">
    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
      Поле
      <v-spacer />
      <v-btn
        icon="mdi-arrow-left"
        size="x-small"
        variant="text"
        :disabled="props.index <= 0"
        title="Поле влево"
        @click="emit('move', props.board.id, -1)"
      />
      <v-btn
        icon="mdi-arrow-right"
        size="x-small"
        variant="text"
        :disabled="props.index >= props.boardCount - 1"
        title="Поле вправо"
        @click="emit('move', props.board.id, 1)"
      />
      <v-btn
        icon="mdi-delete"
        size="x-small"
        variant="text"
        color="error"
        :disabled="props.boardCount <= 1"
        title="Удалить поле"
        @click="emit('remove', props.board.id)"
      />
    </v-card-title>
    <v-card-text class="scene-board-settings">
      <v-text-field
        v-model="name"
        label="Название"
        density="compact"
        variant="outlined"
        hide-details
        @blur="commitName"
        @keyup.enter="commitName"
      />

      <div class="scene-board-settings__summary">{{ summary }}</div>

      <div class="scene-board-settings__row">
        <SceneDistanceField
          :model-value="props.board.boundWidth"
          :resolution="props.resolution"
          label="Ширина, ипари"
          :min="1"
          :max="maxBound"
          @update:model-value="(value) => emit('update', props.board.id, { boundWidth: value })"
        />
        <SceneDistanceField
          :model-value="props.board.boundHeight"
          :resolution="props.resolution"
          label="Высота, ипари"
          :min="1"
          :max="maxBound"
          @update:model-value="(value) => emit('update', props.board.id, { boundHeight: value })"
        />
      </div>

      <div class="scene-board-settings__row">
        <SceneDistanceField
          :model-value="props.board.gridStep"
          :resolution="props.resolution"
          label="Сетка, ипари"
          hint="0 — выключить; жирная линия каждые 2 шага"
          :min="0"
          :max="maxBound"
          @update:model-value="(value) => emit('update', props.board.id, { gridStep: value })"
        />
        <SceneDistanceField
          :model-value="props.board.occupancyCell"
          :resolution="props.resolution"
          label="Сэмплинг, ипари"
          :hint="cellHint"
          :min="1"
          :max="maxCell"
          @update:model-value="(value) => emit('update', props.board.id, { occupancyCell: value })"
        />
      </div>

      <FileUploadField
        :model-value="backdropFile"
        label="Подложка"
        :accept="['image/png', 'image/jpeg', 'image/webp']"
        @update:model-value="onBackdrop"
      />

      <div class="scene-board-settings__row scene-board-settings__row--align-end">
        <v-select
          v-model="terrainKind"
          :items="SCENE_TERRAIN_KIND_OPTIONS"
          label="Террейн поля"
          density="compact"
          variant="outlined"
          hide-details
        />
        <v-btn
          color="primary"
          variant="tonal"
          prepend-icon="mdi-format-color-fill"
          @click="emit('terrain', props.board.id, terrainKind)"
        >
          Залить
        </v-btn>
      </div>

      <v-alert v-if="aspectWarning" type="warning" variant="tonal" density="compact" class="text-caption">
        Пропорции подложки не совпадают с границами поля: изображение растянется.
      </v-alert>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.scene-board-settings {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.scene-board-settings__summary {
  font-size: 0.8rem;
  color: rgb(var(--v-theme-on-surface-variant));
}

.scene-board-settings__row {
  display: flex;
  gap: 12px;
}

.scene-board-settings__row > * {
  min-width: 0;
  flex: 1 1 0;
}

.scene-board-settings__row--align-end {
  align-items: flex-end;
}

.scene-board-settings__row--align-end > *:last-child {
  flex: 0 0 auto;
}
</style>
