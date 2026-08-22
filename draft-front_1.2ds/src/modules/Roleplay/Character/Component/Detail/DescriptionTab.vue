<script setup lang="ts">
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

defineProps<{
  version: CharacterVersion;
}>();
</script>

<template>
  <div class="description-tab d-flex flex-column ga-4">
    <v-card>
      <v-card-title>Краткое описание</v-card-title>
      <v-card-text class="text-body-1">{{ version.shortDescription ?? '—' }}</v-card-text>
    </v-card>

    <v-card>
      <v-card-title>Полное описание</v-card-title>
      <v-card-text class="text-body-1" style="white-space: pre-line">{{ version.fullDescription ?? '—' }}</v-card-text>
    </v-card>
  </div>
</template>
