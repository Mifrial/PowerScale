<script setup lang="ts">
defineProps<{
  label: string;
  value?: string;
}>();
</script>

<template>
  <div class="item-stat">
    <div class="text-caption text-medium-emphasis">{{ label }}</div>
    <div class="text-body-2 font-weight-medium">
      <slot>{{ value }}</slot>
    </div>
  </div>
</template>

<style scoped>
.item-stat {
  min-width: 0;
}
</style>
