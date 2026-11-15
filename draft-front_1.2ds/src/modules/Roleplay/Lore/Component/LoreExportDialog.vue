<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { LoreFile } from '@/modules/Roleplay/Lore/Dto/File/LoreFile';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';

const props = defineProps<{
  modelValue: boolean;
  world: LoreWorldDetail;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const file = ref<LoreFile | null>(null);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
});

watch(isOpen, (open) => {
  if (open) void load();
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    file.value = await getLoreApi().exportWorld(props.world.code);
  } catch (exception) {
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось собрать файл мира';
  } finally {
    loading.value = false;
  }
}

function download(): void {
  if (!file.value) return;
  const blob = new Blob([JSON.stringify(file.value, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${props.world.code}.lore.json`;
  link.click();
  URL.revokeObjectURL(url);
}
</script>

<template>
  <v-dialog v-model="isOpen" max-width="560">
    <v-card>
      <v-card-title>Экспорт мира</v-card-title>
      <v-card-text>
        <p>
          В файл попадут все разделы и статьи мира «{{ props.world.name }}», включая черновики и секреты. Признаки
          сохраняются кодами, читатели — логинами.
        </p>
        <v-list v-if="file" density="compact">
          <v-list-item title="Разделов" :subtitle="String(file.sections.length)" />
          <v-list-item title="Статей" :subtitle="String(file.articles.length)" />
          <v-list-item title="Признаков" :subtitle="String(file.keywords.length)" />
        </v-list>
        <v-progress-linear v-if="loading" indeterminate class="my-3" />
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2">
          {{ error }}
          <v-btn size="small" variant="text" @click="load">Повторить</v-btn>
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="isOpen = false">Закрыть</v-btn>
        <v-btn color="primary" prepend-icon="mdi-download" :disabled="!file" @click="download">Скачать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
