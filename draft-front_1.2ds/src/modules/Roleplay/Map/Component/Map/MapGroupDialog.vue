<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaGroup } from '@/modules/Roleplay/Map/Dto/MapAreaGroup';
import type { MapGroupEdit } from '@/modules/Roleplay/Map/Dto/MapGroupEdit';
import { decorFromSet, MAP_DECOR_OPTIONS, isMapDecorSet } from '@/modules/Roleplay/Map/Constant/Map/MAP_DECOR';
import { MAP_FILL_OPACITY } from '@/modules/Roleplay/Map/Constant/Map/MAP_FILL_OPACITY';
import { MAP_AREA_STROKE } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_STROKE';
import { speedFromInput, speedToInput } from '@/modules/Roleplay/Map/Constant/Map/MAP_SPEED';
import { clampInt } from '@/modules/Roleplay/Map/Utils/clampInt';
import { autoStrokeColor } from '@/modules/Roleplay/Map/Utils/color';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  group: MapAreaGroup | null;
  members: MapArea[];
  mapFillOpacity: number;
  submitting: boolean;
}>();

const emit = defineEmits<{
  submit: [edit: MapGroupEdit];
}>();

const form = reactive({
  label: '',
  color: '#8d8d8d',
  borderTouched: false,
  borderEnabled: true,
  stroke: autoStrokeColor('#8d8d8d', MAP_AREA_STROKE.autoDarken),
  strokeWidth: MAP_AREA_STROKE.defaultWidth as number,
  speed: speedToInput(5000),
  memberLabel: '',
  decorSet: null as string | null,
  fillOpacityEnabled: false,
  fillOpacity: 25,
});

interface GroupForm {
  label: string;
  color: string;
  borderTouched: boolean;
  borderEnabled: boolean;
  stroke: string;
  strokeWidth: number;
  speed: number;
  memberLabel: string;
  decorSet: string | null;
  fillOpacityEnabled: boolean;
  fillOpacity: number;
}

let initial: GroupForm = { ...form };

function snapshot(): GroupForm {
  return { ...form };
}

function reset(): void {
  const group = props.group;
  const sample = props.members[0] ?? null;
  form.label = group?.label ?? '';
  form.color = sample?.color ?? '#8d8d8d';
  form.borderTouched = false;
  form.borderEnabled = (sample?.stroke ?? '') !== '';
  form.stroke = sample?.stroke || autoStrokeColor(form.color, MAP_AREA_STROKE.autoDarken);
  form.strokeWidth = sample?.strokeWidth ?? MAP_AREA_STROKE.defaultWidth;
  form.speed = speedToInput(sample?.speed ?? 5000);
  form.memberLabel = sample?.label ?? '';
  form.decorSet = isMapDecorSet(sample?.decor.set) ? sample.decor.set : null;
  form.fillOpacityEnabled = sample?.decor.fillOpacity !== undefined;
  form.fillOpacity = sample?.decor.fillOpacity ?? props.mapFillOpacity;
  initial = snapshot();
}

watch(
  () => [props.group?.id, props.members.length, open.value],
  () => {
    if (open.value) reset();
  },
  { immediate: true },
);

const decorOptions = computed(() => [...MAP_DECOR_OPTIONS, { value: null, title: 'Без декора' }]);
const memberCount = computed(() => props.members.length);

function submit(): void {
  const fields: MapGroupEdit['fields'] = {};
  if (form.color !== initial.color) fields.color = form.color.trim();
  if (form.borderTouched) {
    fields.stroke = form.borderEnabled
      ? /^#[0-9a-fA-F]{6}$/.test(form.stroke.trim())
        ? form.stroke.trim().toLowerCase()
        : autoStrokeColor(form.color.trim() || '#8d8d8d', MAP_AREA_STROKE.autoDarken)
      : '';
    fields.strokeWidth = clampInt(
      form.strokeWidth,
      MAP_AREA_STROKE.minWidth,
      MAP_AREA_STROKE.maxWidth,
      MAP_AREA_STROKE.defaultWidth,
    );
  }
  if (form.speed !== initial.speed) fields.speed = speedFromInput(Number(form.speed));
  if (form.memberLabel !== initial.memberLabel) fields.label = form.memberLabel.trim();

  const decor: Partial<MapAreaDecor> = {};
  let decorChanged = false;
  if (form.decorSet !== initial.decorSet) {
    if (form.decorSet === null) {
      fields.decor = null;
      decorChanged = false;
    } else if (isMapDecorSet(form.decorSet)) {
      Object.assign(decor, decorFromSet(form.decorSet));
      decorChanged = true;
    }
  }
  if (form.fillOpacityEnabled) {
    decor.fillOpacity = clampInt(form.fillOpacity, MAP_FILL_OPACITY.min, MAP_FILL_OPACITY.max, props.mapFillOpacity);
    if (!initial.fillOpacityEnabled || form.fillOpacity !== initial.fillOpacity) decorChanged = true;
  } else if (initial.fillOpacityEnabled) {
    // Наследовать плотность карты: убираем поле из декора.
    decor.fillOpacity = undefined;
    decorChanged = true;
  }
  if (decorChanged && fields.decor === undefined) fields.decor = decor;

  const groupLabel = form.label.trim() === initial.label ? null : form.label.trim();
  emit('submit', { groupLabel, fields });
}
</script>

<template>
  <v-dialog v-model="open" max-width="560" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">Группа объектов</v-card-title>
      <v-card-text>
        <p class="text-caption text-medium-emphasis mb-3">
          Объектов в группе в этом варианте: {{ memberCount }}. Меняются только тронутые поля.
        </p>
        <v-text-field v-model="form.label" label="Название группы" variant="outlined" density="comfortable" />
        <div class="d-flex align-center ga-2 mt-3">
          <input v-model="form.color" type="color" class="map-color" aria-label="Цвет объектов" />
          <v-text-field v-model="form.color" label="Цвет" variant="outlined" density="comfortable" hide-details />
        </div>
        <v-switch
          v-model="form.borderTouched"
          label="Изменить границу"
          color="primary"
          density="compact"
          hide-details
          class="mt-2"
        />
        <template v-if="form.borderTouched">
          <v-switch v-model="form.borderEnabled" label="Граница есть" color="primary" density="compact" hide-details />
          <template v-if="form.borderEnabled">
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
        <v-text-field
          v-model="form.memberLabel"
          label="Подпись объектов"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
        <v-select
          v-model="form.decorSet"
          :items="decorOptions"
          item-title="title"
          item-value="value"
          label="Декор"
          variant="outlined"
          density="comfortable"
          class="mt-3"
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
        <p v-else class="text-caption text-medium-emphasis mt-1">
          Плотность наследуется от карты: {{ props.mapFillOpacity }} %.
        </p>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="props.submitting" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" variant="tonal" :loading="props.submitting" @click="submit"> Применить </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
