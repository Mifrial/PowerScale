<script setup lang="ts">
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';

defineProps<{
  open: boolean;
  session: CommittedActionSession | null;
  actionName: string;
  availableOd: number;
  busy?: boolean;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  continue: [];
  abort: [];
}>();
</script>

<template>
  <v-dialog :model-value="open" max-width="420" persistent @update:model-value="emit('update:open', $event)">
    <v-card v-if="session">
      <v-card-title>Продолжить действие?</v-card-title>
      <v-card-text>
        Незавершённое «{{ actionName }}»: осталось {{ session.remainingOd }} ОД, сейчас {{ availableOd }}. Если бросишь,
        потраченные ОД не вернутся и эффекта не будет.
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="emit('abort')">Бросить</v-btn>
        <v-btn color="primary" :loading="busy" @click="emit('continue')">Продолжить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
