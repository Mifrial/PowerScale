<script setup lang="ts">
import RuleDescriptionEditor from '@/modules/Roleplay/Rule/Component/Editors/RuleDescriptionEditor.vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

defineProps<{
  name: string;
  code: string;
  description: string;
  keywordIds: number[];
  keywordOptions: { title: string; value: number }[];
  rules?: Rule[];
  /** Код неизменяем после создания — поле блокируется при редактировании. */
  codeDisabled?: boolean;
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:keywordIds': [value: number[]];
}>();
</script>

<template>
  <div>
    <v-text-field
      :model-value="name"
      label="Название"
      :rules="[(v) => !!v || 'Обязательное поле']"
      @update:model-value="emit('update:name', $event)"
    />

    <v-text-field
      :model-value="code"
      label="Код (системное имя)"
      hint="Используется в ссылках между правилами. Пусто — генерируется автоматически из названия."
      :disabled="codeDisabled"
      class="mt-4"
      @update:model-value="emit('update:code', $event)"
    />

    <RuleDescriptionEditor
      :model-value="description"
      :rules="rules ?? []"
      @update:model-value="emit('update:description', $event)"
    />

    <v-select
      :model-value="keywordIds"
      :items="keywordOptions"
      item-title="title"
      item-value="value"
      label="Признаки"
      multiple
      chips
      closable-chips
      class="mt-4"
      @update:model-value="emit('update:keywordIds', $event)"
    />
  </div>
</template>
