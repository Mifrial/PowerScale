<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import { RULE_CATALOG_AREA_OPTIONS } from '@/modules/Roleplay/RuleSpace/Constant/RULE_CATALOG_AREA_OPTIONS';

const props = defineProps<{
  modelValue: boolean;
  mode: 'create' | 'edit';
  section: AbilitySection | null;
  parentPath: string;
  deleteBlocked: string | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  save: [payload: { code: string; name: string; catalogRootFor: string | null }];
  remove: [];
}>();

const code = ref('');
const name = ref('');
const catalogRootFor = ref<string | null>(null);

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
});

const title = computed(() => (props.mode === 'create' ? 'Новая секция' : 'Секция'));
const areaItems = computed(() => {
  const items: { title: string; value: string | null }[] = [
    { title: 'Нет', value: null },
    ...RULE_CATALOG_AREA_OPTIONS,
  ];
  const current = props.section?.catalogRootFor;
  if (current && !items.some((item) => item.value === current)) {
    items.push({ title: current, value: current });
  }

  return items;
});

watch(
  () => [props.modelValue, props.section, props.mode] as const,
  () => {
    if (!props.modelValue) return;
    if (props.mode === 'edit' && props.section) {
      code.value = props.section.code;
      name.value = props.section.name;
      catalogRootFor.value = props.section.catalogRootFor ?? null;
    } else {
      code.value = '';
      name.value = '';
      catalogRootFor.value = null;
    }
  },
);

function save(): void {
  if (!name.value.trim()) return;
  if (props.mode === 'create' && !code.value.trim()) return;
  emit('save', {
    code: code.value.trim(),
    name: name.value.trim(),
    catalogRootFor: catalogRootFor.value,
  });
}
</script>

<template>
  <v-dialog v-model="open" max-width="480">
    <v-card class="section-editor-card">
      <v-card-title>{{ title }}</v-card-title>
      <v-card-text class="pb-2">
        <div v-if="parentPath" class="text-body-2 text-medium-emphasis mb-3">Путь: {{ parentPath }}</div>
        <v-text-field
          v-model="code"
          label="Код"
          :disabled="mode === 'edit'"
          :rules="[(v) => !!v || 'Обязательное поле']"
          density="compact"
          hide-details="auto"
        />
        <v-text-field
          v-model="name"
          label="Название"
          :rules="[(v) => !!v || 'Обязательное поле']"
          class="mt-2"
          density="compact"
          hide-details="auto"
        />
        <v-select
          v-model="catalogRootFor"
          :items="areaItems"
          item-title="title"
          item-value="value"
          label="Корень области редактора"
          class="mt-2"
          density="compact"
          hide-details
          clearable
        />
      </v-card-text>
      <v-card-actions>
        <v-btn v-if="mode === 'edit'" color="error" variant="text" :disabled="!!deleteBlocked" @click="emit('remove')">
          Удалить
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" @click="save">Сохранить</v-btn>
      </v-card-actions>
      <v-alert v-if="deleteBlocked" type="info" variant="tonal" density="compact" class="mx-4 mb-4">
        {{ deleteBlocked }}
      </v-alert>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.section-editor-card :deep(.v-card-text) {
  flex: none;
}
</style>
