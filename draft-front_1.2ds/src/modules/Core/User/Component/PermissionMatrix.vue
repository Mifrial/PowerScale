<script setup lang="ts">
import { computed } from 'vue'
import type { PermissionCategory } from '@/modules/Core/User/Interface/IPermissionRegistry'
import { getPermissionCategories } from '@/modules/Core/User/init'

const props = defineProps<{
  modelValue: string[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

const categories: PermissionCategory[] = getPermissionCategories()

const allActions = computed(() => {
  const map = new Map<string, string>()
  for (const category of categories) {
    for (const action of category.actions) map.set(action.key, action.label)
  }
  return Array.from(map, ([key, label]) => ({ key, label }))
})

function hasPermission(category: PermissionCategory, action: string): boolean {
  return category.actions.some(a => a.key === action)
}

function isChecked(category: PermissionCategory, action: string): boolean {
  return props.modelValue.includes(`${category.key}.${action}`)
}

function toggle(category: PermissionCategory, action: string, value: boolean | null) {
  const key = `${category.key}.${action}`
  const newPerms = value
    ? [...props.modelValue, key]
    : props.modelValue.filter(p => p !== key)
  emit('update:modelValue', newPerms)
}
</script>

<template>
  <v-card variant="outlined">
    <v-card-text class="pa-0">
      <v-table density="compact">
        <thead>
          <tr>
            <th class="text-left">Категория</th>
            <th v-for="action in allActions" :key="action.key" class="text-center">
              {{ action.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="category in categories" :key="category.key">
            <td class="font-weight-medium">{{ category.label }}</td>
            <td v-for="action in allActions" :key="action.key" class="text-center">
              <v-checkbox
                v-if="hasPermission(category, action.key)"
                :model-value="isChecked(category, action.key)"
                :disabled="disabled"
                density="compact"
                hide-details
                class="d-flex justify-center"
                @update:model-value="toggle(category, action.key, $event)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>
  </v-card>
</template>

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
