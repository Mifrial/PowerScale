<script setup lang="ts">
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

defineProps<{
  rule: Rule;
}>();
</script>

<template>
  <v-card v-if="rule.code" variant="tonal" class="mb-4">
    <v-card-text>
      <div class="text-body-2">
        Системное имя: <strong>{{ rule.code }}</strong>
      </div>
      <div class="text-body-2 text-medium-emphasis mt-1">
        Способности ссылаются на очки в своих зонах. Базовые значения не хранятся — бюджеты задаются игрой.
      </div>
    </v-card-text>
  </v-card>
</template>
