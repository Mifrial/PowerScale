<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { SceneDraftElement } from '@/modules/Roleplay/Scene/Dto/SceneDraftElement';
import SceneDistanceField from '@/modules/Roleplay/Scene/Component/Scene/SceneDistanceField.vue';
import { SCENE_ASSET_COLLECTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ASSET_COLLECTIONS';
import { sceneDecorPreset, sceneDecorPresetProps } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_DECOR_PRESETS';
import { SCENE_MARKER_ROLE_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_MARKER_ROLE_OPTIONS';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { SCENE_OPENING_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OPENING_KIND_OPTIONS';
import { SCENE_SUPPORT_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SUPPORT_KIND_OPTIONS';
import { SCENE_TERRAIN_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_KIND_OPTIONS';
import { SCENE_TERRAIN_SHORE_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_SHORE_OPTIONS';

const AssetLibraryField = defineAsyncComponent(() => import('@/modules/Library/Asset/Component/AssetLibraryField.vue'));

const props = defineProps<{
  element: SceneDraftElement;
  boards: SceneSaveBoard[];
  resolution: number;
}>();

const emit = defineEmits<{
  update: [elementProps: SceneElementProps];
  target: [boardId: number | null];
  asset: [publicId: string | null];
  stretch: [];
  widthAll: [width: number];
  smooth: [];
  corner: [];
}>();

const assetPublicId = computed(() => props.element.assetPublicId ?? null);

const SPEED_PRESETS = [
  { label: '100 норма', value: 100 },
  { label: '150 дорога', value: 150 },
  { label: '80 песок', value: 80 },
  { label: '50 грязь', value: 50 },
  { label: '20 река', value: 20 },
  { label: '0 стоп', value: 0 },
];

const defaultSpeed = computed(() => {
  const kind = props.element.props.kind ?? (props.element.kind === 'support' ? 'ground' : 'grass');

  return SCENE_SURFACE.speeds[kind as keyof typeof SCENE_SURFACE.speeds] ?? 100;
});
const currentSpeed = computed(() => props.element.props.speed ?? defaultSpeed.value);

/** Мост и съезд: у них `height` — толщина настила, а не высота объёма. */
const isDeck = computed(
  () =>
    props.element.kind === 'support' && (props.element.props.kind === 'bridge' || props.element.props.kind === 'ramp'),
);

const targetOptions = computed(() =>
  props.boards
    .filter((board) => board.id !== props.element.boardId)
    .map((board) => ({ value: board.id, title: board.name })),
);

function patch(elementProps: SceneElementProps): void {
  emit('update', { ...props.element.props, ...elementProps });
}

function patchNumber(
  key: 'rotation' | 'scale' | 'baseRadius' | 'sightRadius' | 'sightBottom',
  value: string | number | null,
): void {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return;

  patch({ [key]: Math.round(parsed) });
}

function patchText(key: 'label' | 'kind' | 'state' | 'role', value: string | null): void {
  patch({ [key]: value ?? '' });
}

/** Смена подтипа: если скорость ещё пресетная, подставляем пресет нового типа. */
function patchKind(value: string | null): void {
  const kind = value ?? '';
  const previous = props.element.props.kind ?? (props.element.kind === 'support' ? 'ground' : 'grass');
  const previousSpeed = SCENE_SURFACE.speeds[previous as keyof typeof SCENE_SURFACE.speeds] ?? 100;
  const nextSpeed = SCENE_SURFACE.speeds[kind as keyof typeof SCENE_SURFACE.speeds] ?? 100;
  const speed = props.element.props.speed;

  patch({
    kind,
    ...(speed === undefined || speed === previousSpeed ? { speed: nextSpeed } : {}),
  });
}

/** Скорость элемента: целое 0…10000, 100 — норма. */
function patchSpeed(value: string | number | null): void {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return;

  patch({ speed: Math.max(0, Math.min(10000, Math.round(parsed))) });
}

function patchShoreKind(value: string | null): void {
  const shoreKind = value ?? 'none';
  const hasWidth = (props.element.props.shoreWidth ?? 0) > 0;
  if (shoreKind !== 'none' && !hasWidth) {
    patch({ shoreKind, shoreWidth: Math.max(1, Math.round(props.resolution / 2)) });

    return;
  }

  patch({ shoreKind });
}

function patchFlag(key: 'blocksMovement' | 'blocksVision', value: boolean | null): void {
  patch({ [key]: value === true });
}

function onAsset(publicId: string | null): void {
  emit('asset', publicId);
}

/** Пресет декора: основание и силуэт подставляются по имени файла. */
function onAssetPreset(item: AssetItem): void {
  const preset = sceneDecorPreset(item.originalName ?? item.name);
  if (preset === null) {
    return;
  }

  patch(sceneDecorPresetProps(preset));
}
</script>

<template>
  <div class="scene-element-settings">
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
        @update:model-value="patchKind"
      />
      <v-text-field
        :model-value="currentSpeed"
        type="number"
        label="Скорость"
        hint="100 — норма; 150 — дорога и мост; 20 — река; 0 — непроходимо."
        persistent-hint
        density="compact"
        variant="outlined"
        @update:model-value="patchSpeed"
      />
      <div class="scene-element-settings__speeds">
        <v-chip
          v-for="preset in SPEED_PRESETS"
          :key="preset.value"
          size="x-small"
          :color="currentSpeed === preset.value ? 'primary' : undefined"
          variant="tonal"
          @click="patchSpeed(preset.value)"
        >
          {{ preset.label }}
        </v-chip>
      </div>
      <SceneDistanceField
        :model-value="props.element.props.height ?? 0"
        :resolution="props.resolution"
        :label="isDeck ? 'Толщина настила, ипари' : 'Высота, ипари'"
        :hint="isDeck ? 'Толщина досок и балок; у старого моста без подъёма — высота над водой.' : undefined"
        :min="0"
        :max="100000"
        @update:model-value="(value) => patch({ height: value })"
      />
      <SceneDistanceField
        :model-value="props.element.props.elevation ?? 0"
        :resolution="props.resolution"
        :label="isDeck ? 'Подъём настила, ипари' : 'Подъём, ипари'"
        :hint="isDeck ? 'Высота настила над землёй или водой; по ней рисуются боковая грань и сваи.' : undefined"
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
        hint="Толщину задаёт протяжка; высота — вертикальный размер, не толщина."
        :min="0"
        :max="100000"
        @update:model-value="(value) => patch({ height: value })"
      />
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
        @update:model-value="patchKind"
      />
      <v-text-field
        :model-value="currentSpeed"
        type="number"
        label="Скорость"
        hint="100 — норма; 150 — дорога и мост; 20 — река; 0 — непроходимо."
        persistent-hint
        density="compact"
        variant="outlined"
        @update:model-value="patchSpeed"
      />
      <div class="scene-element-settings__speeds">
        <v-chip
          v-for="preset in SPEED_PRESETS"
          :key="preset.value"
          size="x-small"
          :color="currentSpeed === preset.value ? 'primary' : undefined"
          variant="tonal"
          @click="patchSpeed(preset.value)"
        >
          {{ preset.label }}
        </v-chip>
      </div>
      <AssetLibraryField
        :model-value="assetPublicId"
        label="Текстура террейна"
        :collection-module="SCENE_ASSET_COLLECTIONS.terrain.module"
        :collection-code="SCENE_ASSET_COLLECTIONS.terrain.code"
        :collection-title="SCENE_ASSET_COLLECTIONS.terrain.title"
        @update:model-value="onAsset"
      />
      <v-select
        :model-value="props.element.props.shoreKind ?? 'none'"
        :items="SCENE_TERRAIN_SHORE_OPTIONS"
        :label="props.element.props.kind === 'water' ? 'Берег' : 'Кромка'"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="patchShoreKind"
      />
      <SceneDistanceField
        v-if="(props.element.props.shoreKind ?? 'none') !== 'none'"
        :model-value="props.element.props.shoreWidth ?? 0"
        :resolution="props.resolution"
        :label="props.element.props.kind === 'water' ? 'Ширина берега, ипари' : 'Ширина кромки, ипари'"
        hint="Кромка расширяет террейн снаружи; материал выбирается отдельно от поверхности."
        :min="0"
        :max="100000"
        @update:model-value="(value) => patch({ shoreWidth: value })"
      />
      <SceneDistanceField
        v-if="props.element.shape.type === 'path'"
        :model-value="props.element.shape.widths[0] ?? props.resolution * 4"
        :resolution="props.resolution"
        label="Ширина ленты, ипари"
        hint="Одна ширина на все вершины; отдельную вершину тяните ромбом на холсте."
        :min="1"
        :max="100000"
        @update:model-value="(value) => emit('widthAll', value)"
      />
      <div
        v-if="props.element.shape.type === 'polygon' || props.element.shape.type === 'path'"
        class="scene-element-settings__row"
      >
        <v-btn variant="tonal" size="small" prepend-icon="mdi-transit-connection-variant" @click="emit('smooth')">
          Сгладить
        </v-btn>
        <v-btn variant="tonal" size="small" prepend-icon="mdi-vector-square" @click="emit('corner')"> Углы </v-btn>
      </div>
      <v-btn
        v-if="props.element.shape.type !== 'path'"
        variant="tonal"
        size="small"
        prepend-icon="mdi-fit-to-screen"
        @click="emit('stretch')"
      >
        Растянуть на всё поле
      </v-btn>
    </template>

    <template v-if="props.element.kind === 'decor'">
      <AssetLibraryField
        :model-value="assetPublicId"
        label="Ассет декора"
        :collection-module="SCENE_ASSET_COLLECTIONS.decor.module"
        :collection-code="SCENE_ASSET_COLLECTIONS.decor.code"
        :collection-title="SCENE_ASSET_COLLECTIONS.decor.title"
        @update:model-value="onAsset"
        @select="onAssetPreset"
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
      <v-text-field
        :model-value="props.element.props.baseRadius ?? 30"
        label="Основание"
        type="number"
        suffix="% ширины"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="(value) => patchNumber('baseRadius', value)"
      />
      <v-text-field
        :model-value="props.element.props.sightRadius ?? 45"
        label="Силуэт"
        type="number"
        suffix="% ширины"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="(value) => patchNumber('sightRadius', value)"
      />
      <v-text-field
        :model-value="props.element.props.sightBottom ?? 0"
        label="Низ силуэта"
        type="number"
        suffix="% ширины"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="(value) => patchNumber('sightBottom', value)"
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
  </div>
</template>

<style scoped>
.scene-element-settings {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.scene-element-settings__speeds {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: -8px;
}

.scene-element-settings__row {
  display: flex;
  gap: 8px;
}

.scene-element-settings__row > * {
  flex: 1 1 0;
  min-width: 0;
}
</style>
