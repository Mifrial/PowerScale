<script setup lang="ts">
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';

defineProps<{
  ruleCode: string | null | undefined;
}>();

const { openRule } = useRuleDetailSlider();
</script>

<template>
  <a href="#" class="text-decoration-none cursor-pointer" @click.prevent="openRule(ruleCode)">
    <slot />
  </a>
</template>
