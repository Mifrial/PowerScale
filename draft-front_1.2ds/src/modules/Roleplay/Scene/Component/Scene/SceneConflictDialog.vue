<script setup lang="ts">
import { computed } from 'vue';
import type { SceneConflict } from '@/modules/Roleplay/Scene/Dto/SceneConflict';

const props = defineProps<{
  conflict: SceneConflict | null;
  currentVersion: number;
}>();

const emit = defineEmits<{
  reload: [];
  overwrite: [];
}>();

const isOpen = computed(() => props.conflict !== null);

function formatDate(unix: number): string {
  return unix === 0 ? '—' : new Date(unix * 1000).toLocaleString();
}
</script>

<template>
  <v-dialog :model-value="isOpen" max-width="460" persistent>
    <v-card>
      <v-card-title>Локацию изменили в другой сессии</v-card-title>
      <v-card-text v-if="props.conflict !== null">
        <p>
          Сохранённая версия — {{ props.conflict.currentVersion }} от {{ formatDate(props.conflict.updatedAt) }}.
          Текущая версия — {{ props.currentVersion }}.
        </p>
        <p class="text-medium-emphasis">
          Перезагрузка потеряет несохранённые правки. Перезапись сохранит их поверх серверной версии.
        </p>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" @click="emit('reload')">Перезагрузить</v-btn>
        <v-spacer />
        <v-btn color="warning" variant="tonal" @click="emit('overwrite')">Перезаписать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
