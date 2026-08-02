<template>
  <div class="d-flex flex-column align-center justify-center text-center not-found">
    <v-icon icon="mdi-compass-off-outline" size="64" color="primary" class="mb-4" />
    <h1 class="text-h4 font-weight-bold mb-2">404</h1>
    <p class="text-body-1 text-medium-emphasis mb-6">Страница не найдена</p>
    <div class="d-flex gap-2">
      <v-btn color="primary" :to="{ name: 'Home' }">На главную</v-btn>
      <v-btn variant="tonal" @click="goBack">Назад</v-btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push({ name: 'Home' })
}
</script>

<style scoped>
.not-found {
  min-height: 60vh;
}
</style>
