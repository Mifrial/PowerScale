<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import { isLoreCode, loreSlug } from '@/modules/Roleplay/Lore/Utils/loreSlug';

const props = defineProps<{
  modelValue: boolean;
  mode: 'create' | 'edit';
  section: LoreSectionInput | null;
  parentCode: string | null;
  parentPath: string;
  parentOptions: { value: string | null; title: string }[];
  existingCodes: string[];
  removeBlocked: string | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  save: [payload: { code: string; name: string; description: string; icon: string; parentCode: string | null }];
  remove: [];
}>();

const isOpen = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
});

const title = computed(() => (props.mode === 'create' ? 'Новый раздел' : 'Раздел'));
const resolvedCode = computed(() => (code.value.trim() || loreSlug(name.value)).trim());

const name = ref('');
const code = ref('');
const description = ref('');
const icon = ref('');
const selectedParent = ref<string | null>(null);
const error = ref<string | null>(null);

watch(
  () => [props.modelValue, props.section, props.mode, props.parentCode] as const,
  () => {
    if (!props.modelValue) return;
    error.value = null;
    if (props.mode === 'edit' && props.section) {
      name.value = props.section.name;
      code.value = props.section.code;
      description.value = props.section.description;
      icon.value = props.section.icon;
      selectedParent.value = props.section.parentCode;

      return;
    }
    name.value = '';
    code.value = '';
    description.value = '';
    icon.value = '';
    selectedParent.value = props.parentCode;
  },
);

function save(): void {
  if (name.value.trim() === '') {
    error.value = 'Укажите название раздела.';

    return;
  }
  if (!isLoreCode(resolvedCode.value)) {
    error.value = 'Код раздела: латиница, цифры, дефис или подчёркивание.';

    return;
  }
  const duplicated = props.mode === 'create' && props.existingCodes.includes(resolvedCode.value);
  if (duplicated) {
    error.value = 'Раздел с таким кодом уже есть.';

    return;
  }
  error.value = null;
  emit('save', {
    code: resolvedCode.value,
    name: name.value.trim(),
    description: description.value.trim(),
    icon: icon.value.trim(),
    parentCode: selectedParent.value,
  });
}
</script>

<template>
  <v-dialog v-model="isOpen" max-width="520">
    <v-card>
      <v-card-title>{{ title }}</v-card-title>
      <v-card-text>
        <div v-if="props.parentPath" class="text-caption text-medium-emphasis mb-3">Путь: {{ props.parentPath }}</div>
        <v-text-field v-model="name" label="Название" autofocus class="mb-2" />
        <v-text-field
          v-model="code"
          label="Код"
          :disabled="props.mode === 'edit'"
          :placeholder="loreSlug(name) || 'history'"
          :hint="props.mode === 'edit' ? 'Код не меняется после создания' : 'Стабильный код раздела для ссылок'"
          persistent-hint
          class="mb-2"
        />
        <v-select
          v-model="selectedParent"
          :items="props.parentOptions"
          item-title="title"
          item-value="value"
          label="Родительский раздел"
          variant="outlined"
          density="comfortable"
          hide-details="auto"
          class="mb-2"
        />
        <v-textarea v-model="description" label="Описание" rows="2" auto-grow class="mb-2" />
        <v-text-field v-model="icon" label="Иконка" placeholder="mdi-folder-outline" hint="Например, mdi-sword" />
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2">{{ error }}</v-alert>
        <v-alert v-if="props.removeBlocked" type="info" variant="tonal" density="compact" class="mt-2">
          {{ props.removeBlocked }}
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="props.mode === 'edit'"
          color="error"
          variant="text"
          :disabled="!!props.removeBlocked"
          @click="emit('remove')"
        >
          Удалить
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="isOpen = false">Отмена</v-btn>
        <v-btn color="primary" @click="save">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
