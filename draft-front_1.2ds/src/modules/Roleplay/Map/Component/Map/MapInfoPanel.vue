<script setup lang="ts">
import { computed } from 'vue';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapHit } from '@/modules/Roleplay/Map/Dto/MapHit';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';

const props = defineProps<{
  map: MapDetail;
  selected: MapHit | null;
  terrains: MapTerrain[];
  activeIndex: number | null;
}>();

const emit = defineEmits<{
  edit: [hit: MapHit];
  remove: [hit: MapHit];
  branch: [hit: MapHit];
  smooth: [];
  corner: [];
  width: [lineId: number, index: number, width: number];
  uniformWidth: [lineId: number, width: number];
  close: [];
}>();

const node = computed(() =>
  props.selected?.kind === 'node' ? props.map.nodes.find((item) => item.id === props.selected?.id) : null,
);

const area = computed(() =>
  props.selected?.kind === 'area' ? props.map.areas.find((item) => item.id === props.selected?.id) : null,
);

const line = computed(() =>
  props.selected?.kind === 'line' ? props.map.lines.find((item) => item.id === props.selected?.id) : null,
);

const edge = computed(() =>
  props.selected?.kind === 'edge' ? props.map.edges.find((item) => item.id === props.selected?.id) : null,
);

const linkedNode = computed(() =>
  area.value?.nodeId === null || area.value?.nodeId === undefined
    ? null
    : (props.map.nodes.find((item) => item.id === area.value?.nodeId) ?? null),
);

const lineWidthRange = computed(() => {
  if (!line.value || line.value.widths.length === 0) return '—';
  const min = Math.min(...line.value.widths);
  const max = Math.max(...line.value.widths);

  return min === max ? `${min} px` : `${min}–${max} px`;
});

const uniformWidth = computed(() =>
  line.value && line.value.widths.length > 0 ? Math.max(...line.value.widths) : null,
);

const vertexWidth = computed(() =>
  line.value && props.activeIndex !== null ? (line.value.widths[props.activeIndex] ?? null) : null,
);

function setUniformWidth(value: number | string): void {
  if (!line.value) return;
  const width = Math.round(Number(value));
  if (!Number.isFinite(width) || width < 1) return;
  emit('uniformWidth', line.value.id, width);
}

function setVertexWidth(value: number | string): void {
  if (!line.value || props.activeIndex === null) return;
  const width = Math.round(Number(value));
  if (!Number.isFinite(width) || width < 1) return;
  emit('width', line.value.id, props.activeIndex, width);
}

const title = computed(
  () => node.value?.name || area.value?.label || line.value?.label || edge.value?.label || 'Объект карты',
);

const terrainLabel = (code: string | null): string =>
  props.terrains.find((terrain) => terrain.code === code)?.label ?? '—';

const nodeName = (nodeId: number): string => {
  const item = props.map.nodes.find((candidate) => candidate.id === nodeId);
  if (!item) return `Узел #${nodeId}`;

  return item.isPoi && item.name ? item.name : `Узел #${item.id}`;
};

function edit(): void {
  if (props.selected) emit('edit', props.selected);
}

function remove(): void {
  if (props.selected) emit('remove', props.selected);
}
</script>

<template>
  <v-card variant="outlined" class="map-info">
    <v-card-title class="d-flex align-center text-body-1">
      <span>{{ title }}</span>
      <v-spacer />
      <v-btn icon="mdi-close" size="small" variant="text" @click="emit('close')" />
    </v-card-title>
    <v-card-text class="text-body-2">
      <template v-if="node">
        <div class="mb-2">{{ node.shortInfo || 'Без короткой информации' }}</div>
        <template v-if="props.map.canEdit">
          <v-divider class="my-2" />
          <div class="text-caption text-medium-emphasis">Раскрываемая информация</div>
          <div>{{ node.publicInfo || '—' }}</div>
          <div class="text-caption text-medium-emphasis mt-2">Информация ведущего</div>
          <div>{{ node.gmInfo || '—' }}</div>
        </template>
      </template>
      <template v-else-if="area">
        <div>Слой: {{ area.layer === 'terrain' ? 'ландшафт' : 'объект' }}</div>
        <div v-if="area.layer === 'terrain'">Террейн: {{ terrainLabel(area.terrainCode) }}</div>
        <div v-else>Вид: {{ area.kind }}{{ area.blocksMovement ? ', непроходим' : '' }}</div>
        <template v-if="linkedNode">
          <v-divider class="my-2" />
          <div class="text-caption text-medium-emphasis">Точка: {{ linkedNode.name }}</div>
          <div>{{ linkedNode.shortInfo || 'Без короткой информации' }}</div>
          <template v-if="props.map.canEdit">
            <div class="text-caption text-medium-emphasis mt-2">Раскрываемая информация</div>
            <div>{{ linkedNode.publicInfo || '—' }}</div>
            <div class="text-caption text-medium-emphasis mt-2">Информация ведущего</div>
            <div>{{ linkedNode.gmInfo || '—' }}</div>
          </template>
        </template>
      </template>
      <template v-else-if="line">
        <div>Террейн: {{ terrainLabel(line.terrainCode) }}</div>
        <div>Точек: {{ line.points.length }}, ширина: {{ lineWidthRange }}</div>
        <v-row v-if="props.map.canEdit" dense class="mt-2">
          <v-col cols="6">
            <v-text-field
              :model-value="uniformWidth"
              type="number"
              min="1"
              label="Ширина всюду, px"
              variant="outlined"
              density="compact"
              hide-details
              @change="setUniformWidth"
            />
          </v-col>
          <v-col v-if="props.activeIndex !== null" cols="6">
            <v-text-field
              :model-value="vertexWidth"
              type="number"
              min="1"
              :label="`Точка ${props.activeIndex + 1}, px`"
              variant="outlined"
              density="compact"
              hide-details
              @change="setVertexWidth"
            />
          </v-col>
        </v-row>
        <div class="text-caption text-medium-emphasis mt-2">
          <template v-if="props.map.canEdit">
            Кликните вершину на карте, чтобы увидеть ручки Безье (фиолетовые) и ручку ширины (оранжевый ромб).
            <span v-if="props.activeIndex !== null"> Сейчас выбрана точка {{ props.activeIndex + 1 }}.</span>
          </template>
          <template v-else>Ширина меняется по точкам вдоль осевой линии.</template>
        </div>
      </template>
      <template v-else-if="edge">
        <div>{{ nodeName(edge.fromNodeId) }} → {{ nodeName(edge.toNodeId) }}</div>
        <div>Террейн: {{ terrainLabel(edge.terrainCode) }}</div>
        <div>Расстояние: {{ edge.distanceMeters === null ? 'по масштабу' : `${edge.distanceMeters} м` }}</div>
        <div v-if="edge.isDirected" class="text-caption">Только в одну сторону</div>
      </template>
    </v-card-text>
    <v-card-actions v-if="props.map.canEdit" class="flex-wrap">
      <template v-if="line">
        <v-btn size="small" variant="text" prepend-icon="mdi-source-branch" @click="props.selected && emit('branch', props.selected)">
          Ответвить
        </v-btn>
        <v-btn size="small" variant="text" prepend-icon="mdi-auto-fix" @click="emit('smooth')">Сгладить</v-btn>
        <v-btn size="small" variant="text" prepend-icon="mdi-vector-corner" @click="emit('corner')">Угол</v-btn>
      </template>
      <v-btn size="small" variant="text" prepend-icon="mdi-pencil-outline" @click="edit">Изменить</v-btn>
      <v-spacer />
      <v-btn size="small" color="error" variant="text" prepend-icon="mdi-delete-outline" @click="remove">Удалить</v-btn>
    </v-card-actions>
  </v-card>
</template>

<style scoped>
.map-info {
  border-radius: 8px;
}
</style>
