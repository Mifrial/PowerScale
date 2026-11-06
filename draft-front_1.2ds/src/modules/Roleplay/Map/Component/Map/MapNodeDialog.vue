<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapNodeEdit } from '@/modules/Roleplay/Map/Dto/MapNodeEdit';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  node: MapNode | null;
  mapOptions: MapSummary[];
  submitting: boolean;
}>();

const emit = defineEmits<{
  submit: [edit: MapNodeEdit];
  remove: [nodeId: number];
}>();

const canSubmit = computed(() => !form.isPoi || form.name.trim() !== '');

const form = reactive({
  isPoi: true,
  name: '',
  shortInfo: '',
  publicInfo: '',
  gmInfo: '',
  icon: '',
  linkedMapId: null as number | null,
});

watch(open, (value) => {
  if (!value) return;
  form.isPoi = props.node?.isPoi ?? true;
  form.name = props.node?.name ?? '';
  form.shortInfo = props.node?.shortInfo ?? '';
  form.publicInfo = props.node?.publicInfo ?? '';
  form.gmInfo = props.node?.gmInfo ?? '';
  form.icon = props.node?.icon ?? '';
  form.linkedMapId = props.node?.linkedMapId ?? null;
});

function submit(): void {
  emit('submit', {
    isPoi: form.isPoi,
    name: form.name.trim(),
    shortInfo: form.shortInfo.trim(),
    publicInfo: form.publicInfo.trim(),
    gmInfo: form.gmInfo.trim(),
    icon: form.icon.trim(),
    linkedMapId: form.linkedMapId,
  });
}

function remove(): void {
  if (props.node) emit('remove', props.node.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="640" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">
        {{ props.node ? 'Точка карты' : 'Новая точка' }}
      </v-card-title>
      <v-card-text>
        <v-switch
          v-model="form.isPoi"
          label="Точка интереса (видимая)"
          color="primary"
          density="compact"
          hide-details
        />
        <p class="text-caption text-medium-emphasis mt-1">
          Выключено — невидимый waypoint для маршрута. Включено — видимая точка с информацией; её можно привязать к
          объекту.
        </p>
        <v-row dense class="mt-2">
          <v-col cols="12" sm="8">
            <v-text-field v-model="form.name" label="Имя" variant="outlined" density="comfortable" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="form.icon"
              label="Иконка (mdi-…)"
              variant="outlined"
              density="comfortable"
              placeholder="mdi-map-marker"
            />
          </v-col>
        </v-row>
        <v-textarea
          v-model="form.shortInfo"
          label="Короткая информация"
          variant="outlined"
          density="comfortable"
          rows="2"
          class="mt-2"
        />
        <v-textarea
          v-model="form.publicInfo"
          label="Раскрываемая информация"
          variant="outlined"
          density="comfortable"
          rows="2"
          class="mt-2"
        />
        <v-textarea
          v-model="form.gmInfo"
          label="Информация ведущего"
          variant="outlined"
          density="comfortable"
          rows="2"
          class="mt-2"
        />
        <v-select
          v-model="form.linkedMapId"
          :items="props.mapOptions"
          item-title="name"
          item-value="id"
          label="Связанная карта"
          variant="outlined"
          density="comfortable"
          clearable
          class="mt-2"
        />
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="props.node"
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
