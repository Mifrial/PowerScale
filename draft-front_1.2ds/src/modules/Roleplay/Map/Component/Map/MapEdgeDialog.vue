<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import type { MapEdge } from '@/modules/Roleplay/Map/Dto/MapEdge';
import type { MapEdgeEdit } from '@/modules/Roleplay/Map/Dto/MapEdgeEdit';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import type { MapWarning } from '@/modules/Roleplay/Map/Dto/MapWarning';
import { MAP_EDGE_DEFAULT_TERRAIN } from '@/modules/Roleplay/Map/Constant/Map/MAP_EDGE_DEFAULT_TERRAIN';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  edge: MapEdge | null;
  fromNodeId: number | null;
  toNodeId: number | null;
  terrains: MapTerrain[];
  nodes: { id: number; name: string }[];
  warnings: MapWarning[];
  submitting: boolean;
  scaleRequired?: boolean;
}>();

const emit = defineEmits<{
  submit: [edit: MapEdgeEdit];
  remove: [edgeId: number];
}>();

const form = reactive({
  fromNodeId: null as number | null,
  toNodeId: null as number | null,
  terrainCode: MAP_EDGE_DEFAULT_TERRAIN,
  distanceMeters: null as number | null,
  isDirected: false,
  label: '',
});

const terrainOptions = computed(() => props.terrains.map((terrain) => ({ title: terrain.label, value: terrain.code })));

const distanceModel = computed({
  get: () => form.distanceMeters,
  set: (value: number | string | null) => {
    form.distanceMeters = value === null || value === '' ? null : Number(value);
  },
});

const canSubmit = computed(
  () =>
    form.fromNodeId !== null &&
    form.toNodeId !== null &&
    form.fromNodeId !== form.toNodeId &&
    (!props.scaleRequired || form.distanceMeters !== null),
);

watch(open, (value) => {
  if (!value) return;
  form.fromNodeId = props.edge?.fromNodeId ?? props.fromNodeId;
  form.toNodeId = props.edge?.toNodeId ?? props.toNodeId;
  form.terrainCode = props.edge?.terrainCode ?? MAP_EDGE_DEFAULT_TERRAIN;
  form.distanceMeters = props.edge?.distanceMeters ?? null;
  form.isDirected = props.edge?.isDirected ?? false;
  form.label = props.edge?.label ?? '';
});

function submit(): void {
  if (form.fromNodeId === null || form.toNodeId === null) return;
  emit('submit', {
    fromNodeId: form.fromNodeId,
    toNodeId: form.toNodeId,
    terrainCode: form.terrainCode,
    distanceMeters: form.distanceMeters,
    isDirected: form.isDirected,
    label: form.label.trim(),
  });
}

function remove(): void {
  if (props.edge) emit('remove', props.edge.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="520" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">
        {{ props.edge ? 'Ребро маршрута' : 'Новое ребро' }}
      </v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.fromNodeId"
              :items="props.nodes"
              item-title="name"
              item-value="id"
              label="Откуда"
              variant="outlined"
              density="comfortable"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.toNodeId"
              :items="props.nodes"
              item-title="name"
              item-value="id"
              label="Куда"
              variant="outlined"
              density="comfortable"
            />
          </v-col>
        </v-row>
        <v-select
          v-model="form.terrainCode"
          :items="terrainOptions"
          label="Террейн"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
        <v-text-field
          v-model="distanceModel"
          type="number"
          min="0"
          label="Расстояние, м (пусто — по масштабу)"
          variant="outlined"
          density="comfortable"
          class="mt-3"
          :error="props.scaleRequired === true && form.distanceMeters === null"
          :error-messages="
            props.scaleRequired === true && form.distanceMeters === null
              ? 'У карты нет масштаба — укажите расстояние в метрах'
              : ''
          "
        />
        <v-text-field v-model="form.label" label="Подпись" variant="outlined" density="comfortable" class="mt-3" />
        <v-switch v-model="form.isDirected" label="Только в одну сторону" color="primary" density="compact" />
        <p class="text-caption text-medium-emphasis mt-2">
          Путь ребра правится на карте: двойной клик по ребру добавляет точку, вершины тянутся.
        </p>
        <v-alert v-if="props.warnings.length > 0" type="warning" variant="tonal" density="compact" class="mt-3">
          Ребро пересекает блокер ({{ props.warnings.length }}). Это предупреждение: мост или арка допустимы.
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="props.edge"
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
        <v-btn color="primary" variant="tonal" :loading="props.submitting" :disabled="!canSubmit" @click="submit">
          Применить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
