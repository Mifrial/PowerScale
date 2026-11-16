<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { SceneSaveEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneSaveEnclosure';
import SceneDistanceField from '@/modules/Roleplay/Scene/Component/Scene/SceneDistanceField.vue';
import { SCENE_TERRAIN_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_KIND_OPTIONS';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

const FileUploadField = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileUploadField.vue'));

const props = defineProps<{
  board: SceneDraftBoard;
  resolution: number;
  terrainKind: string | null;
  /** Вложение, если поле — этаж; у корня null. */
  enclosure: SceneSaveEnclosure | null;
}>();

const emit = defineEmits<{
  rename: [boardId: number, name: string];
  update: [boardId: number, patch: Partial<Omit<SceneSaveBoard, 'id' | 'sortOrder'>>];
  backdrop: [boardId: number, publicId: string | null, width: number | null, height: number | null];
  terrain: [boardId: number, kind: string];
  enclosure: [boardId: number, patch: Partial<Pick<SceneSaveEnclosure, 'elevation' | 'label'>>];
}>();

const name = ref(props.board.name);
const terrainKind = ref(props.terrainKind ?? 'grass');
const floorUp = ref((props.enclosure?.elevation ?? 0) >= 0);
const floorHeight = ref(Math.abs(props.enclosure?.elevation ?? 0));
const floorLabel = ref(props.enclosure?.label ?? '');

watch(
  () => props.enclosure,
  (value) => {
    floorUp.value = (value?.elevation ?? 0) >= 0;
    floorHeight.value = Math.abs(value?.elevation ?? 0);
    floorLabel.value = value?.label ?? '';
  },
);

/** Направление этажа: знак отметки вложения. */
function setFloorDirection(value: unknown): void {
  const up = value === 'up';
  if (up === floorUp.value) {
    return;
  }

  floorUp.value = up;
  applyFloorHeight(floorHeight.value);
}

/** Высота этажа: абсолютная, знак берётся из направления. */
function applyFloorHeight(value: number): void {
  if (props.enclosure === null) {
    return;
  }

  const elevation = (floorUp.value ? 1 : -1) * Math.abs(value);
  if (elevation !== props.enclosure.elevation) {
    emit('enclosure', props.board.id, { elevation });
  }
}

/** Подпись этажа или строения; пусто — без подписи. */
function commitFloorLabel(): void {
  if (props.enclosure === null) {
    return;
  }

  const label = floorLabel.value.trim();
  const current = props.enclosure.label ?? '';
  if (label === current) {
    return;
  }

  emit('enclosure', props.board.id, { label: label === '' ? null : label });
}

watch(
  () => props.terrainKind,
  (value) => {
    if (value !== null) {
      terrainKind.value = value;
    }
  },
);

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
        contentBox: null,
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
  <div class="scene-board-settings">
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

    <template v-if="props.enclosure !== null">
      <v-divider />
      <div class="text-subtitle-2">Этаж строения</div>
      <div class="scene-board-settings__row scene-board-settings__row--align-end">
        <v-btn-toggle
          :model-value="floorUp ? 'up' : 'down'"
          mandatory
          density="compact"
          variant="outlined"
          divided
          @update:model-value="setFloorDirection"
        >
          <v-btn value="up" prepend-icon="mdi-arrow-up">Вверх</v-btn>
          <v-btn value="down" prepend-icon="mdi-arrow-down">Вниз</v-btn>
        </v-btn-toggle>
        <SceneDistanceField
          :model-value="floorHeight"
          :resolution="props.resolution"
          label="Высота этажа"
          hint="Отметка относительно родительского поля."
          :min="0"
          :max="maxBound"
          @update:model-value="applyFloorHeight"
        />
      </div>
      <v-text-field
        v-model="floorLabel"
        label="Подпись этажа или строения"
        placeholder="Например, «Мельница»"
        density="compact"
        variant="outlined"
        hide-details
        @blur="commitFloorLabel"
        @keyup.enter="commitFloorLabel"
      />
    </template>

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
  </div>
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
