<script setup lang="ts">
import { computed } from 'vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { SpellBurstTarget } from '@/modules/Roleplay/Game/Dto/Spell/SpellBurstTarget';
import { combatEntitySelectService } from '@/modules/Roleplay/Game/Service/Instance/combatEntitySelectService';

/**
 * Временный выбор целей взрыва до батлграунда: участники боя и дистанции.
 * Кастер всегда входит в список с дистанцией 0.
 */
const props = defineProps<{
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  initiativeKeys?: string[];
  casterKey: CombatEntityKey | null;
  casterName: string;
  /** Радиус взрыва в ипари. */
  radius: number;
  /** Кастер и отмеченные цели. */
  modelValue: SpellBurstTarget[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: SpellBurstTarget[]];
}>();

const extras = computed(() => props.modelValue.filter((target) => target.key !== props.casterKey && target.key));
const pickedKeys = computed(() => extras.value.map((target) => target.key));
const distances = computed<Record<string, number>>(() =>
  Object.fromEntries(extras.value.map((target) => [target.key, target.distanceIpari])),
);
const options = computed(() => {
  const initiative = props.initiativeKeys ?? [];

  return combatEntitySelectService
    .items({
      characters: props.characters,
      npcs: props.npcs,
      initiativeKeys: initiative,
      exclude: props.casterKey ? [props.casterKey] : [],
      participantsOnly: initiative.length > 0,
    })
    .filter((item) => typeof item.value === 'string' && item.value !== '');
});

/** Дистанция в пределах радиуса. */
function clampDistance(value: unknown): number {
  const raw = Math.trunc(Number(value ?? 0));
  if (!Number.isFinite(raw)) {
    return 0;
  }

  return Math.min(Math.max(0, raw), Math.max(0, props.radius));
}

/** Отправляет кастера и отмеченные цели. */
function emitExtras(rows: SpellBurstTarget[]): void {
  const caster = props.casterKey ? [{ key: props.casterKey, distanceIpari: 0 }] : [];
  emit('update:modelValue', [...caster, ...rows]);
}

/** Отмечает или снимает цели. */
function updateKeys(keys: CombatEntityKey[]): void {
  emitExtras(keys.map((key) => ({ key, distanceIpari: clampDistance(distances.value[key]) })));
}

/** Меняет дистанцию цели. */
function updateDistance(key: CombatEntityKey, value: string | number | null): void {
  emitExtras(
    extras.value.map((target) => (target.key === key ? { key, distanceIpari: clampDistance(value) } : target)),
  );
}

/** Подпись цели. */
function titleOf(key: CombatEntityKey): string {
  return options.value.find((item) => item.value === key)?.title ?? key;
}
</script>

<template>
  <div>
    <div class="text-body-2 mb-2">
      Кого задел взрыв. Кастер получает его всегда, остальных отметьте сами — это временная заглушка до батлграунда.
    </div>
    <div v-if="casterKey" class="text-caption mb-2">{{ casterName || casterKey }} — кастер, 0 ипари</div>
    <v-autocomplete
      :model-value="pickedKeys"
      :items="options"
      item-title="title"
      item-value="value"
      label="Ещё под взрыв"
      density="compact"
      multiple
      chips
      hide-details
      clearable
      @update:model-value="updateKeys"
    />
    <div v-for="key in pickedKeys" :key="key" class="d-flex align-center ga-2 mt-1">
      <span class="text-body-2 flex-grow-1">{{ titleOf(key) }}</span>
      <v-text-field
        :model-value="distances[key] ?? 0"
        type="number"
        :min="0"
        :max="radius"
        label="ипари"
        density="compact"
        hide-details
        style="max-width: 110px"
        @update:model-value="updateDistance(key, $event)"
      />
    </div>
    <div class="text-caption text-medium-emphasis mt-2">Если никого не отметить, взрыв заденет только кастера.</div>
  </div>
</template>
