<script setup lang="ts">
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';

const props = defineProps<{
  from: MapPoint | null;
  to: MapPoint | null;
  route: MapRoute | null;
  error: string | null;
  loading: boolean;
}>();

const emit = defineEmits<{
  build: [];
  clear: [];
}>();

const pointLabel = (point: MapPoint | null): string => (point === null ? 'не выбрано' : `${point[0]}, ${point[1]}`);

const distance = (route: MapRoute): string =>
  route.distanceMeters === null ? `${Math.round(route.distancePixels)} px` : `${route.distanceMeters} м`;

const cost = (route: MapRoute): string =>
  route.costMeters === null ? `${Math.round(route.costPixels)} px` : `${route.costMeters} м`;
</script>

<template>
  <div class="map-route-panel">
    <p class="text-body-2 mb-3">
      Кликните точку начала и точку конца на карте. Путь сам обойдёт воду, стены и дома и предпочтёт дороги.
    </p>
    <div class="text-body-2">Откуда: {{ pointLabel(props.from) }}</div>
    <div class="text-body-2 mt-1">Куда: {{ pointLabel(props.to) }}</div>
    <div class="d-flex align-center mt-3">
      <v-btn
        color="primary"
        variant="tonal"
        :loading="props.loading"
        :disabled="props.from === null || props.to === null"
        @click="emit('build')"
      >
        Построить
      </v-btn>
      <v-btn variant="text" class="ml-2" @click="emit('clear')">Сбросить</v-btn>
    </div>
    <v-alert v-if="props.error" type="error" variant="tonal" density="compact" class="mt-3">
      {{ props.error }}
    </v-alert>
    <v-alert v-if="props.route" type="info" variant="tonal" density="compact" class="mt-3">
      <div>Расстояние: {{ distance(props.route) }}</div>
      <div>Стоимость пути: {{ cost(props.route) }}</div>
      <div class="text-caption">Точек пути: {{ props.route.points.length }}</div>
    </v-alert>
  </div>
</template>
