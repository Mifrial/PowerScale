<script setup lang="ts">
import type { MapConflict } from '@/modules/Roleplay/Map/Dto/MapConflict';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  conflict: MapConflict | null;
  saving: boolean;
}>();

const emit = defineEmits<{
  overwrite: [];
  copy: [];
}>();

const formatDate = (unixSeconds: number): string =>
  unixSeconds > 0 ? new Date(unixSeconds * 1000).toLocaleString('ru-RU') : 'неизвестно';
</script>

<template>
  <v-dialog v-model="open" max-width="560" persistent>
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">Карту изменили</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">
          Пока вы редактировали черновик, карту опубликовали заново. Выберите, что делать с вашими правками.
        </p>
        <v-alert type="info" variant="tonal" density="compact">
          <div>Текущая версия: {{ props.conflict?.currentVersion ?? '—' }}</div>
          <div>Обновлена: {{ formatDate(props.conflict?.updatedAt ?? 0) }}</div>
          <div>Автор: {{ props.conflict?.updatedBy === null ? 'неизвестен' : `#${props.conflict?.updatedBy}` }}</div>
        </v-alert>
      </v-card-text>
      <v-card-actions class="flex-wrap">
        <v-btn variant="text" :disabled="props.saving" @click="open = false">Продолжить редактирование</v-btn>
        <v-spacer />
        <v-btn variant="tonal" :disabled="props.saving" @click="emit('copy')">Сохранить копией</v-btn>
        <v-btn color="warning" variant="tonal" :loading="props.saving" @click="emit('overwrite')">Перезаписать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
