<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { SceneDraftElement } from '@/modules/Roleplay/Scene/Dto/SceneDraftElement';
import SceneDistanceField from '@/modules/Roleplay/Scene/Component/Scene/SceneDistanceField.vue';
import { SCENE_ELEMENT_KIND_LABELS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ELEMENT_KIND_LABELS';
import { SCENE_MARKER_ROLE_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_MARKER_ROLE_OPTIONS';
import { SCENE_OPENING_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OPENING_KIND_OPTIONS';
import { SCENE_SUPPORT_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SUPPORT_KIND_OPTIONS';
import { SCENE_TERRAIN_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_KIND_OPTIONS';

const FileUploadField = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileUploadField.vue'));

const props = defineProps<{
  element: SceneDraftElement;
  boards: SceneSaveBoard[];
  resolution: number;
}>();

const emit = defineEmits<{
  update: [elementProps: SceneElementProps];
  target: [boardId: number | null];
  asset: [publicId: string | null];
  remove: [];
}>();

const assetPublicId = ref(props.element.assetPublicId ?? null);

watch(
  () => props.element.assetPublicId,
  (value) => {
    assetPublicId.value = value ?? null;
  },
);

const title = computed(() => `Элемент · ${SCENE_ELEMENT_KIND_LABELS[props.element.kind]}`);
const targetOptions = computed(() =>
  props.boards
    .filter((board) => board.id !== props.element.boardId)
    .map((board) => ({ value: board.id, title: board.name })),
);
const assetFile = computed<FileRecord | null>(() =>
  assetPublicId.value === null
    ? null
    : {
        id: 0,
        publicId: assetPublicId.value,
        ownerId: 0,
        originalName: '',
        mime: 'image/*',
        size: 0,
        width: null,
        height: null,
        sha256: '',
        isArchived: false,
        isDerived: false,
        archivedAt: null,
        createdAt: 0,
      },
);

function patch(elementProps: SceneElementProps): void {
  emit('update', { ...props.element.props, ...elementProps });
}

function patchNumber(key: 'rotation' | 'scale', value: string | number | null): void {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return;

  patch({ [key]: Math.round(parsed) });
}

function patchText(key: 'label' | 'kind' | 'state' | 'role', value: string | null): void {
  patch({ [key]: value ?? '' });
}

function patchFlag(key: 'blocksMovement' | 'blocksVision', value: boolean | null): void {
  patch({ [key]: value === true });
}

function onAsset(file: FileRecord | null): void {
  assetPublicId.value = file?.publicId ?? null;
  emit('asset', file?.publicId ?? null);
}
</script>

<template>
  <v-card variant="outlined">
    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
      {{ title }}
      <v-spacer />
      <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" title="Удалить" @click="emit('remove')" />
    </v-card-title>

    <v-card-text class="scene-element-settings">
      <v-text-field
        :model-value="props.element.props.label ?? ''"
        label="Подпись"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="(value) => patchText('label', value)"
      />

      <template v-if="props.element.kind === 'support'">
        <v-select
          :model-value="props.element.props.kind ?? 'ground'"
          :items="SCENE_SUPPORT_KIND_OPTIONS"
          label="Подтип"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchText('kind', value)"
        />
        <SceneDistanceField
          :model-value="props.element.props.height ?? 0"
          :resolution="props.resolution"
          label="Высота, ипари"
          :min="0"
          :max="100000"
          @update:model-value="(value) => patch({ height: value })"
        />
        <SceneDistanceField
          :model-value="props.element.props.elevation ?? 0"
          :resolution="props.resolution"
          label="Подъём, ипари"
          :min="0"
          :max="100000"
          @update:model-value="(value) => patch({ elevation: value })"
        />
      </template>

      <template v-if="props.element.kind === 'obstacle'">
        <v-switch
          :model-value="props.element.props.blocksMovement ?? true"
          label="Блокирует движение"
          density="compact"
          hide-details
          @update:model-value="(value) => patchFlag('blocksMovement', value)"
        />
        <v-switch
          :model-value="props.element.props.blocksVision ?? true"
          label="Блокирует обзор"
          density="compact"
          hide-details
          @update:model-value="(value) => patchFlag('blocksVision', value)"
        />
        <SceneDistanceField
          :model-value="props.element.props.height ?? 0"
          :resolution="props.resolution"
          label="Высота, ипари"
          :min="0"
          :max="100000"
          @update:model-value="(value) => patch({ height: value })"
        />
        <div class="text-caption text-medium-emphasis">
          Толщину задаёт протяжка; высота — вертикальный размер, не толщина.
        </div>
      </template>

      <template v-if="props.element.kind === 'opening'">
        <v-select
          :model-value="props.element.props.kind ?? 'door'"
          :items="SCENE_OPENING_KIND_OPTIONS"
          label="Подтип"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchText('kind', value)"
        />
        <v-select
          :model-value="props.element.props.state ?? 'closed'"
          :items="[
            { value: 'open', title: 'Открыт' },
            { value: 'closed', title: 'Закрыт' },
          ]"
          label="Состояние"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchText('state', value)"
        />
        <v-switch
          :model-value="props.element.props.blocksVision ?? false"
          label="Блокирует обзор"
          density="compact"
          hide-details
          @update:model-value="(value) => patchFlag('blocksVision', value)"
        />
        <v-select
          :model-value="props.element.targetBoardId"
          :items="targetOptions"
          label="Ведёт на поле"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          @update:model-value="(value) => emit('target', value === null ? null : Number(value))"
        />
      </template>

      <template v-if="props.element.kind === 'terrain'">
        <v-select
          :model-value="props.element.props.kind ?? 'grass'"
          :items="SCENE_TERRAIN_KIND_OPTIONS"
          label="Шаблон"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchText('kind', value)"
        />
        <FileUploadField
          :model-value="assetFile"
          label="Текстура террейна"
          :accept="['image/png', 'image/jpeg', 'image/webp']"
          @update:model-value="onAsset"
        />
      </template>

      <template v-if="props.element.kind === 'decor'">
        <FileUploadField
          :model-value="assetFile"
          label="Ассет"
          :accept="['image/png', 'image/jpeg', 'image/webp']"
          @update:model-value="onAsset"
        />
        <v-text-field
          :model-value="props.element.props.rotation ?? 0"
          label="Поворот"
          type="number"
          suffix="°"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchNumber('rotation', value)"
        />
        <v-text-field
          :model-value="props.element.props.scale ?? 100"
          label="Масштаб"
          type="number"
          suffix="%"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchNumber('scale', value)"
        />
      </template>

      <template v-if="props.element.kind === 'marker'">
        <v-select
          :model-value="props.element.props.role ?? 'entrance'"
          :items="SCENE_MARKER_ROLE_OPTIONS"
          label="Роль"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="(value) => patchText('role', value)"
        />
      </template>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.scene-element-settings {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
