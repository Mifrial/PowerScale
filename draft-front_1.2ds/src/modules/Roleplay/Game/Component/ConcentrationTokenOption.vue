<script setup lang="ts">
import { computed, watch } from 'vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { concentrationTokenService } from '@/modules/Roleplay/Game/Service/Instance/concentrationTokenService';

const props = defineProps<{
  modelValue: number;
  version: CharacterVersion | null;
  overlay: GameCombatOverlay | null;
  rules: Rule[];
  checkCode: string;
  characteristicCode?: string | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: number];
}>();

const maxSpend = computed(() =>
  concentrationTokenService.maxSpend(
    props.version,
    props.overlay,
    props.rules,
    props.checkCode,
    props.characteristicCode,
  ),
);

const remaining = computed(() =>
  props.version ? concentrationTokenService.tokenCurrent(props.version, props.overlay) : 0,
);

watch(
  maxSpend,
  (max) => {
    const next = Math.min(props.modelValue, max);
    if (next !== props.modelValue) emit('update:modelValue', next);
  },
  { immediate: true },
);
</script>

<template>
  <ClampedNumberField
    v-if="maxSpend > 0"
    :model-value="modelValue"
    :min="0"
    :max="maxSpend"
    label="Жетоны концентрации"
    :hint="`Осталось: ${remaining}`"
    persistent-hint
    density="compact"
    hide-details="auto"
    class="mt-2"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
