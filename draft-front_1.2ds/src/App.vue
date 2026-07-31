<template>
  <v-app>
    <div v-if="error" class="error-fallback pa-8 text-center">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <h2 class="text-h5 mb-2">Произошла ошибка</h2>
      <p class="text-body-1 text-medium-emphasis mb-4">{{ error.message }}</p>
      <v-btn color="primary" @click="reset">Перезагрузить страницу</v-btn>
    </div>
    <router-view v-else />
  </v-app>
</template>

<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue'

const error = ref<Error | null>(null)

onErrorCaptured((err: Error) => {
  error.value = err
  console.error('Component error:', err)
  return false
})

function reset() {
  error.value = null
  window.location.reload()
}
</script>

<style scoped>
.error-fallback {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
</style>
