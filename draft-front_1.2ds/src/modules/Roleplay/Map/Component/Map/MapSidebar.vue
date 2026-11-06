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

const linkedNodeIds = computed(() => {
  const ids = new Set<number>();
  for (const area of props.map.areas) {
    if (area.nodeId !== null) ids.add(area.nodeId);
  }

  return ids;
});

const variantNodes = computed(() => {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.nodes.filter((node) => {
    if (linkedNodeIds.value.has(node.id)) return false;
    if (!node.placements.some((placement) => placement.variantId === variant.id && placement.isPresent)) return false;
    if (namedOnly.value && node.name.trim() === '') return false;

    return true;
  });
});

function variantAreas(layer: 'surface' | 'object') {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.areas
    .filter((area) => {
      if (area.layer !== layer) return false;
      if (!area.shapes.some((shape) => shape.variantId === variant.id && shape.isPresent)) return false;
      if (namedOnly.value && area.label.trim() === '') return false;

      return true;
    })
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
}

const objectAreas = computed(() => variantAreas('object'));
const surfaceAreas = computed(() => variantAreas('surface'));
const placeAreas = computed(() =>
  [...objectAreas.value, ...surfaceAreas.value].filter((area) => area.nodeId !== null),
);

const variantLines = computed(() => {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.lines
    .filter((line) => {
      if (line.variantId !== variant.id || !line.isPresent) return false;
      if (namedOnly.value && line.label.trim() === '') return false;

      return true;
    })
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
});

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
      Добавить место
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
    </div>

    <v-expansion-panels variant="accordion" multiple>
      <v-expansion-panel title="Места" :value="0">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="node in variantNodes"
              :key="node.id"
              :active="isSelected('node', node.id)"
              @click="select('node', node.id)"
            >
              <template #prepend>
                <v-icon :icon="node.icon || 'mdi-map-marker'" />
              </template>
              <v-list-item-title>{{ node.name || `Место #${node.id}` }}</v-list-item-title>
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
            <v-list-item
              v-for="area in placeAreas"
              :key="`place-${area.id}`"
              :active="isSelected('area', area.id)"
              @click="select('area', area.id)"
            >
              <template #prepend>
                <v-icon icon="mdi-map-marker-circle" />
              </template>
              <v-list-item-title>{{ area.label || `Место #${area.id}` }}</v-list-item-title>
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
            <v-list-item v-if="variantNodes.length === 0 && placeAreas.length === 0">
              <v-list-item-title class="text-medium-emphasis">Нет мест</v-list-item-title>
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

      <v-expansion-panel title="Поверхности" :value="2">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="area in surfaceAreas"
              :key="area.id"
              :active="isSelected('area', area.id)"
              @click="select('area', area.id)"
            >
              <template #prepend>
                <v-icon icon="mdi-vector-polygon" />
              </template>
              <v-list-item-title>{{ area.label || `Поверхность #${area.id}` }}</v-list-item-title>
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

    </v-expansion-panels>
  </div>
</template>
