<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import CombatEntitySelect from '@/modules/Roleplay/Game/Component/CombatEntitySelect.vue';

const props = defineProps<{
  open: boolean;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  initiativeKeys?: string[];
  exclude?: string[];
  lastKey?: CombatEntityKey | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  hop: [key: CombatEntityKey, distanceIpari: number];
  stop: [];
}>();

const target = ref<CombatEntityKey | null>(null);
const distance = ref(0);
const error = ref('');

const isSameAsLast = computed(() => Boolean(target.value && props.lastKey && target.value === props.lastKey));

watch(
  () => props.open,
  (open) => {
    if (open) {
      error.value = '';
    }
  },
);

function confirm(): void {
  if (!target.value) {
    return;
  }
  if (isSameAsLast.value) {
    error.value = 'Нельзя бить в ту же цель подряд';

    return;
  }
  emit('hop', target.value, distance.value);
  target.value = null;
  distance.value = 0;
  error.value = '';
}
</script>

<template>
  <v-dialog :model-value="open" max-width="420" persistent @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-body-1">Цепная молния</v-card-title>
      <v-card-text>
        <CombatEntitySelect
          v-model="target"
          label="Следующая цель"
          :characters="characters"
          :npcs="npcs"
          :initiative-keys="initiativeKeys"
          :exclude="exclude"
        />
        <v-text-field
          v-model.number="distance"
          type="number"
          min="0"
          label="Дистанция, ипари"
          density="compact"
          hide-details
          class="mt-2"
        />
        <div v-if="error || isSameAsLast" class="text-caption text-error mt-2">
          {{ error || 'Нельзя бить в ту же цель подряд' }}
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="emit('stop')">Обрыв</v-btn>
        <v-btn color="primary" :disabled="!target || isSameAsLast" @click="confirm">Удар</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
