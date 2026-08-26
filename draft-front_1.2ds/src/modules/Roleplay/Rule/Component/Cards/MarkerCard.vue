<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  rule: Rule;
}>();

const hint = computed(() => {
  if (props.rule.type === 'sense') {
    return 'Чувство — метка для даров «модификатор чувства». Значение на персонаже складывается из даров.';
  }
  if (props.rule.type === 'language') {
    return 'Язык — словарная статья домена навыка «Владение языком». Отдельной спеки нет.';
  }

  return 'Источник — метка, которой атрибутируются дары, сопротивления и помехи.';
});
</script>

<template>
  <v-card variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2 text-medium-emphasis">{{ hint }}</div>
    </v-card-text>
  </v-card>
</template>
