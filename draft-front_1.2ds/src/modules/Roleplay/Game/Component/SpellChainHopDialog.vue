<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GamePhpSpellChain } from '@/modules/Roleplay/Game/Dto/GamePhpSpellChain';
import CombatEntitySelect from '@/modules/Roleplay/Game/Component/CombatEntitySelect.vue';
import DimensionalNumber from '@/modules/Core/UI/Component/Input/DimensionalNumber.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { DimensionalNumber as DimensionalNumberValueClass } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { SpellChainPreviewService } from '@/modules/Roleplay/Game/Service/SpellChainPreviewService';

const props = defineProps<{
  open: boolean;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  initiativeKeys?: string[];
  exclude?: string[];
  lastKey?: CombatEntityKey | null;
  chain?: GamePhpSpellChain | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  hop: [key: CombatEntityKey, distance: DimensionalNumberValue];
  stop: [];
}>();

const preview = new SpellChainPreviewService();
const target = ref<CombatEntityKey | null>(null);
const distance = ref<DimensionalNumberValue>({ base: 0, size: 0 });
const error = ref('');

const isSameAsLast = computed(() => Boolean(target.value && props.lastKey && target.value === props.lastKey));
const distanceIpari = computed(() => new DimensionalNumberValueClass(distance.value).toNumber());
const damage = computed(() => (props.chain ? preview.damage(props.chain, distanceIpari.value) : null));

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
  distance.value = { base: 0, size: 0 };
  error.value = '';
}
</script>

<template>
  <v-dialog :model-value="open" max-width="460" persistent @update:model-value="emit('update:open', $event)">
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
        <DimensionalNumberInput v-model="distance" label="Дистанция, ипари" :min="0" class="mt-3" />
        <div v-if="chain" class="text-body-2 mt-3">
          <template v-if="damage">
            Урон прыжка: <DimensionalNumber :value="damage" />
            <span class="text-medium-emphasis">(до сопротивления цели)</span>
          </template>
          <template v-else>Прыжок не нанесёт повреждений: урон ниже минимума.</template>
        </div>
        <div v-if="chain" class="text-caption text-medium-emphasis mt-1">
          Минимум: <DimensionalNumber :value="chain.min" /> · остаток РУ каста: {{ chain.rating }}
        </div>
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
