<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapLineEdit } from '@/modules/Roleplay/Map/Dto/MapLineEdit';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import { MAP_LINE_DEFAULT_TERRAIN } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DEFAULT_TERRAIN';
import { MAP_LINE_DEFAULT_WIDTH } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DEFAULT_WIDTH';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  line: MapLine | null;
  terrains: MapTerrain[];
  submitting: boolean;
  defaults?: { terrainCode: string; width: number } | null;
}>();

const widthRange = computed(() => {
  if (!props.line || props.line.widths.length === 0) return null;
  const min = Math.min(...props.line.widths);
  const max = Math.max(...props.line.widths);

  return min === max ? `${min} px` : `${min}–${max} px`;
});

const emit = defineEmits<{
  submit: [edit: MapLineEdit];
  remove: [lineId: number];
}>();

const form = reactive({
  terrainCode: MAP_LINE_DEFAULT_TERRAIN,
  width: MAP_LINE_DEFAULT_WIDTH,
  label: '',
});

const terrainOptions = computed(() => props.terrains.map((terrain) => ({ title: terrain.label, value: terrain.code })));

watch(open, (value) => {
  if (!value) return;
  form.terrainCode = props.line?.terrainCode ?? props.defaults?.terrainCode ?? MAP_LINE_DEFAULT_TERRAIN;
  form.width = props.line ? Math.max(1, ...props.line.widths) : (props.defaults?.width ?? MAP_LINE_DEFAULT_WIDTH);
  form.label = props.line?.label ?? '';
});

function submit(): void {
  emit('submit', {
    terrainCode: form.terrainCode,
    width: Math.max(1, Math.round(form.width)),
    label: form.label.trim(),
  });
}

function remove(): void {
  if (props.line) emit('remove', props.line.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="460" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">
        {{ props.line ? 'Лента карты' : 'Новая лента' }}
      </v-card-title>
      <v-card-text>
        <v-select
          v-model="form.terrainCode"
          :items="terrainOptions"
          label="Террейн"
          variant="outlined"
          density="comfortable"
        />
        <v-text-field
          v-if="!props.line"
          v-model.number="form.width"
          type="number"
          min="1"
          label="Ширина, px"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
        <div v-else class="text-body-2 mt-3">
          Ширина по точкам: {{ widthRange }}
          <div class="text-caption text-medium-emphasis">Меняется оранжевыми ручками на карте.</div>
        </div>
        <v-text-field v-model="form.label" label="Подпись" variant="outlined" density="comfortable" class="mt-3" />
        <p class="text-caption text-medium-emphasis mt-3">
          Лента живёт в текущем варианте: река, ручей или дорога вне графа. Кривизна задаётся ручками Безье на карте.
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
