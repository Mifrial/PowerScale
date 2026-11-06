<script setup lang="ts">
import { reactive, watch } from 'vue';
import type { MapMeta } from '@/modules/Roleplay/Map/Dto/MapMeta';
import type { MapVisibility } from '@/modules/Roleplay/Map/Enum/MapVisibility';
import { MAP_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Map/Constant/Map/MAP_VISIBILITY_OPTIONS';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  submitting: boolean;
}>();

const emit = defineEmits<{
  submit: [meta: MapMeta];
}>();

const form = reactive<{ name: string; description: string; visibility: MapVisibility }>({
  name: '',
  description: '',
  visibility: 'private',
});

watch(open, (value) => {
  if (value) {
    form.name = '';
    form.description = '';
    form.visibility = 'private';
  }
});

function submit(): void {
  const name = form.name.trim();
  if (name === '') return;
  emit('submit', { name, description: form.description.trim(), visibility: form.visibility });
}
</script>

<template>
  <v-dialog v-model="open" max-width="520" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">Новая карта</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.name" label="Название" variant="outlined" density="comfortable" autofocus />
        <v-textarea
          v-model="form.description"
          label="Описание"
          variant="outlined"
          density="comfortable"
          rows="3"
          class="mt-3"
        />
        <v-select
          v-model="form.visibility"
          :items="MAP_VISIBILITY_OPTIONS"
          item-title="title"
          item-value="value"
          label="Видимость"
          variant="outlined"
          density="comfortable"
          class="mt-3"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="props.submitting" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" variant="tonal" :loading="props.submitting" @click="submit">Создать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
