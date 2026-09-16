<script setup lang="ts">
import { computed } from 'vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CombatEntitySelectEntry } from '@/modules/Roleplay/Game/Dto/CombatEntitySelectEntry';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { combatEntitySelectService } from '@/modules/Roleplay/Game/Service/Instance/combatEntitySelectService';

const props = withDefaults(
  defineProps<{
    modelValue: CombatEntityKey | string | null;
    label: string;
    characters: GameCharacterMembership[];
    npcs: GameNpc[];
    initiativeKeys?: string[];
    exclude?: string[];
    leading?: CombatEntitySelectEntry[];
    disabled?: boolean;
  }>(),
  {
    initiativeKeys: () => [],
    exclude: () => [],
    leading: () => [],
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: CombatEntityKey | string | null];
}>();

const items = computed(() =>
  combatEntitySelectService.items({
    characters: props.characters,
    npcs: props.npcs,
    initiativeKeys: props.initiativeKeys,
    exclude: props.exclude,
    leading: props.leading,
  }),
);
</script>

<template>
  <v-autocomplete
    :model-value="modelValue"
    :items="items"
    item-title="title"
    item-value="value"
    :label="label"
    density="compact"
    hide-details
    :auto-select-first="!disabled"
    :disabled="disabled"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
