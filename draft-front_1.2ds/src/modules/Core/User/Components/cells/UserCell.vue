<template>
  <div class="d-flex align-center ga-2">
    <v-avatar size="28" color="primary" variant="tonal">
      <v-icon size="16" icon="mdi-account" />
    </v-avatar>
    <div>
      <div class="font-weight-medium text-body-2">{{ value?.name ?? '—' }}</div>
      <div v-if="value?.login" class="text-caption text-medium-emphasis">@{{ value.login }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ value: any }>()
</script>
