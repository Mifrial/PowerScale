<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapLineDecor } from '@/modules/Roleplay/Map/Dto/MapLineDecor';
import type { MapLineEdit } from '@/modules/Roleplay/Map/Dto/MapLineEdit';
import type { MapLineSegment } from '@/modules/Roleplay/Map/Dto/MapLineSegment';
import { MAP_LINE_DEFAULT_COLOR_BLEND, MAP_LINE_FLOW_DEFAULTS, MAP_LINE_FLOW_OFF } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DECOR';
import { MAP_LINE_DEFAULT_WIDTH } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DEFAULT_WIDTH';
import { MAP_LINE_PRESETS } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_PRESETS';
import { MAP_LINE_SEGMENT_PRESETS } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_SEGMENT_PRESETS';
import {
  MAP_LINE_DEFAULT_COLOR,
  MAP_SPEED_DEFAULT,
  speedFromInput,
  speedToInput,
} from '@/modules/Roleplay/Map/Constant/Map/MAP_SPEED';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  line: MapLine | null;
  submitting: boolean;
  defaults?: { color: string; speed: number; width: number; colorBlend?: number; decor?: MapLineDecor } | null;
}>();

const emit = defineEmits<{
  submit: [edit: MapLineEdit];
  remove: [lineId: number];
}>();

interface SegmentForm {
  speed: number;
  color: string;
}

const form = reactive({
  color: MAP_LINE_DEFAULT_COLOR,
  speed: speedToInput(MAP_SPEED_DEFAULT),
  width: MAP_LINE_DEFAULT_WIDTH,
  widthAll: false,
  label: '',
  segments: [] as SegmentForm[],
  colorBlend: MAP_LINE_DEFAULT_COLOR_BLEND,
  flow: { ...MAP_LINE_FLOW_OFF },
});
const preset = ref<string | null>(null);
const openBase = reactive({ speed: speedToInput(MAP_SPEED_DEFAULT), color: MAP_LINE_DEFAULT_COLOR });
const gradientEnabled = computed({
  get: () => form.colorBlend > 0,
  set: (value: boolean) => {
    form.colorBlend = value ? form.colorBlend || MAP_LINE_DEFAULT_COLOR_BLEND : 0;
  },
});
const directionOptions = [
  { title: 'По точкам', value: 1 },
  { title: 'Против точек', value: -1 },
];

const presetOptions = computed(() => MAP_LINE_PRESETS.map((item) => ({ title: item.title, value: item.code })));
const segmentPresetOptions = computed(() =>
  MAP_LINE_SEGMENT_PRESETS.map((item) => ({ title: item.title, value: item.code })),
);
const widthRange = computed(() => {
  if (!props.line || props.line.widths.length === 0) return null;
  const min = Math.min(...props.line.widths);
  const max = Math.max(...props.line.widths);

  return min === max ? `${min} px` : `${min}–${max} px`;
});
const segmentCount = computed(() => (props.line ? Math.max(0, props.line.points.length - 1) : 0));

watch(open, (value) => {
  if (!value) return;
  preset.value = null;
  form.color = props.line?.color ?? props.defaults?.color ?? MAP_LINE_DEFAULT_COLOR;
  form.speed = speedToInput(props.line?.speed ?? props.defaults?.speed ?? MAP_SPEED_DEFAULT);
  openBase.speed = form.speed;
  openBase.color = form.color;
  form.width = props.line ? Math.max(1, ...props.line.widths) : (props.defaults?.width ?? MAP_LINE_DEFAULT_WIDTH);
  form.widthAll = props.line !== null;
  form.label = props.line?.label ?? '';
  form.colorBlend = props.line?.colorBlend ?? props.defaults?.colorBlend ?? MAP_LINE_DEFAULT_COLOR_BLEND;
  Object.assign(form.flow, props.line?.decor?.flow ?? props.defaults?.decor?.flow ?? MAP_LINE_FLOW_OFF);
  form.segments = effectiveSegments(props.line, form.speed, form.color);
});

function effectiveSegments(line: MapLine | null, speed: number, color: string): SegmentForm[] {
  if (!line) return [];
  const count = Math.max(0, line.points.length - 1);

  return Array.from({ length: count }, (_, index) => {
    const segment = line.segments[index];

    return {
      speed: speedToInput(segment?.speed ?? line.speed ?? speedFromInput(speed)),
      color: segment?.color ?? line.color ?? color,
    };
  });
}

function applyPreset(code: string | null): void {
  preset.value = code;
  const found = MAP_LINE_PRESETS.find((item) => item.code === code);
  if (!found) return;
  form.color = found.color;
  form.speed = speedToInput(found.speed);
  form.width = found.width;
  form.colorBlend = found.colorBlend ?? MAP_LINE_DEFAULT_COLOR_BLEND;
  Object.assign(form.flow, found.decor?.flow ?? MAP_LINE_FLOW_OFF);
  if (form.label.trim() === '') form.label = found.title;
}

function applyBaseToAll(): void {
  form.segments = form.segments.map(() => ({ speed: form.speed, color: form.color }));
}

function resetSegments(): void {
  form.segments = effectiveSegments(props.line, form.speed, form.color);
}

function presetCodeOf(segment: SegmentForm): string | null {
  const found = MAP_LINE_SEGMENT_PRESETS.find(
    (item) =>
      speedToInput(item.segment.speed) === segment.speed &&
      item.segment.color?.toLowerCase() === segment.color.toLowerCase(),
  );

  return found?.code ?? null;
}

function applySegmentPreset(index: number, code: string | null): void {
  const found = MAP_LINE_SEGMENT_PRESETS.find((item) => item.code === code);
  if (!found) return;
  form.segments[index] = {
    speed: speedToInput(found.segment.speed),
    color: found.segment.color ?? form.color,
  };
}

function submit(): void {
  const baseSpeed = speedFromInput(Number(form.speed));
  const segments: MapLineSegment[] = [];
  let hasOverrides = false;
  for (const segment of form.segments) {
    // Значение, равное базе на момент открытия, означает «наследовать базу».
    const inherited = segment.speed === openBase.speed && segment.color === openBase.color;
    const speed = inherited ? baseSpeed : speedFromInput(Number(segment.speed));
    const color = inherited
      ? form.color
      : segment.color === form.color
        ? null
        : segment.color;
    if (speed !== baseSpeed || color !== null) hasOverrides = true;
    segments.push({ speed, color });
  }
  const decor: MapLineDecor = form.flow.enabled
    ? {
        flow: {
          enabled: true,
          speed: clampInt(form.flow.speed, 1, 600, MAP_LINE_FLOW_DEFAULTS.speed),
          density: clampInt(form.flow.density, 0, 100, MAP_LINE_FLOW_DEFAULTS.density),
          opacity: clampInt(form.flow.opacity, 0, 100, MAP_LINE_FLOW_DEFAULTS.opacity),
          color: form.flow.color.trim() || MAP_LINE_FLOW_DEFAULTS.color,
          direction: form.flow.direction === -1 ? -1 : 1,
          seed: clampInt(form.flow.seed, 0, 1000000, MAP_LINE_FLOW_DEFAULTS.seed),
        },
      }
    : {};
  emit('submit', {
    color: form.color.trim() || MAP_LINE_DEFAULT_COLOR,
    speed: baseSpeed,
    width: Math.max(1, Math.round(form.width)),
    widthAll: form.widthAll,
    label: form.label.trim(),
    segments: hasOverrides ? segments : [],
    colorBlend: clampInt(form.colorBlend, 0, 1000, MAP_LINE_DEFAULT_COLOR_BLEND),
    decor,
  });
}

function clampInt(value: number, min: number, max: number, fallback: number): number {
  const number = Math.round(Number(value));
  if (!Number.isFinite(number)) return fallback;

  return Math.min(max, Math.max(min, number));
}

function remove(): void {
  if (props.line) emit('remove', props.line.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="640" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">
        {{ props.line ? 'Лента карты' : 'Новая лента' }}
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
        <div class="d-flex align-center ga-2 mt-3">
          <input v-model="form.color" type="color" class="map-color" aria-label="Цвет ленты" />
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
        <div class="d-flex align-end ga-2 mt-3">
          <v-text-field
            v-model.number="form.width"
            type="number"
            min="1"
            label="Ширина линии, px"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <v-checkbox
            v-if="props.line"
            v-model="form.widthAll"
            label="Применить ко всем точкам"
            density="compact"
            hide-details
            class="flex-grow-0"
          />
        </div>
        <div v-if="props.line" class="text-caption text-medium-emphasis mt-1">
          Сейчас ширина по точкам: {{ widthRange }}. Галочка применяет поле ко всем точкам; без неё ширина не
          меняется — точечно она правится ромбом на карте.
        </div>

        <v-divider class="my-4" />
        <v-switch
          v-model="gradientEnabled"
          label="Плавные переходы цвета"
          color="primary"
          density="compact"
          hide-details
        />
        <v-text-field
          v-if="gradientEnabled"
          v-model.number="form.colorBlend"
          type="number"
          min="1"
          max="1000"
          step="1"
          label="Ширина перехода, px"
          variant="outlined"
          density="comfortable"
          class="mt-2"
        />

        <v-switch
          v-model="form.flow.enabled"
          label="Течение воды"
          color="primary"
          density="compact"
          hide-details
          class="mt-4"
        />
        <template v-if="form.flow.enabled">
          <v-row dense class="mt-1">
            <v-col cols="6">
              <v-text-field
                v-model.number="form.flow.speed"
                type="number"
                min="1"
                max="600"
                label="Скорость, px/с"
                variant="outlined"
                density="comfortable"
              />
            </v-col>
            <v-col cols="6">
              <v-select
                v-model="form.flow.direction"
                :items="directionOptions"
                item-title="title"
                item-value="value"
                label="Направление"
                variant="outlined"
                density="comfortable"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="form.flow.density"
                type="number"
                min="0"
                max="100"
                label="Плотность, 0–100"
                variant="outlined"
                density="comfortable"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="form.flow.opacity"
                type="number"
                min="0"
                max="100"
                label="Прозрачность, %"
                variant="outlined"
                density="comfortable"
              />
            </v-col>
          </v-row>
          <div class="d-flex align-center ga-2">
            <input v-model="form.flow.color" type="color" class="map-color" aria-label="Цвет бликов" />
            <v-text-field
              v-model="form.flow.color"
              label="Цвет бликов"
              variant="outlined"
              density="comfortable"
              hide-details
            />
            <v-text-field
              v-model.number="form.flow.seed"
              type="number"
              min="0"
              max="1000000"
              label="Зерно"
              variant="outlined"
              density="comfortable"
              hide-details
              style="max-width: 140px"
            />
          </div>
        </template>

        <v-text-field v-model="form.label" label="Подпись" variant="outlined" density="comfortable" class="mt-3" />

        <template v-if="props.line && segmentCount > 0">
          <v-divider class="my-4" />
          <div class="d-flex align-center mb-2">
            <span class="text-body-2">Сегменты</span>
            <v-spacer />
            <v-btn size="small" variant="text" @click="applyBaseToAll">Ко всему</v-btn>
            <v-btn size="small" variant="text" @click="resetSegments">Сбросить</v-btn>
          </div>
          <p class="text-caption text-medium-emphasis mb-2">
            Брод — это сегмент со своей скоростью и цветом, а не отдельный тип ленты.
          </p>
          <div v-for="(segment, index) in form.segments" :key="index" class="d-flex align-center ga-2 mb-2">
            <span class="text-caption text-medium-emphasis" style="min-width: 92px">
              {{ index + 1 }}: точка {{ index + 1 }} → {{ index + 2 }}
            </span>
            <v-select
              :model-value="presetCodeOf(segment)"
              :items="segmentPresetOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="compact"
              hide-details
              placeholder="Стиль"
              style="max-width: 150px"
              @update:model-value="applySegmentPreset(index, $event)"
            />
            <v-text-field
              v-model.number="segment.speed"
              type="number"
              min="0"
              max="100"
              variant="outlined"
              density="compact"
              hide-details
              style="max-width: 88px"
            />
            <input v-model="segment.color" type="color" class="map-color map-color--small" aria-label="Цвет сегмента" />
          </div>
        </template>

        <p class="text-caption text-medium-emphasis mt-3">
          Кривизна задаётся ручками Безье на карте. Shift при перетаскивании ромба ширины масштабирует все точки.
        </p>
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="props.line"
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

.map-color--small {
  width: 40px;
  height: 32px;
  flex: 0 0 40px;
}
</style>
