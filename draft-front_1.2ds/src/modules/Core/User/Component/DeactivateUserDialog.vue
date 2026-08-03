<script setup lang="ts">
import { ref, watch } from 'vue'
import type { User } from '@/modules/Core/User/Dto/User'

const props = defineProps<{
  user: User
  loading?: boolean
}>()

const open = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  confirm: [reason: string | undefined, until: string | undefined]
}>()

const reason = ref('')
const until = ref('')

watch(open, (value) => {
  if (!value) {
    reason.value = ''
    until.value = ''
  }
})

function confirm() {
  emit('confirm', reason.value || undefined, until.value || undefined)
}
</script>

<template>
  <v-dialog v-model="open" max-width="460">
    <v-card>
      <v-card-title>Отключить пользователя</v-card-title>
      <v-card-subtitle class="text-body-2">
        Пользователь «{{ props.user.name }}» будет отключён
      </v-card-subtitle>
      <v-card-text>
        <v-textarea
          v-model="reason"
          label="Причина"
          placeholder="Нарушение правил, неактивность…"
          rows="2"
          variant="outlined"
          class="mb-3"
          hide-details
        />
        <v-text-field
          v-model="until"
          label="Отключён до (опционально)"
          type="date"
          variant="outlined"
          hide-details
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
        <v-btn color="error" :loading="loading" @click="confirm">Отключить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
