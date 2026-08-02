<template>
  <span class="font-mono">{{ value != null ? Number(value).toLocaleString() : '—' }}</span>
</template>

<script setup lang="ts">
defineProps<{ value: unknown }>()
</script>
