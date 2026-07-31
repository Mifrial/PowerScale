<template>
  <v-card variant="outlined">
    <v-card-text class="pa-0">
      <v-table density="compact">
        <thead>
          <tr>
            <th class="text-left">Категория</th>
            <th v-for="action in allActions" :key="action" class="text-center">
              {{ ACTION_LABELS[action] || action }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="category in categories" :key="category">
            <td class="font-weight-medium">{{ PERMISSION_LABELS[category] }}</td>
            <td v-for="action in allActions" :key="action" class="text-center">
              <v-checkbox
                v-if="hasPermission(category, action)"
                :model-value="isChecked(category, action)"
                :disabled="disabled"
                density="compact"
                hide-details
                class="d-flex justify-center"
                @update:model-value="toggle(category, action, $event)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { PERMISSION_KEYS, PERMISSION_LABELS, ACTION_LABELS } from '../Interface/types'

const props = defineProps<{
  modelValue: string[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

const categories = Object.keys(PERMISSION_KEYS) as (keyof typeof PERMISSION_KEYS)[]

const allActions = computed(() => {
  const actions = new Set<string>()
  for (const perms of Object.values(PERMISSION_KEYS)) {
    for (const p of perms) actions.add(p)
  }
  return Array.from(actions)
})

function hasPermission(category: string, action: string): boolean {
  const perms = PERMISSION_KEYS[category as keyof typeof PERMISSION_KEYS]
  return (perms as readonly string[] | undefined)?.includes(action) ?? false
}

function isChecked(category: string, action: string): boolean {
  return props.modelValue.includes(`${category}.${action}`)
}

function toggle(category: string, action: string, value: boolean | null) {
  const key = `${category}.${action}`
  const newPerms = value
    ? [...props.modelValue, key]
    : props.modelValue.filter(p => p !== key)
  emit('update:modelValue', newPerms)
}
</script>

<style scoped>
.v-table {
  --v-table-header-height: 48px;
}
:deep(th) {
  font-size: 0.85rem;
  font-weight: 500;
  white-space: nowrap;
}
:deep(td) {
  height: 48px;
}
</style>
