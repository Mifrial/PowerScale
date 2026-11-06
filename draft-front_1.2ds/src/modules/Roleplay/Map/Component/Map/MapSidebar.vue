<script setup lang="ts">
import { computed, ref } from 'vue';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapHit } from '@/modules/Roleplay/Map/Dto/MapHit';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapToolMode } from '@/modules/Roleplay/Map/Enum/MapToolMode';

const props = defineProps<{
  map: MapDetail;
  variant: MapVariant | null;
  selected: MapHit | null;
}>();

const emit = defineEmits<{
  select: [hit: MapHit];
  edit: [hit: MapHit];
  create: [tool: MapToolMode];
}>();

const namedOnly = ref(false);
const poiOnly = ref(false);

const variantNodes = computed(() => {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.nodes.filter((node) => {
    if (!node.placements.some((placement) => placement.variantId === variant.id && placement.isPresent)) return false;
    if (poiOnly.value && !node.isPoi) return false;
    if (namedOnly.value && node.name.trim() === '') return false;

    return true;
  });
});

function variantAreas(layer: 'terrain' | 'object') {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.areas.filter((area) => {
    if (area.layer !== layer) return false;
    if (!area.shapes.some((shape) => shape.variantId === variant.id && shape.isPresent)) return false;
    if (namedOnly.value && area.label.trim() === '') return false;

    return true;
  });
}

const objectAreas = computed(() => variantAreas('object'));
const terrainAreas = computed(() => variantAreas('terrain'));

const variantLines = computed(() => {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.lines.filter((line) => {
    if (line.variantId !== variant.id || !line.isPresent) return false;
    if (namedOnly.value && line.label.trim() === '') return false;

    return true;
  });
});

const variantEdges = computed(() => {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.edges.filter((edge) => edge.variantId === variant.id);
});

const nodeName = (nodeId: number): string => {
  const node = props.map.nodes.find((candidate) => candidate.id === nodeId);
  if (!node) return `Узел #${nodeId}`;

  return node.isPoi && node.name ? node.name : `Узел #${node.id}`;
};

const isSelected = (kind: MapHit['kind'], id: number): boolean =>
  props.selected?.kind === kind && props.selected.id === id;

function select(kind: MapHit['kind'], id: number): void {
  emit('select', { kind, id });
}

function edit(kind: MapHit['kind'], id: number): void {
  emit('edit', { kind, id });
}
</script>

<template>
  <div class="map-sidebar">
    <v-btn
      v-if="props.map.canEdit"
      block
      color="primary"
      variant="tonal"
      prepend-icon="mdi-plus"
      class="mb-3"
      @click="emit('create', 'node')"
    >
      Добавить точку
    </v-btn>

    <div class="d-flex ga-3 mb-2">
      <v-switch
        v-model="namedOnly"
        label="Только именованные"
        color="primary"
        density="compact"
        hide-details
        class="text-caption"
      />
      <v-switch v-model="poiOnly" label="Только POI" color="primary" density="compact" hide-details />
    </div>

    <v-expansion-panels variant="accordion" multiple>
      <v-expansion-panel title="Точки" :value="0">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="node in variantNodes"
              :key="node.id"
              :active="isSelected('node', node.id)"
              @click="select('node', node.id)"
            >
              <template #prepend>
                <v-icon :icon="node.icon || (node.isPoi ? 'mdi-map-marker' : 'mdi-checkbox-blank-circle-outline')" />
              </template>
              <v-list-item-title>{{ node.isPoi && node.name ? node.name : `Узел #${node.id}` }}</v-list-item-title>
              <template #append>
                <v-btn
                  v-if="props.map.canEdit"
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="text"
                  @click.stop="edit('node', node.id)"
                />
              </template>
            </v-list-item>
            <v-list-item v-if="variantNodes.length === 0">
              <v-list-item-title class="text-medium-emphasis">Нет точек</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="Объекты" :value="1">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="area in objectAreas"
              :key="area.id"
              :active="isSelected('area', area.id)"
              @click="select('area', area.id)"
            >
              <template #prepend>
                <v-icon icon="mdi-vector-square" />
              </template>
              <v-list-item-title>{{ area.label || `Объект #${area.id}` }}</v-list-item-title>
              <template #append>
                <v-btn
                  v-if="props.map.canEdit"
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="text"
                  @click.stop="edit('area', area.id)"
                />
              </template>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="Ландшафт" :value="2">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="area in terrainAreas"
              :key="area.id"
              :active="isSelected('area', area.id)"
              @click="select('area', area.id)"
            >
              <template #prepend>
                <v-icon icon="mdi-vector-polygon" />
              </template>
              <v-list-item-title>{{ area.label || `Ландшафт #${area.id}` }}</v-list-item-title>
              <template #append>
                <v-btn
                  v-if="props.map.canEdit"
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="text"
                  @click.stop="edit('area', area.id)"
                />
              </template>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="Ленты" :value="3">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="line in variantLines"
              :key="line.id"
              :active="isSelected('line', line.id)"
              @click="select('line', line.id)"
            >
              <template #prepend>
                <v-icon icon="mdi-waves" />
              </template>
              <v-list-item-title>{{ line.label || `Лента #${line.id}` }}</v-list-item-title>
              <template #append>
                <v-btn
                  v-if="props.map.canEdit"
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="text"
                  @click.stop="edit('line', line.id)"
                />
              </template>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="Рёбра" :value="4">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="edge in variantEdges"
              :key="edge.id"
              :active="isSelected('edge', edge.id)"
              @click="select('edge', edge.id)"
            >
              <template #prepend>
                <v-icon icon="mdi-vector-polyline" />
              </template>
              <v-list-item-title>
                {{ edge.label || `${nodeName(edge.fromNodeId)} → ${nodeName(edge.toNodeId)}` }}
              </v-list-item-title>
              <template #append>
                <v-btn
                  v-if="props.map.canEdit"
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="text"
                  @click.stop="edit('edge', edge.id)"
                />
              </template>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
