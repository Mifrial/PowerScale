<script setup lang="ts">
defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  keywordIds: number[]
  mechanicOptions: { title: string; value: number }[]
  keywordOptions: { title: string; value: number }[]
  /** Код неизменяем после создания — поле блокируется при редактировании. */
  codeDisabled?: boolean
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:keywordIds': [value: number[]]
}>()
</script>

<template>
  <div>
    <v-text-field
      :model-value="name"
      label="Название"
      :rules="[v => !!v || 'Обязательное поле']"
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

    <v-textarea
      :model-value="description"
      label="Описание"
      rows="5"
      class="mt-4"
      @update:model-value="emit('update:description', $event)"
    />

    <v-select
      :model-value="mechanicId"
      :items="mechanicOptions"
      item-title="title"
      item-value="value"
      label="Механика"
      clearable
      class="mt-4"
      @update:model-value="emit('update:mechanicId', $event)"
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
