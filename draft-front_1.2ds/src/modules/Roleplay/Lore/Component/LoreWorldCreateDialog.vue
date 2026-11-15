<script setup lang="ts">
import { computed, ref } from 'vue';
import type { LoreWorld } from '@/modules/Roleplay/Lore/Dto/LoreWorld';
import type { LoreWorldVisibility } from '@/modules/Roleplay/Lore/Enum/LoreWorldVisibility';
import { LORE_WORLD_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Lore/Constant/LORE_VISIBILITY_OPTIONS';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import { isLoreCode, loreSlug } from '@/modules/Roleplay/Lore/Utils/loreSlug';

const props = defineProps<{
  modelValue: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  created: [world: LoreWorld];
}>();

const name = ref('');
const code = ref('');
const description = ref('');
const visibility = ref<LoreWorldVisibility>('private');
const saving = ref(false);
const error = ref<string | null>(null);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
});

const resolvedCode = computed(() => (code.value.trim() || loreSlug(name.value)).trim());

async function create(): Promise<void> {
  const worldName = name.value.trim();
  if (worldName === '') {
    error.value = 'Укажите название мира.';

    return;
  }
  if (!isLoreCode(resolvedCode.value)) {
    error.value = 'Код мира: латиница, цифры, дефис или подчёркивание.';

    return;
  }

  saving.value = true;
  error.value = null;
  try {
    const world = await getLoreApi().saveWorld({
      world: {
        code: resolvedCode.value,
        name: worldName,
        description: description.value,
        visibility: visibility.value,
        spaceCode: '',
      },
    });
    emit('created', world);
    isOpen.value = false;
    name.value = '';
    code.value = '';
    description.value = '';
    visibility.value = 'private';
  } catch (exception) {
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось создать мир';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <v-dialog v-model="isOpen" max-width="520">
    <v-card>
      <v-card-title>Новый мир кодекса</v-card-title>
      <v-card-text>
        <v-text-field v-model="name" label="Название" autofocus />
        <v-text-field
          v-model="code"
          label="Код URL"
          :placeholder="loreSlug(name) || 'austerland'"
          hint="Стабильный код: латиница, цифры, дефис. После создания не меняется."
          persistent-hint
        />
        <v-textarea v-model="description" label="Описание" rows="3" auto-grow class="mt-4" />
        <v-select
          v-model="visibility"
          :items="LORE_WORLD_VISIBILITY_OPTIONS"
          item-title="title"
          item-value="value"
          label="Видимость"
          class="mt-2"
        />
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="isOpen = false">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="create">Создать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
