<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';

const FileUploadField = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileUploadField.vue'));

const props = defineProps<{
  board: SceneDraftBoard;
  boardCount: number;
  index: number;
}>();

const emit = defineEmits<{
  rename: [boardId: number, name: string];
  update: [boardId: number, patch: Partial<Omit<SceneSaveBoard, 'id' | 'sortOrder'>>];
  backdrop: [boardId: number, publicId: string | null, width: number | null, height: number | null];
  move: [boardId: number, offset: number];
  remove: [boardId: number];
}>();

const name = ref(props.board.name);

watch(
  () => props.board.name,
  (value) => {
    name.value = value;
  },
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

function patchNumber(
  key: 'boundWidth' | 'boundHeight' | 'occupancyCell' | 'zQuantum' | 'gridStep',
  value: string | number,
): void {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return;

  emit('update', props.board.id, { [key]: Math.round(parsed) });
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

      <div class="scene-board-settings__row">
        <v-text-field
          :model-value="props.board.boundWidth"
          label="Ширина"
          type="number"
          suffix="ед."
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchNumber('boundWidth', value)"
        />
        <v-text-field
          :model-value="props.board.boundHeight"
          label="Высота"
          type="number"
          suffix="ед."
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchNumber('boundHeight', value)"
        />
      </div>

      <div class="scene-board-settings__row">
        <v-text-field
          :model-value="props.board.gridStep"
          label="Шаг сетки"
          type="number"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchNumber('gridStep', value)"
        />
        <v-text-field
          :model-value="props.board.occupancyCell"
          label="Клетка"
          type="number"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchNumber('occupancyCell', value)"
        />
      </div>

      <FileUploadField
        :model-value="backdropFile"
        label="Подложка"
        :accept="['image/png', 'image/jpeg', 'image/webp']"
        @update:model-value="onBackdrop"
      />

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

.scene-board-settings__row {
  display: flex;
  gap: 12px;
}

.scene-board-settings__row > * {
  min-width: 0;
}
</style>
