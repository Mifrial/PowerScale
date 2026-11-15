<script setup lang="ts">
import { computed, ref } from 'vue';
import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import { isLoreCode, loreSlug } from '@/modules/Roleplay/Lore/Utils/loreSlug';

const props = defineProps<{
  modelValue: LoreSectionInput[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: LoreSectionInput[]];
}>();

const error = ref<string | null>(null);
const newName = ref('');
const newCode = ref('');

const parentOptions = computed(() => [
  { value: null as string | null, title: '— Корень —' },
  ...props.modelValue.map((section) => ({ value: section.code, title: section.name })),
]);

function emitValue(sections: LoreSectionInput[]): void {
  emit('update:modelValue', sections);
}

function update(index: number, patch: Partial<LoreSectionInput>): void {
  emitValue(props.modelValue.map((section, position) => (position === index ? { ...section, ...patch } : section)));
}

function remove(index: number): void {
  const target = props.modelValue[index];
  if (!target) return;
  const hasChildren = props.modelValue.some((section) => section.parentCode === target.code);
  if (hasChildren) {
    error.value = 'Сначала перенесите дочерние разделы.';

    return;
  }

  error.value = null;
  emitValue(props.modelValue.filter((_, position) => position !== index));
}

function move(index: number, direction: -1 | 1): void {
  const next = index + direction;
  if (next < 0 || next >= props.modelValue.length) return;
  const copy = [...props.modelValue];
  const item = copy[index];
  if (!item) return;
  copy.splice(index, 1);
  copy.splice(next, 0, item);
  emitValue(copy.map((section, position) => ({ ...section, sortOrder: position })));
}

function add(): void {
  const name = newName.value.trim();
  const code = (newCode.value.trim() || loreSlug(name)).trim();
  if (name === '') {
    error.value = 'Укажите название раздела.';

    return;
  }
  if (!isLoreCode(code)) {
    error.value = 'Код раздела: латиница, цифры, дефис или подчёркивание.';

    return;
  }
  if (props.modelValue.some((section) => section.code === code)) {
    error.value = 'Раздел с таким кодом уже есть.';

    return;
  }

  error.value = null;
  emitValue([
    ...props.modelValue,
    {
      code,
      name,
      description: '',
      parentCode: null,
      sortOrder: props.modelValue.length,
      icon: 'mdi-folder-outline',
    },
  ]);
  newName.value = '';
  newCode.value = '';
}
</script>

<template>
  <div>
    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>

    <v-table v-if="props.modelValue.length > 0" density="compact">
      <thead>
        <tr>
          <th>Название</th>
          <th>Код</th>
          <th>Родитель</th>
          <th>Иконка</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="(section, index) in props.modelValue" :key="section.code">
          <td>
            <v-text-field
              :model-value="section.name"
              density="compact"
              hide-details
              @update:model-value="update(index, { name: String($event) })"
            />
          </td>
          <td class="text-caption text-medium-emphasis">{{ section.code }}</td>
          <td>
            <v-select
              :model-value="section.parentCode"
              :items="parentOptions.filter((option) => option.value !== section.code)"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
              @update:model-value="update(index, { parentCode: $event as string | null })"
            />
          </td>
          <td>
            <v-text-field
              :model-value="section.icon"
              density="compact"
              hide-details
              placeholder="mdi-folder"
              @update:model-value="update(index, { icon: String($event) })"
            />
          </td>
          <td class="text-right text-no-wrap">
            <v-btn size="x-small" icon="mdi-arrow-up" variant="text" @click="move(index, -1)" />
            <v-btn size="x-small" icon="mdi-arrow-down" variant="text" @click="move(index, 1)" />
            <v-btn size="x-small" icon="mdi-delete-outline" variant="text" color="error" @click="remove(index)" />
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-alert v-else type="info" variant="tonal" density="compact" class="mb-3">
      Разделов пока нет. Статьи можно создавать и без разделов.
    </v-alert>

    <v-row class="mt-3" dense>
      <v-col cols="12" md="5">
        <v-text-field v-model="newName" label="Новый раздел" density="compact" hide-details />
      </v-col>
      <v-col cols="12" md="5">
        <v-text-field
          v-model="newCode"
          label="Код (необязательно)"
          density="compact"
          hide-details
          placeholder="из названия"
        />
      </v-col>
      <v-col cols="12" md="2">
        <v-btn block variant="tonal" prepend-icon="mdi-plus" @click="add">Добавить</v-btn>
      </v-col>
    </v-row>
  </div>
</template>
