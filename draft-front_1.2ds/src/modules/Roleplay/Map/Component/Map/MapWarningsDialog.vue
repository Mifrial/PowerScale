<script setup lang="ts">
import { computed } from 'vue';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import type { MapWarning } from '@/modules/Roleplay/Map/Dto/MapWarning';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  warnings: MapWarning[];
  map: MapDetail;
  terrains: MapTerrain[];
}>();

const items = computed(() =>
  props.warnings.map((warning) => ({
    key: `${warning.edgeId}-${warning.blockerKind}-${warning.blockerId}`,
    edge: edgeLabel(warning.edgeId),
    blocker: blockerLabel(warning),
  })),
);

function nodeName(nodeId: number): string {
  const node = props.map.nodes.find((candidate) => candidate.id === nodeId);
  if (!node) return `Узел #${nodeId}`;

  return node.isPoi && node.name ? node.name : `Узел #${nodeId}`;
}

function edgeLabel(edgeId: number): string {
  const edge = props.map.edges.find((candidate) => candidate.id === edgeId);
  if (!edge) return `Ребро #${edgeId}`;

  return edge.label || `${nodeName(edge.fromNodeId)} → ${nodeName(edge.toNodeId)}`;
}

function blockerLabel(warning: MapWarning): string {
  if (warning.blockerKind === 'area') {
    const area = props.map.areas.find((candidate) => candidate.id === warning.blockerId);

    return area ? `область «${area.label || `#${area.id}`}»` : `область #${warning.blockerId}`;
  }

  const line = props.map.lines.find((candidate) => candidate.id === warning.blockerId);
  if (!line) return `лента #${warning.blockerId}`;
  const terrain = props.terrains.find((candidate) => candidate.code === line.terrainCode);

  return `лента «${line.label || terrain?.label || `#${line.id}`}»`;
}
</script>

<template>
  <v-dialog v-model="open" max-width="560">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">Карта опубликована с предупреждениями</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">
          Рёбра пересекают непроходимые объекты. Это не ошибка: мост, арка или брод допустимы, маршрут считает только
          террейн ребра.
        </p>
        <v-list density="compact">
          <v-list-item v-for="item in items" :key="item.key">
            <v-list-item-title>{{ item.edge }}</v-list-item-title>
            <v-list-item-subtitle>пересекает {{ item.blocker }}</v-list-item-subtitle>
          </v-list-item>
        </v-list>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="tonal" @click="open = false">Понятно</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
