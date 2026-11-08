<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import type { useMapRecognition } from '@/modules/Roleplay/Map/Composables/useMapRecognition';
import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import { MAP_AREA_PRESETS } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_PRESETS';
import { MAP_FILL_OPACITY } from '@/modules/Roleplay/Map/Constant/Map/MAP_FILL_OPACITY';
import { MAP_RECOGNITION } from '@/modules/Roleplay/Map/Constant/Map/MAP_RECOGNITION';
import { MAP_AREA_STROKE } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_STROKE';
import { speedFromInput, speedToInput } from '@/modules/Roleplay/Map/Constant/Map/MAP_SPEED';
import { clampInt } from '@/modules/Roleplay/Map/Utils/clampInt';
import { autoStrokeColor } from '@/modules/Roleplay/Map/Utils/color';

const props = defineProps<{
  recognition: ReturnType<typeof useMapRecognition>;
  mapFillOpacity: number;
  disabled: boolean;
}>();

const emit = defineEmits<{
  created: [count: number];
}>();

const notice = ref<string | null>(null);
const preset = ref<string | null>('house');
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
const setTolerance = (value: number): void => props.recognition.updateSettings({ tolerance: Number(value) });
const setIgnoreLuminance = (value: boolean | null): void =>
  props.recognition.updateSettings({ ignoreLuminance: value === true });
const setOnlyVisible = (value: boolean | null): void =>
  props.recognition.updateSettings({ onlyVisible: value === true });
const setMinArea = (value: number | string): void => props.recognition.updateSettings({ minArea: Number(value) });
const setMaxArea = (value: number | string): void => props.recognition.updateSettings({ maxArea: Number(value) });
const setGap = (value: number | string): void => props.recognition.updateSettings({ gap: Number(value) });
const setSimplify = (value: number | string): void => props.recognition.updateSettings({ simplify: Number(value) });
const setMaxVertices = (value: number | string): void =>
  props.recognition.updateSettings({ maxVertices: Number(value) });
const setSmoothArcs = (value: boolean | null): void =>
  props.recognition.updateSettings({ smoothArcs: value === true });

const form = reactive({
  layer: 'object' as MapAreaLayer,
  color: '#b08968',
  strokeEnabled: true,
  stroke: autoStrokeColor('#b08968', MAP_AREA_STROKE.autoDarken),
  strokeWidth: MAP_AREA_STROKE.defaultWidth as number,
  speed: speedToInput(0),
  label: '',
  groupLabel: 'Дома',
  fillOpacityEnabled: true,
  fillOpacity: 25,
});

function applyPreset(code: string | null): void {
  preset.value = code;
  const found = MAP_AREA_PRESETS.find((item) => item.code === code);
  if (!found) return;
  form.layer = found.layer;
  form.color = found.color;
  form.stroke = autoStrokeColor(found.color, MAP_AREA_STROKE.autoDarken);
  form.speed = speedToInput(found.speed);
  if (form.groupLabel.trim() === '' || MAP_AREA_PRESETS.some((item) => item.title === form.groupLabel)) {
    form.groupLabel = found.title;
  }
}

function create(): void {
  if (props.disabled || props.recognition.state.isBusy) return;
  const decor: MapAreaDecor = { ...(MAP_AREA_PRESETS.find((item) => item.code === preset.value)?.decor ?? {}) };
  if (form.fillOpacityEnabled) {
    decor.fillOpacity = clampInt(form.fillOpacity, MAP_FILL_OPACITY.min, MAP_FILL_OPACITY.max, props.mapFillOpacity);
  }
  const edit: MapAreaEdit = {
    layer: form.layer,
    color: form.color.trim() || MAP_AREA_PRESETS[0].color,
    stroke: form.strokeEnabled
      ? /^#[0-9a-fA-F]{6}$/.test(form.stroke.trim())
        ? form.stroke.trim().toLowerCase()
        : autoStrokeColor(form.color.trim() || MAP_AREA_PRESETS[0].color, MAP_AREA_STROKE.autoDarken)
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
    nodeId: null,
    groupId: null,
    sortOrder: 0,
    place: null,
  };
  try {
    const created = props.recognition.createObjects(edit, form.groupLabel);
    notice.value = created > 0 ? `Создано объектов: ${created}` : 'Нечего создавать: все пятна исключены';
    if (created > 0) emit('created', created);
  } catch (caught) {
    notice.value = caught instanceof Error ? caught.message : 'Не удалось создать объекты';
  }
}
</script>

<template>
  <v-card variant="outlined">
    <v-card-title class="text-body-2">Объекты по подложке</v-card-title>
    <v-card-text class="text-body-2">
      <v-alert v-if="props.disabled" type="info" variant="tonal" density="compact">
        Редактирование карты недоступно.
      </v-alert>
      <template v-else>
        <v-alert v-if="props.recognition.state.imageState === 'idle'" type="info" variant="tonal" density="compact">
          У выбранного варианта нет подложки.
        </v-alert>
        <div v-else-if="props.recognition.state.imageState === 'loading'" class="text-medium-emphasis">
          Загрузка подложки…
        </div>
        <v-alert
          v-else-if="props.recognition.state.imageState === 'error'"
          type="error"
          variant="tonal"
          density="compact"
        >
          {{ props.recognition.state.error }}
        </v-alert>
        <template v-else>
          <div class="text-caption text-medium-emphasis mb-1">Образцы цвета</div>
          <div class="d-flex flex-wrap align-center ga-2 mb-2">
            <div
              v-for="(color, index) in props.recognition.samples"
              :key="color"
              class="recognition-swatch"
              :style="{ background: color }"
              :title="color"
            >
              <button
                type="button"
                class="recognition-swatch__remove"
                aria-label="Убрать образец"
                @click="props.recognition.removeSample(index)"
              >
                ×
              </button>
            </div>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-eyedropper" @click="props.recognition.startPick()">
              Взять цвет
            </v-btn>
          </div>
          <p class="text-caption text-medium-emphasis mt-1">
            {{
              props.recognition.state.isPicking
                ? 'Кликните по подложке — цвет станет образцом'
                : 'Можно добавить несколько оттенков; повторный клик по пятну исключает его'
            }}
          </p>

          <v-slider
            :model-value="props.recognition.settings.tolerance"
            @update:model-value="setTolerance"
            :min="MAP_RECOGNITION.limits.tolerance.min"
            :max="MAP_RECOGNITION.limits.tolerance.max"
            :step="1"
            color="primary"
            label="Похожесть цвета"
            thumb-label
            density="compact"
            hide-details
          />
          <v-switch
            :model-value="props.recognition.settings.ignoreLuminance"
            @update:model-value="setIgnoreLuminance"
            label="Не учитывать яркость (тени)"
            color="primary"
            density="compact"
            hide-details
          />
          <v-switch
            :model-value="props.recognition.settings.onlyVisible"
            @update:model-value="setOnlyVisible"
            label="Только видимая область"
            color="primary"
            density="compact"
            hide-details
          />

          <div class="d-flex ga-2 mt-2">
            <v-text-field
              :model-value="props.recognition.settings.minArea"
              @update:model-value="setMinArea"
              type="number"
              :min="MAP_RECOGNITION.limits.minArea.min"
              :max="MAP_RECOGNITION.limits.minArea.max"
              label="Мин. площадь"
              variant="outlined"
              density="compact"
              hide-details
            />
            <v-text-field
              :model-value="props.recognition.settings.maxArea"
              @update:model-value="setMaxArea"
              type="number"
              min="0"
              label="Макс. площадь (0 — нет)"
              variant="outlined"
              density="compact"
              hide-details
            />
          </div>
          <div class="d-flex ga-2 mt-2">
            <v-text-field
              :model-value="props.recognition.settings.gap"
              @update:model-value="setGap"
              type="number"
              :min="MAP_RECOGNITION.limits.gap.min"
              :max="MAP_RECOGNITION.limits.gap.max"
              label="Разделять перемычки, px"
              variant="outlined"
              density="compact"
              hide-details
            />
            <v-text-field
              :model-value="props.recognition.settings.simplify"
              @update:model-value="setSimplify"
              type="number"
              :min="MAP_RECOGNITION.limits.simplify.min"
              :max="MAP_RECOGNITION.limits.simplify.max"
              step="0.5"
              label="Упрощение, px"
              variant="outlined"
              density="compact"
              hide-details
            />
          </div>
          <p class="text-caption text-medium-emphasis mt-1">
            Перемычки 1–2 px разделяют слипшиеся дома; контур при этом возвращается к исходному размеру.
          </p>
          <v-switch
            :model-value="props.recognition.settings.smoothArcs"
            @update:model-value="setSmoothArcs"
            label="Сглаживать дуги"
            color="primary"
            density="compact"
            hide-details
          />
          <v-slider
            :model-value="props.recognition.settings.maxVertices"
            @update:model-value="setMaxVertices"
            :min="MAP_RECOGNITION.limits.maxVertices.min"
            :max="MAP_RECOGNITION.limits.maxVertices.max"
            :step="1"
            color="primary"
            label="Макс. углов"
            thumb-label
            density="compact"
            hide-details
            class="mt-2"
          />

          <v-btn
            block
            class="mt-3"
            color="primary"
            variant="tonal"
            :loading="props.recognition.state.isBusy"
            :disabled="props.recognition.samples.length === 0"
            prepend-icon="mdi-magnify"
            @click="props.recognition.detect()"
          >
            Найти объекты
          </v-btn>
          <v-alert v-if="props.recognition.state.error" type="error" variant="tonal" density="compact" class="mt-2">
            {{ props.recognition.state.error }}
          </v-alert>

          <template v-if="props.recognition.state.hasResult">
            <v-divider class="my-3" />
            <div>Найдено пятен: {{ props.recognition.state.stats.found }}</div>
            <div>Принято контуров: {{ props.recognition.state.stats.objects }}</div>
            <div v-if="props.recognition.state.stats.skippedSmall > 0">
              Отброшено мелких: {{ props.recognition.state.stats.skippedSmall }}
            </div>
            <div v-if="props.recognition.state.stats.skippedLarge > 0">
              Отброшено крупных: {{ props.recognition.state.stats.skippedLarge }}
            </div>
            <div v-if="props.recognition.state.stats.skippedThin > 0">
              Отброшено тонких: {{ props.recognition.state.stats.skippedThin }}
            </div>
            <div>
              Углов: среднее {{ props.recognition.state.stats.meanVertices }}, максимум
              {{ props.recognition.state.stats.maxVertices }}
            </div>
            <div class="mt-1">К созданию: {{ props.recognition.state.stats.included }}</div>
            <div v-if="props.recognition.state.stats.included < props.recognition.state.stats.objects">
              Исключено: {{ props.recognition.state.stats.objects - props.recognition.state.stats.included }}
            </div>
            <p class="text-caption text-medium-emphasis">
              Клик по контуру или по самому пятну исключает его и возвращает обратно.
            </p>
            <div class="d-flex ga-2 mt-1">
              <v-btn size="small" variant="tonal" @click="props.recognition.setAllExcluded(true)">
                Исключить все
              </v-btn>
              <v-btn size="small" variant="tonal" @click="props.recognition.setAllExcluded(false)">
                Вернуть все
              </v-btn>
            </div>

            <v-divider class="my-3" />
            <v-select
              v-model="preset"
              :items="presetOptions"
              item-title="title"
              item-value="value"
              label="Шаблон объекта"
              variant="outlined"
              density="compact"
              hide-details
              @update:model-value="applyPreset"
            />
            <v-select
              v-model="form.layer"
              :items="layerOptions"
              item-title="title"
              item-value="value"
              label="Слой"
              variant="outlined"
              density="compact"
              hide-details
              class="mt-2"
            />
            <div class="d-flex align-center ga-2 mt-2">
              <input v-model="form.color" type="color" class="map-color" aria-label="Цвет объектов" />
              <v-text-field v-model="form.color" label="Цвет" variant="outlined" density="compact" hide-details />
            </div>
            <v-switch
              v-model="form.strokeEnabled"
              label="Граница объектов"
              color="primary"
              density="compact"
              hide-details
              class="mt-1"
            />
            <template v-if="form.strokeEnabled">
              <div class="d-flex align-center ga-2">
                <input v-model="form.stroke" type="color" class="map-color" aria-label="Цвет границы" />
                <v-text-field
                  v-model="form.stroke"
                  label="Цвет границы"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </div>
              <v-slider
                v-model.number="form.strokeWidth"
                :min="MAP_AREA_STROKE.minWidth"
                :max="MAP_AREA_STROKE.maxWidth"
                :step="1"
                color="primary"
                label="Толщина границы, px"
                thumb-label
                density="compact"
                hide-details
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
              density="compact"
              hide-details
              class="mt-2"
            />
            <v-text-field
              v-model="form.label"
              label="Подпись объектов"
              variant="outlined"
              density="compact"
              hide-details
              class="mt-2"
            />
            <v-text-field
              v-model="form.groupLabel"
              label="Группа"
              variant="outlined"
              density="compact"
              hide-details
              class="mt-2"
            />
            <v-switch
              v-model="form.fillOpacityEnabled"
              label="Своя плотность заливки"
              color="primary"
              density="compact"
              hide-details
              class="mt-2"
            />
            <v-slider
              v-if="form.fillOpacityEnabled"
              v-model.number="form.fillOpacity"
              :min="MAP_FILL_OPACITY.min"
              :max="MAP_FILL_OPACITY.max"
              :step="1"
              color="primary"
              label="Плотность, %"
              thumb-label
              density="compact"
              hide-details
            />
            <p v-else class="text-caption text-medium-emphasis">
              Наследует плотность карты: {{ props.mapFillOpacity }} %.
            </p>

            <v-btn
              block
              class="mt-3"
              color="primary"
              :disabled="props.recognition.state.stats.included === 0"
              prepend-icon="mdi-vector-square"
              @click="create"
            >
              Создать {{ props.recognition.state.stats.included }} объектов
            </v-btn>
            <v-alert v-if="notice" type="success" variant="tonal" density="compact" class="mt-2">
              {{ notice }}
            </v-alert>
          </template>
        </template>
      </template>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.recognition-swatch {
  position: relative;
  width: 30px;
  height: 30px;
  border: 1px solid rgba(0, 0, 0, 0.25);
  border-radius: 4px;
}

.recognition-swatch__remove {
  position: absolute;
  top: -7px;
  right: -7px;
  width: 16px;
  height: 16px;
  border: 0;
  border-radius: 50%;
  background: #37474f;
  color: #fff;
  font-size: 11px;
  line-height: 16px;
  cursor: pointer;
}
</style>
