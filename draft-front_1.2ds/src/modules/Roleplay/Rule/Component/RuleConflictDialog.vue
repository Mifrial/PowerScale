<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  open: boolean
  ruleName: string
  baseVersion: string | number | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: []
  cancel: []
}>()

const visible = computed({
  get: () => props.open,
  set: (v: boolean) => emit('update:open', v),
})
</script>

<template>
  <v-dialog v-model="visible" max-width="500" persistent>
    <v-card>
      <v-card-title>Замещение черновика</v-card-title>
      <v-card-text>
        <div class="text-body-2 mb-4">
          В черновике уже есть версия правила "{{ ruleName }}".
        </div>
        <div class="text-body-2 text-medium-emphasis">
          Её заменит версия v{{ baseVersion }}. Продолжить редактирование?
        </div>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" @click="emit('cancel')">Отмена</v-btn>
        <v-btn color="primary" variant="tonal" @click="emit('confirm')">
          Заменить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
