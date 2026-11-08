<script setup lang="ts">
import { ref, watch } from 'vue';

const open = defineModel<boolean>('modelValue', { default: false });

const emit = defineEmits<{
  create: [title: string];
}>();

const title = ref('');

watch(open, (isOpen) => {
  if (isOpen) title.value = '';
});

function submit(): void {
  const value = title.value.trim();
  if (!value) return;
  emit('create', value);
  open.value = false;
}
</script>

<template>
  <v-dialog v-model="open" max-width="360">
    <v-card title="Новая заметка">
      <v-card-text>
        <v-text-field v-model="title" autofocus label="Заголовок" maxlength="255" @keyup.enter="submit" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" variant="flat" :disabled="!title.trim()" @click="submit">Создать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
