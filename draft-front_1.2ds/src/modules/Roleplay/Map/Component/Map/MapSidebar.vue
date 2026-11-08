<script setup lang="ts">
import { computed, ref } from 'vue';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
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
  'edit-group': [groupId: number];
  ungroup: [areaId: number];
}>();

/** Сколько строк списка рисовать сразу: на 1000+ объектов сайдбар не должен тонуть. */
const LIST_LIMIT = 100;

const namedOnly = ref(false);
const expandedLists = ref<Set<string>>(new Set());

function visible<T>(items: T[], key: string): T[] {
  return expandedLists.value.has(key) ? items : items.slice(0, LIST_LIMIT);
}

function isExpanded(key: string): boolean {
  return expandedLists.value.has(key);
}

function expand(key: string): void {
  expandedLists.value = new Set(expandedLists.value).add(key);
}

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
      if (area.groupId !== null) return false;
      if (!area.shapes.some((shape) => shape.variantId === variant.id && shape.isPresent)) return false;
      if (namedOnly.value && area.label.trim() === '') return false;

      return true;
    })
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
}

const objectAreas = computed(() => variantAreas('object'));
const surfaceAreas = computed(() => variantAreas('surface'));
// Места ищем по всем областям, включая сгруппированные: иначе площадное
// место, попавшее в группу, пропало бы из списка мест.
const placeAreas = computed(() => {
  const variant = props.variant;
  if (!variant) return [];

  return props.map.areas
    .filter(
      (area) =>
        area.nodeId !== null &&
        area.shapes.some((shape) => shape.variantId === variant.id && shape.isPresent),
    )
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
});

/** Группы карты с участниками текущего варианта. */
const groupRows = computed(() => {
  const variant = props.variant;
  if (!variant) return [];
  const members = new Map<number, MapArea[]>();
  for (const area of props.map.areas) {
    if (area.groupId === null) continue;
    if (!area.shapes.some((shape) => shape.variantId === variant.id && shape.isPresent)) continue;
    const list = members.get(area.groupId) ?? [];
    list.push(area);
    members.set(area.groupId, list);
  }

  return props.map.groups
    .map((group) => ({ ...group, areas: members.get(group.id) ?? [] }))
    .sort((left, right) => right.areas.length - left.areas.length || left.id - right.id);
});

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
              v-for="node in visible(variantNodes, 'nodes')"
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
              v-for="area in visible(placeAreas, 'places')"
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

      <v-expansion-panel title="Группы" :value="4">
        <v-expansion-panel-text>
          <v-list v-if="groupRows.length > 0" density="compact" nav>
            <v-list-group v-for="group in groupRows" :key="group.id" :value="group.id">
              <template #activator="{ props: activatorProps }">
                <v-list-item
                  v-bind="activatorProps"
                  :title="group.label || `Группа #${group.id}`"
                >
                  <template #prepend>
                    <v-icon icon="mdi-folder-multiple-outline" />
                  </template>
                  <template #append>
                    <span class="text-caption text-medium-emphasis mr-2">{{ group.areas.length }}</span>
                    <v-btn
                      v-if="props.map.canEdit"
                      icon="mdi-pencil-outline"
                      size="x-small"
                      variant="text"
                      title="Свойства группы"
                      @click.stop="emit('edit-group', group.id)"
                    />
                  </template>
                </v-list-item>
              </template>
              <v-list-item
                v-for="area in visible(group.areas, `group-${group.id}`)"
                :key="area.id"
                :active="isSelected('area', area.id)"
                @click="select('area', area.id)"
              >
                <template #prepend>
                  <v-icon icon="mdi-vector-square" size="small" />
                </template>
                <v-list-item-title class="text-caption">
                  {{ area.label || `Объект #${area.id}` }}
                </v-list-item-title>
                <template #append>
                  <v-btn
                    v-if="props.map.canEdit"
                    icon="mdi-link-variant-off"
                    size="x-small"
                    variant="text"
                    title="Вывести из группы"
                    @click.stop="emit('ungroup', area.id)"
                  />
                </template>
              </v-list-item>
              <v-list-item
                v-if="!isExpanded(`group-${group.id}`) && group.areas.length > LIST_LIMIT"
                @click="expand(`group-${group.id}`)"
              >
                <v-list-item-title class="text-caption text-primary">
                  Показать все ({{ group.areas.length }})
                </v-list-item-title>
              </v-list-item>
            </v-list-group>
          </v-list>
          <p v-else class="text-caption text-medium-emphasis">Нет групп</p>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="Объекты" :value="1">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="area in visible(objectAreas, 'objects')"
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
            <v-list-item
              v-if="!isExpanded('objects') && objectAreas.length > LIST_LIMIT"
              @click="expand('objects')"
            >
              <v-list-item-title class="text-caption text-primary">
                Показать все ({{ objectAreas.length }})
              </v-list-item-title>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="Поверхности" :value="2">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="area in visible(surfaceAreas, 'surfaces')"
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
            <v-list-item
              v-if="!isExpanded('surfaces') && surfaceAreas.length > LIST_LIMIT"
              @click="expand('surfaces')"
            >
              <v-list-item-title class="text-caption text-primary">
                Показать все ({{ surfaceAreas.length }})
              </v-list-item-title>
            </v-list-item>
          </v-list>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="Ленты" :value="3">
        <v-expansion-panel-text>
          <v-list density="compact" nav>
            <v-list-item
              v-for="line in visible(variantLines, 'lines')"
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
