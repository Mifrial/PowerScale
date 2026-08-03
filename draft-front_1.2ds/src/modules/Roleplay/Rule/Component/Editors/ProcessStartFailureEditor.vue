<script setup lang="ts">
defineProps<{
  startStepCode: string | undefined
  failure: string | null
  stepRefs: { name: string; code: string }[]
}>()

const emit = defineEmits<{
  'update:startStepCode': [value: string | undefined]
  'update:failure': [value: string | null]
}>()

const failureOptions = [
  { label: 'Нет', value: null },
  { label: 'Начать заново с первого шага', value: 'restart_from_first' },
  { label: 'Завершить действие', value: 'end_action' },
]

function updateStartStepCode(value: string | undefined) {
  emit('update:startStepCode', value)
}

function updateFailure(value: string | null) {
  emit('update:failure', value)
}
</script>

<template>
  <div>
    <div class="text-subtitle-2 mb-1">Старт и провал</div>
    <div class="d-flex gap-2 flex-wrap">
      <v-autocomplete
        :model-value="startStepCode ?? null"
        @update:model-value="(v) => updateStartStepCode(v ?? undefined)"
        :items="stepRefs"
        item-title="name"
        item-value="code"
        label="Начальный шаг"
        density="compact"
        hide-details
        clearable
        style="min-width: 220px;"
      />
      <v-select
        :model-value="failure ?? null"
        @update:model-value="(v) => updateFailure(v ?? null)"
        :items="failureOptions"
        item-title="label"
        item-value="value"
        label="Провал шага"
        density="compact"
        hide-details
        clearable
        style="min-width: 280px;"
      />
    </div>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
