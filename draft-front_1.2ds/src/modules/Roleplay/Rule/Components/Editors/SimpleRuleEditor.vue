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
      :model-value="tagIds"
      :items="tagOptions"
      item-title="title"
      item-value="value"
      label="Признаки"
      multiple
      chips
      closable-chips
      class="mt-4"
      @update:model-value="emit('update:tagIds', $event)"
    />
  </div>
</template>

<script setup lang="ts">
defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  tagIds: number[]
  mechanicOptions: { title: string; value: number }[]
  tagOptions: { title: string; value: number }[]
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:tagIds': [value: number[]]
}>()
</script>
