<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';
import type { SceneDraftElement } from '@/modules/Roleplay/Scene/Dto/SceneDraftElement';
import SceneDistanceField from '@/modules/Roleplay/Scene/Component/Scene/SceneDistanceField.vue';
import { SCENE_ASSET_COLLECTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ASSET_COLLECTIONS';
import { sceneDecorPreset, sceneDecorPresetProps } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_DECOR_PRESETS';
import { SCENE_MARKER_ROLE_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_MARKER_ROLE_OPTIONS';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { SCENE_OPENING_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OPENING_KIND_OPTIONS';
import { SCENE_SUPPORT_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SUPPORT_KIND_OPTIONS';
import { SCENE_TERRAIN_KIND_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_KIND_OPTIONS';
import { SCENE_TERRAIN_SHORE_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_SHORE_OPTIONS';
import {
  sceneDeckBaseElevation,
  sceneDeckClearance,
  sceneDeckThickness,
  sceneRampSlope,
} from '@/modules/Roleplay/Scene/Service/SceneHeight';
import {
  sceneRampAttachment,
  type SceneCompass,
  type SceneDeckEnd,
  type SceneRampAttachment,
} from '@/modules/Roleplay/Scene/Service/SceneRampAttachment';
import { sceneDecorFootprintDefaults } from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneDecorZone } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import type { SceneFootprint } from '@/modules/Roleplay/Scene/Dto/SceneFootprint';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

const AssetLibraryField = defineAsyncComponent(() => import('@/modules/Library/Asset/Component/AssetLibraryField.vue'));

const props = defineProps<{
  element: SceneDraftElement;
  boards: SceneSaveBoard[];
  elements: SceneDraftElement[];
  resolution: number;
}>();

const emit = defineEmits<{
  update: [elementProps: SceneElementProps];
  target: [boardId: number | null];
  asset: [publicId: string | null, contentBox: ImageContentBox | null, preset: SceneElementProps | null];
  stretch: [];
  widthAll: [width: number];
  smooth: [];
  corner: [];
  attachRamp: [end: SceneDeckEnd];
  outline: [target: SceneDecorZone];
  footprintSmooth: [];
  footprintCorner: [];
  silhouetteSmooth: [];
  silhouetteCorner: [];
  saveObject: [];
  applyObject: [];
}>();

/** Прилагательное для торца по стороне света. */
const COMPASS_LABELS: Record<SceneCompass, string> = {
  north: 'северному',
  'north-east': 'северо-восточному',
  east: 'восточному',
  'south-east': 'юго-восточному',
  south: 'южному',
  'south-west': 'юго-западному',
  west: 'западному',
  'north-west': 'северо-западному',
};

const assetPublicId = computed(() => props.element.assetPublicId ?? null);

const SPEED_PRESETS = [
  { label: '100 норма', value: 100 },
  { label: '150 дорога', value: 150 },
  { label: '80 песок', value: 80 },
  { label: '50 грязь', value: 50 },
  { label: '20 река', value: 20 },
  { label: '0 стоп', value: 0 },
];

const FOOTPRINT_KINDS = [
  { title: 'Круг', value: 'circle' },
  { title: 'Полигон', value: 'polygon' },
];

const defaultSpeed = computed(() => {
  // Декор по умолчанию непроходим: объект, а не поверхность. Явная скорость
  // выше нуля делает его проходимым — например, куст или ковёр.
  if (props.element.kind === 'decor') {
    return 0;
  }

  const kind = props.element.props.kind ?? (props.element.kind === 'support' ? 'ground' : 'grass');

  return SCENE_SURFACE.speeds[kind as keyof typeof SCENE_SURFACE.speeds] ?? 100;
});
const currentSpeed = computed(() => props.element.props.speed ?? defaultSpeed.value);

/** Подсказка к глубине воды: без берега вход в воду остаётся обрывом. */
const depthHint = computed(() =>
  (props.element.props.shoreKind ?? 'none') === 'none'
    ? '0 — вода вровень с берегом. Без береговой полосы вход в воду — обрыв.'
    : '0 — вода вровень с берегом; больше — русло врезано, берег становится откосом.',
);

/** Основание и силуэт: явные поля или старые доли ширины спрайта. */
const decorDefaults = computed(() => sceneDecorFootprintDefaults(props.element, props.resolution));

/** Тип основания в карточке: круг или полигон. */
const footprintKind = computed(() => props.element.props.footprint?.shape ?? 'circle');

const footprintRadius = computed(() => {
  const footprint = props.element.props.footprint;
  if (footprint?.shape === 'circle') {
    return footprint.radius;
  }

  if (footprint?.shape === 'polygon') {
    return outlineRadius(footprint.points);
  }

  const derived = decorDefaults.value.footprint;

  return derived.shape === 'circle' ? derived.radius : 0;
});

const footprintPointCount = computed(() => {
  const footprint = props.element.props.footprint;

  return footprint?.shape === 'polygon' ? footprint.points.length : 0;
});

/** Смещение всей формы основания от якоря, единицы сцены. */
const footprintOffset = computed<ScenePoint>(() => props.element.props.footprint?.offset ?? [0, 0]);

const blocksVision = computed(() => props.element.props.blocksVision !== false);

/**
 * Вид зоны непрозрачности: явный силуэт или, по умолчанию, основание.
 *
 * Пока силуэт не задан явно, зона совпадает с основанием — карточка и карта
 * показывают один и тот же контур.
 */
const silhouetteKind = computed(() => props.element.props.silhouette?.shape ?? decorDefaults.value.silhouette.shape);

const silhouetteRadius = computed(() => {
  const silhouette = props.element.props.silhouette;
  if (silhouette?.shape === 'circle') {
    return silhouette.radius;
  }

  if (silhouette?.shape === 'polygon') {
    return outlineRadius(silhouette.points);
  }

  const derived = decorDefaults.value.silhouette;

  return derived.shape === 'circle' ? derived.radius : outlineRadius(derived.points);
});

const silhouetteBottom = computed(() => {
  const silhouette = props.element.props.silhouette;

  return silhouette?.bottom ?? decorDefaults.value.silhouette.bottom;
});

const silhouettePointCount = computed(() => {
  const silhouette = props.element.props.silhouette;
  if (silhouette?.shape === 'polygon') {
    return silhouette.points.length;
  }

  const derived = decorDefaults.value.silhouette;

  return derived.shape === 'polygon' ? derived.points.length : 0;
});

/** Смещение зоны непрозрачности от якоря, единицы сцены. */
const silhouetteOffset = computed<ScenePoint>(() => props.element.props.silhouette?.offset ?? [0, 0]);

/** Зона ещё не отделена от основания: править её отдельно нечего. */
const silhouetteImplicit = computed(
  () => props.element.props.silhouette === undefined && props.element.props.footprint !== undefined,
);

/** Мост и съезд: у них `height` — толщина настила, а не высота объёма. */
const isDeck = computed(
  () =>
    props.element.kind === 'support' && (props.element.props.kind === 'bridge' || props.element.props.kind === 'ramp'),
);

/** Рост тела, под который проверяется зазор, единицы сцены. */
const bodyHeight = computed(() => Math.max(1, props.resolution) * SCENE_NAVIGATION.bodyHeightIpari);

/** Зазор под настилом выбранного моста или съезда, единицы сцены. */
const deckClearance = computed(() =>
  isDeck.value ? sceneDeckClearance(props.element, props.elements, props.resolution) : null,
);

/** Зазор под съездом на низком и высоком концах, единицы сцены. */
const rampClearance = computed(() => {
  if (props.element.props.kind !== 'ramp') {
    return null;
  }

  const slope = sceneRampSlope(props.element, props.elements, props.resolution);
  if (slope === null) {
    return null;
  }

  const base = sceneDeckBaseElevation(props.element, props.elements, props.resolution);
  const thickness = sceneDeckThickness(props.element, props.resolution);

  return { low: slope.low - thickness - base, high: slope.high - thickness - base };
});

/** Проход под настилом открыт, если зазор не меньше роста тела. */
const clearanceOpen = computed(() => {
  if (rampClearance.value !== null) {
    return rampClearance.value.high >= bodyHeight.value;
  }

  return deckClearance.value !== null && deckClearance.value >= bodyHeight.value;
});

/** Подсказка к полю подъёма: до какой высоты поднять настил. */
const elevationHint = computed(() => {
  if (!isDeck.value) {
    return undefined;
  }

  if (props.element.props.kind === 'ramp') {
    return 'Съезд садится низким концом на поверхность; высокий конец задаёт подъём.';
  }

  const required =
    sceneDeckBaseElevation(props.element, props.elements, props.resolution) +
    sceneDeckThickness(props.element, props.resolution) +
    bodyHeight.value;

  return `Проход под настилом: поднимите верхнюю поверхность до ${sceneUnits.formatUnits(required, props.resolution)} ипари, чтобы прошёл человек.`;
});

/** Зазор в ипари для карточки: у съезда — диапазон от низкого до высокого конца. */
const clearanceText = computed(() => {
  if (rampClearance.value !== null) {
    const low = sceneUnits.formatUnits(Math.round(rampClearance.value.low), props.resolution);
    const high = sceneUnits.formatUnits(Math.round(rampClearance.value.high), props.resolution);

    return `${low}…${high}`;
  }

  return deckClearance.value === null ? '' : sceneUnits.formatUnits(Math.round(deckClearance.value), props.resolution);
});

/** Статус прохода: у съезда низкий конец всегда лежит на поверхности. */
const clearanceStatus = computed(() => {
  if (rampClearance.value !== null) {
    return rampClearance.value.high >= bodyHeight.value
      ? 'под высоким концом открыт, под низким закрыт'
      : 'проход закрыт';
  }

  return clearanceOpen.value ? 'проход открыт' : 'проход закрыт';
});

/**
 * Съезды, которые можно приставить к торцам выбранного моста.
 *
 * Торец, к которому съезд не ставится (настил на земле), в список не попадает.
 * Если не осталось ни одного, кнопка не показывается.
 */
const rampAttachments = computed(() => {
  const list: { end: SceneDeckEnd; attachment: SceneRampAttachment }[] = [];
  if (props.element.props.kind !== 'bridge' || props.element.shape.type !== 'rect') {
    return list;
  }

  const board = props.boards.find((item) => item.id === props.element.boardId);
  for (const end of ['start', 'end'] as const) {
    const attachment = sceneRampAttachment(props.element, props.elements, props.resolution, end);
    if (attachment === null) {
      continue;
    }

    // Валидатор держит прямоугольник внутри поля: съезд, вылезший за край,
    // не сохранится, поэтому такой торец в меню не предлагаем.
    const shape = attachment.shape;
    if (
      board !== undefined &&
      (shape.x < 0 || shape.y < 0 || shape.x + shape.w > board.boundWidth || shape.y + shape.h > board.boundHeight)
    ) {
      continue;
    }

    list.push({ end, attachment });
  }

  return list;
});

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

/** Радиус круглого основания в единицах сцены. */
function patchFootprintRadius(value: number): void {
  patch({
    footprint: { shape: 'circle', radius: Math.max(1, Math.round(value)), offset: footprintOffset.value },
  });
}

/** Смещение всей формы основания: так круг отвязывается от якоря спрайта. */
function patchFootprintOffset(axis: 0 | 1, value: number): void {
  const current = footprintOffset.value;
  const offset: ScenePoint = axis === 0 ? [Math.round(value), current[1]] : [current[0], Math.round(value)];

  patch({ footprint: footprintWithOffset(offset) });
}

/** Текущая форма основания с новым смещением. */
function footprintWithOffset(offset: ScenePoint): SceneFootprint {
  const footprint = props.element.props.footprint;
  if (footprint?.shape === 'polygon') {
    return { ...footprint, offset };
  }

  const radius = footprint?.shape === 'circle' ? footprint.radius : Math.max(1, footprintRadius.value);

  return { shape: 'circle', radius, offset };
}

/** Смена типа основания: полигон начинается с восьмиугольника по кругу. */
function patchFootprintKind(value: string | null): void {
  if (value === 'polygon') {
    if (props.element.props.footprint?.shape === 'polygon') {
      return;
    }

    patch({
      footprint: {
        shape: 'polygon',
        points: regularPolygon(Math.max(1, footprintRadius.value)),
        offset: footprintOffset.value,
      },
    });

    return;
  }

  patch({
    footprint: { shape: 'circle', radius: Math.max(1, footprintRadius.value), offset: footprintOffset.value },
  });
}

/** Восьмиугольник вокруг якоря: стартовая форма для обводки. */
function regularPolygon(radius: number): ScenePoint[] {
  return Array.from({ length: 8 }, (_, index): ScenePoint => {
    const angle = (index / 8) * Math.PI * 2;

    return [Math.round(Math.cos(angle) * radius), Math.round(Math.sin(angle) * radius)];
  });
}

/** Радиус описанной окружности контура: стартовый круг при смене типа. */
function outlineRadius(points: ScenePoint[]): number {
  const radius = points.reduce((max, point) => Math.max(max, Math.hypot(point[0], point[1])), 0);

  return Math.max(1, Math.round(radius));
}

/** Радиус силуэта в единицах сцены; полигон не трогаем. */
function patchSilhouetteRadius(value: number): void {
  patch({
    silhouette: {
      shape: 'circle',
      radius: Math.max(1, Math.round(value)),
      bottom: silhouetteBottom.value,
      ...(silhouetteOffset.value[0] === 0 && silhouetteOffset.value[1] === 0 ? {} : { offset: silhouetteOffset.value }),
    },
  });
}

/** Низ силуэта: форму сохраняем, меняем только высоту. */
function patchSilhouetteBottom(value: number): void {
  patch({ silhouette: { ...silhouetteShape(), bottom: Math.max(0, Math.round(value)) } });
}

/** Текущая форма силуэта без высоты: явная или снятая с основания. */
function silhouetteShape(): SceneFootprint {
  const current = props.element.props.silhouette;
  if (current?.shape === 'polygon') {
    return { shape: 'polygon', points: current.points, ...offsetOf(current) };
  }

  if (current?.shape === 'circle') {
    return { shape: 'circle', radius: current.radius, ...offsetOf(current) };
  }

  const derived = decorDefaults.value.silhouette;

  // Неявная зона снята с основания: её смещение уже учтено, своё не навязываем.
  return derived.shape === 'polygon'
    ? { shape: 'polygon', points: derived.points, ...offsetOf(derived) }
    : { shape: 'circle', radius: derived.radius, ...offsetOf(derived) };
}

/** Смещение, если оно ненулевое: лишние поля в контракт не пишем. */
function offsetOf(footprint: SceneFootprint): { offset?: ScenePoint } {
  return footprint.offset === undefined ? {} : { offset: footprint.offset };
}

/** Смещение всей зоны непрозрачности от якоря спрайта. */
function patchSilhouetteOffset(axis: 0 | 1, value: number): void {
  const current = silhouetteOffset.value;
  const offset: ScenePoint = axis === 0 ? [Math.round(value), current[1]] : [current[0], Math.round(value)];

  patch({ silhouette: { ...silhouetteShape(), offset, bottom: silhouetteBottom.value } });
}

/** Смена вида зоны непрозрачности: полигон начинается с восьмиугольника по кругу. */
function patchSilhouetteKind(value: string | null): void {
  if (value === 'polygon') {
    if (props.element.props.silhouette?.shape === 'polygon') {
      return;
    }

    const shape = silhouetteShape();
    if (shape.shape === 'polygon') {
      patch({ silhouette: { ...shape, bottom: silhouetteBottom.value } });

      return;
    }

    patch({
      silhouette: {
        shape: 'polygon',
        points: regularPolygon(Math.max(1, shape.radius)),
        offset: silhouetteOffset.value,
        bottom: silhouetteBottom.value,
      },
    });

    return;
  }

  patch({
    silhouette: {
      shape: 'circle',
      radius: Math.max(1, silhouetteRadius.value),
      offset: silhouetteOffset.value,
      bottom: silhouetteBottom.value,
    },
  });
}

function outlineFootprint(): void {
  emit('outline', 'footprint');
}

function outlineSilhouette(): void {
  emit('outline', 'silhouette');
}

/** Отделяет зону непрозрачности от основания: копия становится явной. */
function detachSilhouette(): void {
  patch({ silhouette: { ...silhouetteShape(), bottom: silhouetteBottom.value } });
}

function onAsset(publicId: string | null): void {
  emit('asset', publicId, null, null);
}

/** Пресет декора: основание и силуэт подставляются по имени файла. */
function onAssetPreset(item: AssetItem): void {
  const preset = sceneDecorPreset(item.originalName ?? item.name);

  emit('asset', item.filePublicId, item.contentBox ?? null, preset === null ? null : sceneDecorPresetProps(preset));
}

/** Очистка ассета: `update:modelValue` приходит раньше `select`, берём только null. */
function onAssetCleared(publicId: string | null): void {
  if (publicId === null) {
    emit('asset', null, null, null);
  }
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
        :hint="isDeck ? 'Толщина настила: вертикальный размер для зазора и рёбер доступа.' : undefined"
        :min="0"
        :max="100000"
        @update:model-value="(value) => patch({ height: value })"
      />
      <SceneDistanceField
        :model-value="props.element.props.elevation ?? 0"
        :resolution="props.resolution"
        :label="isDeck ? 'Подъём настила, ипари' : 'Подъём, ипари'"
        :hint="isDeck ? elevationHint : undefined"
        :min="0"
        :max="100000"
        @update:model-value="(value) => patch({ elevation: value })"
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
      <div v-if="isDeck" class="scene-element-settings__clearance">
        <v-icon size="16" :color="clearanceOpen ? 'success' : 'error'">
          {{ clearanceOpen ? 'mdi-arrow-expand-vertical' : 'mdi-close-octagon-outline' }}
        </v-icon>
        <span>Зазор под настилом {{ clearanceText }} ипари — {{ clearanceStatus }}</span>
      </div>
      <v-menu v-if="rampAttachments.length > 0" location="bottom start">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" variant="tonal" size="small" prepend-icon="mdi-trending-up" block>
            Съезд к торцу
          </v-btn>
        </template>
        <v-list density="compact">
          <v-list-item
            v-for="item in rampAttachments"
            :key="item.end"
            :title="`К ${COMPASS_LABELS[item.attachment.direction]} торцу`"
            :subtitle="`длина ${sceneUnits.formatUnits(Math.round(item.attachment.length), props.resolution)} ипари`"
            @click="emit('attachRamp', item.end)"
          />
        </v-list>
      </v-menu>
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
        v-if="props.element.props.kind === 'water'"
        :model-value="props.element.props.depth ?? 0"
        :resolution="props.resolution"
        label="Глубина, ипари"
        :hint="depthHint"
        :min="0"
        :max="props.resolution * 3"
        @update:model-value="(value) => patch({ depth: value })"
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
        @update:model-value="onAssetCleared"
        @select="onAssetPreset"
      />
      <div class="scene-element-settings__row">
        <v-btn
          size="small"
          variant="tonal"
          prepend-icon="mdi-cube-outline"
          :disabled="assetPublicId === null"
          @click="emit('saveObject')"
        >
          Сохранить как объект
        </v-btn>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-cube-send" @click="emit('applyObject')">
          Взять из объекта
        </v-btn>
      </div>
      <span v-if="assetPublicId === null" class="text-caption text-medium-emphasis">
        Объект сохраняет основание, силуэт и скорость вместе со спрайтом. Без ассета сохранять нечего.
      </span>
      <v-text-field
        :model-value="currentSpeed"
        type="number"
        label="Скорость"
        hint="0 — непроходимо; 100 — можно пройти; выше — быстрее."
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
      <v-select
        :model-value="footprintKind"
        :items="FOOTPRINT_KINDS"
        label="Основание"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="patchFootprintKind"
      />
      <SceneDistanceField
        v-if="footprintKind === 'circle'"
        :model-value="footprintRadius"
        :resolution="props.resolution"
        label="Радиус основания, ипари"
        hint="Зона хода: поля картинки на неё не влияют."
        :min="1"
        :max="100000"
        @update:model-value="patchFootprintRadius"
      />
      <div v-else class="scene-element-settings__row">
        <span class="text-caption">Полигон: {{ footprintPointCount }} точек</span>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-vector-polygon" @click="outlineFootprint">
          Обвести
        </v-btn>
      </div>
      <div class="scene-element-settings__row">
        <SceneDistanceField
          :model-value="footprintOffset[0]"
          :resolution="props.resolution"
          label="Смещение X, ипари"
          hint="Сдвиг всей формы от якоря спрайта."
          :min="-100000"
          :max="100000"
          @update:model-value="(value) => patchFootprintOffset(0, value)"
        />
        <SceneDistanceField
          :model-value="footprintOffset[1]"
          :resolution="props.resolution"
          label="Смещение Y, ипари"
          :min="-100000"
          :max="100000"
          @update:model-value="(value) => patchFootprintOffset(1, value)"
        />
      </div>
      <div v-if="footprintKind === 'polygon'" class="scene-element-settings__row">
        <v-btn
          variant="tonal"
          size="small"
          prepend-icon="mdi-transit-connection-variant"
          @click="emit('footprintSmooth')"
        >
          Сгладить
        </v-btn>
        <v-btn variant="tonal" size="small" prepend-icon="mdi-vector-square" @click="emit('footprintCorner')">
          Углы
        </v-btn>
      </div>
      <span v-if="footprintKind === 'polygon'" class="text-caption">
        Вершины и ручки — на карте у выделенного объекта: тяните, двойной клик добавляет, Alt — удаляет.
      </span>
      <v-checkbox
        :model-value="blocksVision"
        label="Перекрывает обзор"
        density="compact"
        hide-details
        @update:model-value="patchFlag('blocksVision', $event)"
      />
      <template v-if="blocksVision">
        <div v-if="silhouetteImplicit" class="scene-element-settings__row">
          <span class="text-caption">Зона совпадает с основанием</span>
          <v-btn size="small" variant="tonal" prepend-icon="mdi-link-variant-off" @click="detachSilhouette">
            Отделить
          </v-btn>
        </div>
        <template v-else>
          <v-select
            :model-value="silhouetteKind"
            :items="FOOTPRINT_KINDS"
            label="Непрозрачность"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="patchSilhouetteKind"
          />
          <SceneDistanceField
            v-if="silhouetteKind === 'circle'"
            :model-value="silhouetteRadius"
            :resolution="props.resolution"
            label="Радиус силуэта, ипари"
            hint="Зона, от которой начинается тень."
            :min="1"
            :max="100000"
            @update:model-value="patchSilhouetteRadius"
          />
          <div v-else class="scene-element-settings__row">
            <span class="text-caption">Полигон: {{ silhouettePointCount }} точек</span>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-vector-polygon" @click="outlineSilhouette">
              Обвести
            </v-btn>
          </div>
          <div class="scene-element-settings__row">
            <SceneDistanceField
              :model-value="silhouetteOffset[0]"
              :resolution="props.resolution"
              label="Смещение X, ипари"
              :min="-100000"
              :max="100000"
              @update:model-value="(value) => patchSilhouetteOffset(0, value)"
            />
            <SceneDistanceField
              :model-value="silhouetteOffset[1]"
              :resolution="props.resolution"
              label="Смещение Y, ипари"
              :min="-100000"
              :max="100000"
              @update:model-value="(value) => patchSilhouetteOffset(1, value)"
            />
          </div>
          <div v-if="silhouetteKind === 'polygon'" class="scene-element-settings__row">
            <v-btn
              variant="tonal"
              size="small"
              prepend-icon="mdi-transit-connection-variant"
              @click="emit('silhouetteSmooth')"
            >
              Сгладить
            </v-btn>
            <v-btn variant="tonal" size="small" prepend-icon="mdi-vector-square" @click="emit('silhouetteCorner')">
              Углы
            </v-btn>
          </div>
          <span v-if="silhouetteKind === 'polygon'" class="text-caption">
            Вершины и ручки зоны — на карте, как у основания.
          </span>
        </template>
        <SceneDistanceField
          :model-value="silhouetteBottom"
          :resolution="props.resolution"
          label="Низ силуэта, ипари"
          hint="0 — перекрывает от земли; у кроны — выше глаз."
          :min="0"
          :max="100000"
          @update:model-value="patchSilhouetteBottom"
        />
      </template>
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

.scene-element-settings__clearance {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8125rem;
  line-height: 1.25;
  opacity: 0.9;
}
</style>
