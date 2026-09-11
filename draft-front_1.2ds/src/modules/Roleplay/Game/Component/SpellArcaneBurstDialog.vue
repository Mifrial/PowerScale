<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { SpellBurstTarget } from '@/modules/Roleplay/Game/Dto/Spell/SpellBurstTarget';
import CombatEntitySelect from '@/modules/Roleplay/Game/Component/CombatEntitySelect.vue';

const props = defineProps<{
  open: boolean;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  initiativeKeys?: string[];
  casterKey: CombatEntityKey | null;
  casterName: string;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  confirm: [targets: SpellBurstTarget[]];
}>();

const extraKey = ref<CombatEntityKey | null>(null);
const extraDistance = ref(0);
const extras = ref<SpellBurstTarget[]>([]);

const exclude = computed(() => {
  const keys = extras.value.map((entry) => entry.key);
  if (props.casterKey) {
    keys.push(props.casterKey);
  }

  return keys;
});

watch(
  () => props.open,
  (open) => {
    if (open) {
      extraKey.value = null;
      extraDistance.value = 0;
      extras.value = [];
    }
  },
);

function extraTitle(key: CombatEntityKey): string {
  const character = props.characters.find((entry) => `character:${entry.characterId}` === key);

  return character?.characterName ?? props.npcs.find((entry) => `npc:${entry.id}` === key)?.name ?? key;
}

function addExtra(): void {
  if (!extraKey.value) {
    return;
  }
  extras.value = [...extras.value, { key: extraKey.value, distanceIpari: Math.max(0, extraDistance.value) }];
  extraKey.value = null;
  extraDistance.value = 0;
}

function confirm(): void {
  const targets: SpellBurstTarget[] = [];
  if (props.casterKey) {
    targets.push({ key: props.casterKey, distanceIpari: 0 });
  }
  emit('confirm', [...targets, ...extras.value]);
}
</script>

<template>
  <v-dialog :model-value="open" max-width="460" persistent @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-body-1">Арканный взрыв</v-card-title>
      <v-card-text>
        <div v-if="casterKey" class="text-body-2 mb-3">{{ casterName }} — 0 ипари</div>
        <div v-for="entry in extras" :key="entry.key" class="text-caption mb-1">
          {{ extraTitle(entry.key) }} — {{ entry.distanceIpari }} ипари
        </div>
        <CombatEntitySelect
          v-model="extraKey"
          label="Ещё цель"
          :characters="characters"
          :npcs="npcs"
          :initiative-keys="initiativeKeys"
          :exclude="exclude"
        />
        <v-text-field
          v-model.number="extraDistance"
          type="number"
          min="0"
          label="Дистанция, ипари"
          density="compact"
          hide-details
          class="mt-2"
        />
        <v-btn class="mt-2" size="small" variant="text" :disabled="!extraKey" @click="addExtra">Добавить</v-btn>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" @click="confirm">Взрыв</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
