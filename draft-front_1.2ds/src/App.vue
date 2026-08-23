<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue';

const error = ref<Error | null>(null);

onErrorCaptured((err: Error) => {
  console.error('Component error:', err);
  if (!error.value) {
    queueMicrotask(() => {
      error.value = err;
    });
  }

  return false;
});

function reset() {
  error.value = null;
  window.location.reload();
}
</script>

<template>
  <v-app>
    <!-- Не снимаем router-view при ошибке: v-else размонтирует дерево посреди patch
         и даёт каскад null vnode / recursive updates в VApp. -->
    <router-view />
    <div v-if="error" class="error-fallback pa-8 text-center">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <h2 class="text-h5 mb-2">Произошла ошибка</h2>
      <p class="text-body-1 text-medium-emphasis mb-4">{{ error.message }}</p>
      <v-btn color="primary" @click="reset">Перезагрузить страницу</v-btn>
    </div>
  </v-app>
</template>

<style scoped>
.error-fallback {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background: rgb(var(--v-theme-surface));
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
</style>
