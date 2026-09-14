<script setup lang="ts">
import { computed } from 'vue';
import CombatEntitySelect from '@/modules/Roleplay/Game/Component/CombatEntitySelect.vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { woundActionLaunchService } from '@/modules/Roleplay/Game/Service/Instance/woundActionLaunchService';

const props = defineProps<{
  actionCode: string | null;
  targetKey: CombatEntityKey | null;
  woundIndices: number[];
  actorVersion: CharacterVersion | null;
  targetVersion: CharacterVersion | null;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'update:targetKey': [value: CombatEntityKey | null];
  'update:woundIndices': [value: number[]];
}>();

const isBandage = computed(() => woundActionLaunchService.isBandage(props.actionCode));
const isSqueeze = computed(() => woundActionLaunchService.isSqueeze(props.actionCode));
const options = computed(() => {
  const states = props.targetVersion?.states ?? [];
  if (isBandage.value) return woundActionLaunchService.bandageOptions(states, props.actorVersion);

  return woundActionLaunchService.squeezeOptions(states);
});
const bandageIndex = computed(() => props.woundIndices[0] ?? null);

function onTarget(value: CombatEntityKey | string | null): void {
  emit('update:targetKey', typeof value === 'string' && value ? (value as CombatEntityKey) : null);
  emit('update:woundIndices', []);
}

function onBandageIndex(value: number | null): void {
  emit('update:woundIndices', value == null ? [] : [value]);
}

function onSqueezeIndices(value: number[] | null): void {
  emit('update:woundIndices', (value ?? []).slice(0, 2));
}
</script>

<template>
  <div v-if="isBandage || isSqueeze" class="wound-action-fields">
    <CombatEntitySelect
      class="mb-2"
      :model-value="targetKey"
      label="Цель"
      :characters="characters"
      :npcs="npcs"
      :disabled="disabled"
      @update:model-value="onTarget"
    />
    <v-select
      v-if="isBandage"
      :model-value="bandageIndex"
      class="mb-2"
      :items="options"
      item-title="title"
      item-value="value"
      label="Рана"
      density="compact"
      hide-details
      :disabled="disabled"
      @update:model-value="onBandageIndex"
    />
    <v-select
      v-else
      :model-value="woundIndices"
      class="mb-2"
      :items="options"
      item-title="title"
      item-value="value"
      label="Раны (до двух)"
      density="compact"
      hide-details
      multiple
      chips
      closable-chips
      :disabled="disabled"
      @update:model-value="onSqueezeIndices"
    />
  </div>
</template>
