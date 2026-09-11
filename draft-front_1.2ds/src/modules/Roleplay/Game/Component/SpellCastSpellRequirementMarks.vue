<script setup lang="ts">
defineProps<{
  powerLabel: string;
  controlLabel: string;
}>();
</script>

<template>
  <span class="d-flex align-center ga-2 text-medium-emphasis flex-shrink-0">
    <span class="d-flex align-center ga-1">
      <v-icon size="16">mdi-flash</v-icon>
      {{ powerLabel }}
    </span>
    <span class="d-flex align-center ga-1">
      <v-icon size="16">mdi-head-cog-outline</v-icon>
      {{ controlLabel }}
    </span>
  </span>
</template>
