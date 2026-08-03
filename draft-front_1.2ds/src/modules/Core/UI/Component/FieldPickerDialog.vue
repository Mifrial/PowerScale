<script setup lang="ts">
import { ref } from 'vue'
import type { PickerItem } from '@/modules/Core/UI/Dto/PickerItem'

export type { PickerItem }

const props = defineProps<{
  modelValue: boolean
  title: string
  description?: string
  items: PickerItem[]
}>()

const emit = defineEmits<{
  'update:modelValue': [v: boolean]
  apply: [items: PickerItem[]]
}>()

const localItems = ref<PickerItem[]>(props.items.map(i => ({ ...i })))

function toggleVisible(i: number) {
  localItems.value[i].visible = !localItems.value[i].visible
}

function onCancel() {
  emit('update:modelValue', false)
}

function onApply() {
  emit('apply', localItems.value.map(i => ({ key: i.key, label: i.label, visible: i.visible })))
  emit('update:modelValue', false)
}
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    max-width="420"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card rounded="lg">
      <v-card-title class="text-h6 pa-4 pb-0">
        {{ title }}
      </v-card-title>

      <v-card-text class="pa-4">
        <p v-if="description" class="text-caption text-medium-emphasis mb-3">
          {{ description }}
        </p>

        <v-list lines="one" density="compact" class="picker-list">
          <v-list-item v-for="(item, i) in localItems" :key="item.key">
            <template #title>
              <v-checkbox
                :model-value="item.visible"
                :label="item.label"
                hide-details
                density="compact"
                class="picker-checkbox"
                @update:model-value="toggleVisible(i)"
              />
            </template>
          </v-list-item>
        </v-list>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4 pt-2">
        <v-btn variant="text" color="medium-emphasis" @click="onCancel">
          Отмена
        </v-btn>
        <v-spacer />
        <v-btn variant="tonal" color="primary" @click="onApply">
          Применить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.picker-list {
  max-height: 400px;
  overflow-y: auto;
}
.picker-checkbox {
  pointer-events: none;
}
.picker-checkbox :deep(.v-label) {
  pointer-events: auto;
  cursor: pointer;
}
</style>
