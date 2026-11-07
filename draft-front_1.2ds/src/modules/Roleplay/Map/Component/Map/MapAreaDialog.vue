<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';
import { MAP_AREA_PRESETS } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_PRESETS';
import { MAP_FILL_OPACITY } from '@/modules/Roleplay/Map/Constant/Map/MAP_FILL_OPACITY';
import {
  MAP_AREA_DEFAULT_COLOR,
  MAP_SPEED_DEFAULT,
  speedFromInput,
  speedToInput,
} from '@/modules/Roleplay/Map/Constant/Map/MAP_SPEED';
import { clampInt } from '@/modules/Roleplay/Map/Utils/clampInt';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  area: MapArea | null;
  node: MapNode | null;
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
  speed: 0,
  label: '',
  sortOrder: 0,
  fillOpacityOverride: false,
  fillOpacity: Number(MAP_FILL_OPACITY.default),
  hasPlace: false,
  placeName: '',
  shortInfo: '',
  publicInfo: '',
  gmInfo: '',
  icon: '',
  linkedMapId: null as number | null,
});

const preset = ref<string | null>(null);

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
  form.speed = speedToInput(props.area?.speed ?? (form.layer === 'surface' ? MAP_SPEED_DEFAULT : 0));
  form.label = props.area?.label ?? '';
  form.sortOrder = props.area?.sortOrder ?? 0;
  form.fillOpacityOverride = props.area?.decor?.fillOpacity !== undefined;
  form.fillOpacity = props.area?.decor?.fillOpacity ?? props.mapFillOpacity;
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
  form.speed = speedToInput(found.speed);
  if (form.label.trim() === '') {
    form.label = found.title;
  }
}

function submit(): void {
  emit('submit', {
    layer: form.layer,
    color: form.color.trim() || MAP_AREA_DEFAULT_COLOR,
    speed: speedFromInput(Number(form.speed)),
    decor: form.fillOpacityOverride
      ? { fillOpacity: clampInt(form.fillOpacity, MAP_FILL_OPACITY.min, MAP_FILL_OPACITY.max, props.mapFillOpacity) }
      : {},
    label: form.label.trim(),
    nodeId: props.area?.nodeId ?? null,
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
