<script setup lang="ts">
import { ref, watch } from 'vue';

const open = defineModel<boolean>({ default: false });

const props = defineProps<{
  text?: string | null;
  saving?: boolean;
  error?: string | null;
}>();

const emit = defineEmits<{
  save: [value: string];
}>();

const draft = ref('');

watch(open, (value) => {
  if (value) draft.value = props.text ?? '';
});
</script>

<template>
  <v-dialog v-model="open" max-width="560">
    <v-card>
      <v-card-title>Заметки</v-card-title>
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-3">Только вы видите эти заметки.</p>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
        <v-textarea v-model="draft" auto-grow rows="8" hide-details placeholder="Черновик для себя" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="saving" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="emit('save', draft)">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
