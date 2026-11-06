<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapAreaKind } from '@/modules/Roleplay/Map/Enum/MapAreaKind';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import { MAP_AREA_KIND_OPTIONS } from '@/modules/Roleplay/Map/Constant/Map/MAP_AREA_KIND_OPTIONS';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  area: MapArea | null;
  terrains: MapTerrain[];
  nodes: { id: number; name: string }[];
  submitting: boolean;
}>();

const emit = defineEmits<{
  submit: [edit: MapAreaEdit];
  remove: [areaId: number];
  openNode: [nodeId: number];
}>();

const form = reactive({
  layer: 'object' as MapAreaLayer,
  kind: 'other' as MapAreaKind,
  label: '',
  terrainCode: null as string | null,
  nodeId: null as number | null,
  blocksMovement: false,
  makePoi: false,
});

const terrainOptions = computed(() => props.terrains.map((terrain) => ({ title: terrain.label, value: terrain.code })));
const linkedNode = computed(() => props.nodes.find((node) => node.id === form.nodeId) ?? null);

const layerOptions: { title: string; value: MapAreaLayer }[] = [
  { title: 'Объект', value: 'object' },
  { title: 'Ландшафт', value: 'terrain' },
];

watch(
  () => form.layer,
  (layer) => {
    if (layer === 'terrain' && form.terrainCode === null) {
      form.terrainCode = props.terrains[0]?.code ?? null;
    }
  },
);

watch(open, (value) => {
  if (!value) return;
  form.layer = props.area?.layer ?? 'object';
  form.kind = props.area?.kind ?? 'other';
  form.label = props.area?.label ?? '';
  form.terrainCode = props.area?.terrainCode ?? (form.layer === 'terrain' ? (props.terrains[0]?.code ?? null) : null);
  form.nodeId = props.area?.nodeId ?? null;
  form.blocksMovement = props.area?.blocksMovement ?? false;
  form.makePoi = false;
});

function submit(): void {
  emit('submit', {
    layer: form.layer,
    terrainCode: form.layer === 'terrain' ? form.terrainCode : null,
    kind: form.layer === 'object' ? form.kind : 'other',
    label: form.label.trim(),
    nodeId: form.nodeId,
    blocksMovement: form.layer === 'object' ? form.blocksMovement : false,
    makePoi: form.layer === 'object' && form.makePoi && form.nodeId === null,
  });
}

function remove(): void {
  if (props.area) emit('remove', props.area.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="520" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">
        {{ props.area ? 'Область карты' : 'Новая область' }}
      </v-card-title>
      <v-card-text>
        <v-select
          v-model="form.layer"
          :items="layerOptions"
          item-title="title"
          item-value="value"
          label="Слой"
          variant="outlined"
          density="comfortable"
        />
        <v-select
          v-if="form.layer === 'terrain'"
          v-model="form.terrainCode"
          :items="terrainOptions"
          label="Террейн"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
        <v-select
          v-else
          v-model="form.kind"
          :items="MAP_AREA_KIND_OPTIONS"
          item-title="title"
          item-value="value"
          label="Вид объекта"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
        <v-text-field v-model="form.label" label="Подпись" variant="outlined" density="comfortable" class="mt-3" />
        <v-select
          v-model="form.nodeId"
          :items="props.nodes"
          item-title="name"
          item-value="id"
          label="Привязанная точка интереса"
          variant="outlined"
          density="comfortable"
          clearable
          class="mt-3"
        />
        <div v-if="form.layer === 'object'" class="mt-2">
          <v-switch
            v-if="form.nodeId === null"
            v-model="form.makePoi"
            label="Сделать точкой интереса"
            color="primary"
            density="compact"
            hide-details
          />
          <div v-else class="d-flex align-center ga-2">
            <span class="text-caption text-medium-emphasis">{{ linkedNode?.name ?? 'Точка привязана' }}</span>
            <v-btn size="small" variant="text" @click="emit('openNode', Number(form.nodeId))">Открыть точку</v-btn>
          </div>
        </div>
        <v-switch
          v-if="form.layer === 'object'"
          v-model="form.blocksMovement"
          label="Непроходимый объект"
          color="primary"
          density="compact"
          hide-details
          class="mt-3"
        />
        <p v-else class="text-caption text-medium-emphasis mt-3">
          Проходимость ландшафта задаёт скорость террейна: ноль блокирует маршрут.
        </p>
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="props.area"
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
