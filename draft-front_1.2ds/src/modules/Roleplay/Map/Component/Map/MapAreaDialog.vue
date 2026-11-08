<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaDecor, MapDecorSet } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapAreaGroup } from '@/modules/Roleplay/Map/Dto/MapAreaGroup';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';
import { MAP_AREA_PRESETS } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_PRESETS';
import {
  MAP_DECOR_LIMITS,
  MAP_DECOR_OPTIONS,
  MAP_DECOR_SETS,
  isMapDecorSet,
} from '@/modules/Roleplay/Map/Constant/Map/MAP_DECOR';
import { MAP_FILL_OPACITY } from '@/modules/Roleplay/Map/Constant/Map/MAP_FILL_OPACITY';
import { MAP_AREA_STROKE } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_STROKE';
import {
  MAP_AREA_DEFAULT_COLOR,
  MAP_SPEED_DEFAULT,
  speedFromInput,
  speedToInput,
} from '@/modules/Roleplay/Map/Constant/Map/MAP_SPEED';
import { clampInt } from '@/modules/Roleplay/Map/Utils/clampInt';
import { autoStrokeColor } from '@/modules/Roleplay/Map/Utils/color';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  area: MapArea | null;
  node: MapNode | null;
  groups: MapAreaGroup[];
  mapOptions: MapSummary[];
  mapFillOpacity: number;
  submitting: boolean;
}>();

const emit = defineEmits<{
  submit: [edit: MapAreaEdit];
  remove: [areaId: number];
}>();

const form = reactive({
  layer: 'object' as MapAreaLayer,
  color: MAP_AREA_DEFAULT_COLOR,
  strokeEnabled: true,
  stroke: autoStrokeColor(MAP_AREA_DEFAULT_COLOR, MAP_AREA_STROKE.autoDarken),
  strokeWidth: MAP_AREA_STROKE.defaultWidth as number,
  speed: 0,
  label: '',
  groupId: null as number | null,
  sortOrder: 0,
  fillOpacityOverride: false,
  fillOpacity: Number(MAP_FILL_OPACITY.default),
  decorEnabled: false,
  decorSet: 'grass' as MapDecorSet,
  decorDensity: 0,
  decorScale: 100,
  decorJitter: 90,
  decorSeed: 0,
  hasPlace: false,
  placeName: '',
  shortInfo: '',
  publicInfo: '',
  gmInfo: '',
  icon: '',
  linkedMapId: null as number | null,
});

const preset = ref<string | null>(null);

const groupOptions = computed(() => [
  ...props.groups.map((group) => ({ title: group.label || 'Группа', value: group.id })),
]);

const presetOptions = computed(() =>
  MAP_AREA_PRESETS.map((item) => ({
    title: item.title,
    value: item.code,
    subtitle: item.layer === 'object' ? 'объект' : 'поверхность',
  })),
);

const layerOptions: { title: string; value: MapAreaLayer }[] = [
  { title: 'Объект', value: 'object' },
  { title: 'Поверхность', value: 'surface' },
];

watch(open, (value) => {
  if (!value) return;
  preset.value = null;
  form.layer = props.area?.layer ?? 'object';
  form.color = props.area?.color ?? MAP_AREA_DEFAULT_COLOR;
  form.strokeEnabled = props.area ? (props.area.stroke ?? '') !== '' : true;
  form.stroke = props.area?.stroke || autoStrokeColor(form.color, MAP_AREA_STROKE.autoDarken);
  form.strokeWidth = props.area?.strokeWidth ?? MAP_AREA_STROKE.defaultWidth;
  form.speed = speedToInput(props.area?.speed ?? (form.layer === 'surface' ? MAP_SPEED_DEFAULT : 0));
  form.label = props.area?.label ?? '';
  form.groupId = props.area?.groupId ?? null;
  form.sortOrder = props.area?.sortOrder ?? 0;
  form.fillOpacityOverride = props.area?.decor?.fillOpacity !== undefined;
  form.fillOpacity = props.area?.decor?.fillOpacity ?? props.mapFillOpacity;
  loadDecor(props.area?.decor);
  form.hasPlace = props.node !== null;
  form.placeName = props.node?.name ?? '';
  form.shortInfo = props.node?.shortInfo ?? '';
  form.publicInfo = props.node?.publicInfo ?? '';
  form.gmInfo = props.node?.gmInfo ?? '';
  form.icon = props.node?.icon ?? '';
  form.linkedMapId = props.node?.linkedMapId ?? null;
});

function applyPreset(code: string | null): void {
  preset.value = code;
  const found = MAP_AREA_PRESETS.find((item) => item.code === code);
  if (!found) return;
  form.layer = found.layer;
  form.color = found.color;
  form.strokeEnabled = true;
  form.stroke = autoStrokeColor(found.color, MAP_AREA_STROKE.autoDarken);
  form.speed = speedToInput(found.speed);
  loadDecor(found.decor);
  if (form.label.trim() === '') {
    form.label = found.title;
  }
}

/** Заполняет поля декора: из области или из шаблона; без набора — выключает. */
function loadDecor(decor: MapAreaDecor | undefined): void {
  if (!decor?.set || !isMapDecorSet(decor.set)) {
    form.decorEnabled = false;
    applyDecorDefaults('grass');

    return;
  }

  form.decorEnabled = true;
  form.decorSet = decor.set;
  const info = MAP_DECOR_SETS[decor.set];
  form.decorDensity = decor.density ?? info.density;
  form.decorScale = decor.scale ?? info.scale;
  form.decorJitter = decor.jitter ?? info.jitter;
  form.decorSeed = decor.seed ?? 0;
}

/** Стартовые значения набора: смена набора не должна оставлять чужую плотность. */
function applyDecorDefaults(set: MapDecorSet): void {
  const info = MAP_DECOR_SETS[set];
  form.decorSet = set;
  form.decorDensity = info.density;
  form.decorScale = info.scale;
  form.decorJitter = info.jitter;
}

function onDecorSetChange(value: MapDecorSet | null): void {
  if (value && isMapDecorSet(value)) {
    applyDecorDefaults(value);
  }
}

function submit(): void {
  const decor: MapAreaDecor = {};
  if (form.fillOpacityOverride) {
    decor.fillOpacity = clampInt(form.fillOpacity, MAP_FILL_OPACITY.min, MAP_FILL_OPACITY.max, props.mapFillOpacity);
  }

  if (form.decorEnabled) {
    const info = MAP_DECOR_SETS[form.decorSet];
    decor.set = form.decorSet;
    decor.density = clampInt(
      form.decorDensity,
      MAP_DECOR_LIMITS.density.min,
      MAP_DECOR_LIMITS.density.max,
      info.density,
    );
    decor.scale = clampInt(form.decorScale, MAP_DECOR_LIMITS.scale.min, MAP_DECOR_LIMITS.scale.max, info.scale);
    decor.jitter = clampInt(form.decorJitter, MAP_DECOR_LIMITS.jitter.min, MAP_DECOR_LIMITS.jitter.max, info.jitter);
    decor.seed = clampInt(form.decorSeed, MAP_DECOR_LIMITS.seed.min, MAP_DECOR_LIMITS.seed.max, 0);
  }

  emit('submit', {
    layer: form.layer,
    color: form.color.trim() || MAP_AREA_DEFAULT_COLOR,
    stroke: form.strokeEnabled
      ? /^#[0-9a-fA-F]{6}$/.test(form.stroke.trim())
        ? form.stroke.trim().toLowerCase()
        : autoStrokeColor(form.color.trim() || MAP_AREA_DEFAULT_COLOR, MAP_AREA_STROKE.autoDarken)
      : '',
    strokeWidth: clampInt(
      form.strokeWidth,
      MAP_AREA_STROKE.minWidth,
      MAP_AREA_STROKE.maxWidth,
      MAP_AREA_STROKE.defaultWidth,
    ),
    speed: speedFromInput(Number(form.speed)),
    decor,
    label: form.label.trim(),
    nodeId: props.area?.nodeId ?? null,
    groupId: form.groupId,
    sortOrder: form.sortOrder,
    place: form.hasPlace
      ? {
          name: form.placeName.trim() || form.label.trim() || 'Место',
          shortInfo: form.shortInfo.trim(),
          publicInfo: form.publicInfo.trim(),
          gmInfo: form.gmInfo.trim(),
          icon: form.icon.trim(),
          linkedMapId: form.linkedMapId,
        }
      : null,
  });
}

function remove(): void {
  if (props.area) emit('remove', props.area.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="640" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">
        {{ props.area ? 'Область карты' : 'Новая область' }}
      </v-card-title>
      <v-card-text>
        <v-select
          :model-value="preset"
          :items="presetOptions"
          item-title="title"
          item-value="value"
          label="Шаблон"
          variant="outlined"
          density="comfortable"
          clearable
          @update:model-value="applyPreset"
        />
        <v-select
          v-model="form.layer"
          :items="layerOptions"
          item-title="title"
          item-value="value"
          label="Слой"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
        <div class="d-flex align-center ga-2 mt-3">
          <input v-model="form.color" type="color" class="map-color" aria-label="Цвет области" />
          <v-text-field v-model="form.color" label="Цвет" variant="outlined" density="comfortable" hide-details />
        </div>
        <v-switch
          v-model="form.strokeEnabled"
          label="Граница"
          color="primary"
          density="compact"
          hide-details
          class="mt-2"
        />
        <template v-if="form.strokeEnabled">
          <div class="d-flex align-center ga-2 mt-1">
            <input v-model="form.stroke" type="color" class="map-color" aria-label="Цвет границы" />
            <v-text-field
              v-model="form.stroke"
              label="Цвет границы"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </div>
          <v-slider
            v-model.number="form.strokeWidth"
            :min="MAP_AREA_STROKE.minWidth"
            :max="MAP_AREA_STROKE.maxWidth"
            :step="1"
            thumb-label
            color="primary"
            label="Толщина границы, px"
            density="compact"
            hide-details
            class="mt-1"
          />
        </template>
        <v-text-field
          v-model.number="form.speed"
          type="number"
          min="0"
          max="100"
          step="1"
          label="Скорость (0 — непроходимо)"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
        <p class="text-caption text-medium-emphasis mt-1">
          50 — обычная земля, 70 — грунтовая дорога, 100 — каменная; лес 20, брод 10, река 4.
        </p>
        <v-text-field v-model="form.label" label="Подпись" variant="outlined" density="comfortable" class="mt-3" />
        <v-select
          v-model="form.groupId"
          :items="groupOptions"
          item-title="title"
          item-value="value"
          label="Группа"
          variant="outlined"
          density="comfortable"
          clearable
          class="mt-3"
        />

        <v-divider class="my-4" />
        <div class="text-body-2 mb-2">Заливка</div>
        <v-switch
          v-model="form.fillOpacityOverride"
          label="Своя плотность заливки"
          color="primary"
          density="compact"
          hide-details
        />
        <v-slider
          v-if="form.fillOpacityOverride"
          v-model.number="form.fillOpacity"
          :min="MAP_FILL_OPACITY.min"
          :max="MAP_FILL_OPACITY.max"
          :step="1"
          thumb-label
          color="primary"
          label="Плотность, %"
        />
        <p v-else class="text-caption text-medium-emphasis mt-1">
          Наследует плотность карты: {{ props.mapFillOpacity }} %.
        </p>

        <v-divider class="my-4" />
        <div class="text-body-2 mb-2">Декор символами</div>
        <v-switch
          v-model="form.decorEnabled"
          label="Рассыпать символы по области"
          color="primary"
          density="compact"
          hide-details
        />
        <template v-if="form.decorEnabled">
          <v-select
            :model-value="form.decorSet"
            :items="MAP_DECOR_OPTIONS"
            item-title="title"
            item-value="value"
            label="Набор"
            variant="outlined"
            density="comfortable"
            class="mt-3"
            @update:model-value="onDecorSetChange"
          />
          <v-slider
            v-model.number="form.decorDensity"
            :min="MAP_DECOR_LIMITS.density.min"
            :max="MAP_DECOR_LIMITS.density.max"
            :step="1"
            thumb-label
            color="primary"
            label="Плотность, символов на 10 000 px²"
          />
          <v-slider
            v-model.number="form.decorScale"
            :min="MAP_DECOR_LIMITS.scale.min"
            :max="MAP_DECOR_LIMITS.scale.max"
            :step="1"
            thumb-label
            color="primary"
            label="Размер символа, %"
          />
          <v-slider
            v-model.number="form.decorJitter"
            :min="MAP_DECOR_LIMITS.jitter.min"
            :max="MAP_DECOR_LIMITS.jitter.max"
            :step="1"
            thumb-label
            color="primary"
            label="Разброс позиции, %"
          />
          <v-text-field
            v-model.number="form.decorSeed"
            type="number"
            :min="MAP_DECOR_LIMITS.seed.min"
            :max="MAP_DECOR_LIMITS.seed.max"
            label="Зерно раскладки (0 — по id области)"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <p class="text-caption text-medium-emphasis mt-2">
            Символы детерминированы зерном и могут выходить за контур — так край не выглядит обрезанным. Декор только
            визуальный: маршрут, проходимость и стоимость не меняются.
          </p>
        </template>

        <v-divider class="my-4" />
        <v-switch
          v-model="form.hasPlace"
          label="Это место с информацией"
          color="primary"
          density="compact"
          hide-details
        />
        <template v-if="form.hasPlace">
          <v-row dense class="mt-2">
            <v-col cols="12" sm="8">
              <v-text-field v-model="form.placeName" label="Имя места" variant="outlined" density="comfortable" />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="form.icon"
                label="Иконка (mdi-…)"
                variant="outlined"
                density="comfortable"
                placeholder="mdi-map-marker"
              />
            </v-col>
          </v-row>
          <v-textarea
            v-model="form.shortInfo"
            label="Короткая информация"
            variant="outlined"
            density="comfortable"
            rows="2"
            class="mt-2"
          />
          <v-textarea
            v-model="form.publicInfo"
            label="Раскрываемая информация"
            variant="outlined"
            density="comfortable"
            rows="2"
            class="mt-2"
          />
          <v-textarea
            v-model="form.gmInfo"
            label="Информация ведущего"
            variant="outlined"
            density="comfortable"
            rows="2"
            class="mt-2"
          />
          <v-select
            v-model="form.linkedMapId"
            :items="props.mapOptions"
            item-title="name"
            item-value="id"
            label="Связанная карта"
            variant="outlined"
            density="comfortable"
            clearable
            class="mt-2"
          />
        </template>
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="props.area"
          color="error"
          variant="text"
          :disabled="props.submitting"
          prepend-icon="mdi-delete-outline"
          @click="remove"
        >
          Удалить
        </v-btn>
        <v-spacer />
        <v-btn variant="text" :disabled="props.submitting" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" variant="tonal" :loading="props.submitting" @click="submit">Применить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.map-color {
  width: 48px;
  height: 40px;
  flex: 0 0 48px;
  padding: 0;
  border: 1px solid rgba(0, 0, 0, 0.24);
  border-radius: 4px;
  background: transparent;
}
</style>
