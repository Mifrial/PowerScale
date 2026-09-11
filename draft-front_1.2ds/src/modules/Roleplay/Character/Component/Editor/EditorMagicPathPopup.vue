<script setup lang="ts">
import type { EditorMagicPathView } from '@/modules/Roleplay/Character/Dto/Editor/EditorMagicPathView';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

defineProps<{
  path: EditorMagicPathView;
}>();
</script>

<template>
  <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
    <v-card-title class="text-body-1">
      <RuleLink :rule-code="path.pathCode">{{ path.pathName }}</RuleLink>
    </v-card-title>
    <v-card-text class="pt-0">
      <div class="text-caption text-medium-emphasis mb-1">Ограничения изучения</div>
      <div v-if="path.restrictionLabels.length">
        <div v-for="label in path.restrictionLabels" :key="label" class="text-body-2 py-1">{{ label }}</div>
      </div>
      <div v-else class="text-body-2 text-medium-emphasis py-1">Изучение этим путём ещё не открыто</div>
      <v-divider class="my-2" />
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Опыт пути</span>
        <span class="font-weight-medium">{{ path.experience }}</span>
      </div>
    </v-card-text>
  </v-card>
</template>
