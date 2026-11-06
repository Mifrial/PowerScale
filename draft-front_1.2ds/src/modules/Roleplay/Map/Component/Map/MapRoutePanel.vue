<script setup lang="ts">
import { ref, watch } from 'vue';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';

const props = defineProps<{
  nodes: { id: number; name: string }[];
  route: MapRoute | null;
  error: string | null;
  loading: boolean;
}>();

const emit = defineEmits<{
  build: [fromNodeId: number, toNodeId: number];
  clear: [];
}>();

const fromNodeId = ref<number | null>(null);
const toNodeId = ref<number | null>(null);

watch(
  () => props.nodes,
  (nodes) => {
    if (fromNodeId.value !== null && !nodes.some((node) => node.id === fromNodeId.value)) {
      fromNodeId.value = null;
    }
    if (toNodeId.value !== null && !nodes.some((node) => node.id === toNodeId.value)) {
      toNodeId.value = null;
    }
  },
);

function build(): void {
  if (fromNodeId.value === null || toNodeId.value === null) return;
  emit('build', fromNodeId.value, toNodeId.value);
}

function clear(): void {
  fromNodeId.value = null;
  toNodeId.value = null;
  emit('clear');
}
</script>

<template>
  <div class="map-route-panel">
    <v-select
      v-model="fromNodeId"
      :items="props.nodes"
      item-title="name"
      item-value="id"
      label="Откуда"
      variant="outlined"
      density="comfortable"
      hide-details
    />
    <v-select
      v-model="toNodeId"
      :items="props.nodes"
      item-title="name"
      item-value="id"
      label="Куда"
      variant="outlined"
      density="comfortable"
      hide-details
      class="mt-2"
    />
    <p class="text-caption text-medium-emphasis mt-2">
      Маршрут — по любым узлам, включая невидимые waypoint-ы. Для произвольных точек используйте линейку в редакторе.
    </p>
    <div class="d-flex align-center mt-3">
      <v-btn
        color="primary"
        variant="tonal"
        :loading="props.loading"
        :disabled="fromNodeId === null || toNodeId === null"
        @click="build"
      >
        Построить
      </v-btn>
      <v-btn variant="text" class="ml-2" @click="clear">Сбросить</v-btn>
    </div>
    <v-alert v-if="props.error" type="error" variant="tonal" density="compact" class="mt-3">
      {{ props.error }}
    </v-alert>
    <v-alert v-if="props.route" type="info" variant="tonal" density="compact" class="mt-3">
      <div>Расстояние: {{ props.route.distanceMeters }} м</div>
      <div>Стоимость пути: {{ props.route.costMeters }} м</div>
      <div class="text-caption">Узлов: {{ props.route.nodeIds.length }}, рёбер: {{ props.route.edgeIds.length }}</div>
    </v-alert>
  </div>
</template>
