<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { GameLoot, GameLootDistribution } from '@/modules/Roleplay/Game/Dto/GameLoot';
import type { GameLootRecipientType } from '@/modules/Roleplay/Game/Enum/GameLootRecipientType';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';

interface RecipientOption {
  key: string;
  type: GameLootRecipientType;
  characterId?: number;
  npcId?: number;
  label: string;
}

interface AllocationDraft {
  key: number;
  type: GameLootRecipientType;
  characterId?: number;
  npcId?: number;
  amount: number | null;
}

const props = defineProps<{
  loot: GameLoot | null;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
  distribute: [distribution: GameLootDistribution[]];
}>();

const itemRecipient = ref<RecipientOption | null>(null);
const allocations = ref<AllocationDraft[]>([]);
const draftKind = ref<GameLootRecipientType>('character');
const draftRecipientKey = ref<string | null>(null);

let nextAllocationKey = 1;

const isMoney = computed(() => props.loot?.moneyAmount !== null && props.loot?.moneyAmount !== undefined);

/** Доступные персонажи-получатели — члены игры (любой статус: персонаж уже подан в игру). */
const gameCharacters = computed(() => props.characters);

const recipientOptions = computed<RecipientOption[]>(() => {
  const loot = props.loot;
  if (!loot) return [];
  const interestedIds = loot.interestedUserIds;
  const options: RecipientOption[] = [];
  const interested = gameCharacters.value.filter((character) => interestedIds.includes(character.characterOwnerId));
  const rest = gameCharacters.value.filter((character) => !interestedIds.includes(character.characterOwnerId));
  for (const character of interested) {
    options.push({
      key: `c:${character.characterId}`,
      type: 'character',
      characterId: character.characterId,
      label: `${character.characterName} (интерес)`,
    });
  }
  for (const character of rest) {
    options.push({
      key: `c:${character.characterId}`,
      type: 'character',
      characterId: character.characterId,
      label: character.characterName,
    });
  }
  for (const npc of props.npcs)
    options.push({ key: `n:${npc.id}`, type: 'npc', npcId: npc.id, label: `НПС: ${npc.name}` });
  options.push({ key: 'nowhere', type: 'nowhere', label: 'Вникуда' });

  return options;
});

/** Варианты получателя-персонажа (для выбора доли). */
const characterOptions = computed<RecipientOption[]>(() =>
  recipientOptions.value.filter((option) => option.type === 'character'),
);

const npcOptions = computed<RecipientOption[]>(() => recipientOptions.value.filter((option) => option.type === 'npc'));

const totalAmount = computed(() => props.loot?.moneyAmount ?? 0);
const allocated = computed(() => allocations.value.reduce((sum, allocation) => sum + (allocation.amount ?? 0), 0));
const remaining = computed(() => totalAmount.value - allocated.value);

const canDistributeItem = computed(() => itemRecipient.value !== null);

const canDistributeMoney = computed(() => {
  if (allocations.value.length === 0) return false;

  return allocations.value.every((allocation) => allocation.amount !== null && allocation.amount > 0);
});

function characterName(characterId: number): string {
  return props.characters.find((character) => character.characterId === characterId)?.characterName ?? 'Персонаж';
}

function allocationLabel(allocation: AllocationDraft): string {
  if (allocation.type === 'character') return characterName(allocation.characterId ?? 0);
  if (allocation.type === 'npc') return `НПС: ${props.npcs.find((n) => n.id === allocation.npcId)?.name ?? '?'}`;

  return 'Вникуда';
}

function reset(): void {
  const loot = props.loot;
  itemRecipient.value = recipientOptions.value.find((option) => option.type !== 'nowhere') ?? null;
  allocations.value = [];
  draftKind.value = 'character';
  draftRecipientKey.value = characterOptions.value[0]?.key ?? null;
  if (isMoney.value && loot) {
    splitEqually();
  }
}

/** Поровну между проявившими интерес (по их персонажам в игре; остаток — первому). */
function splitEqually(): void {
  const loot = props.loot;
  if (!loot || isMoney.value === false) return;
  const interested = loot.interestedUserIds;
  if (interested.length === 0) {
    allocations.value = [];
    draftRecipientKey.value = characterOptions.value[0]?.key ?? null;

    return;
  }
  const base = Math.floor(totalAmount.value / interested.length);
  const remainder = totalAmount.value % interested.length;
  allocations.value = interested
    .map((userId, index) => {
      const character = gameCharacters.value.find((candidate) => candidate.characterOwnerId === userId);
      if (!character) return null;
      const allocation: AllocationDraft = {
        key: nextAllocationKey++,
        type: 'character',
        characterId: character.characterId,
        amount: base + (index === 0 ? remainder : 0),
      };

      return allocation;
    })
    .filter((allocation): allocation is AllocationDraft => allocation !== null);
}

function addAllocation(): void {
  const kind = draftKind.value;
  const key = draftRecipientKey.value;
  const option = recipientOptions.value.find((o) => o.key === key);
  const allocation: AllocationDraft = { key: nextAllocationKey++, type: kind, amount: null };
  if (kind === 'character' && option?.characterId !== undefined) allocation.characterId = option.characterId;
  if (kind === 'npc' && option?.npcId !== undefined) allocation.npcId = option.npcId;
  allocations.value.push(allocation);
}

function removeAllocation(key: number): void {
  allocations.value = allocations.value.filter((allocation) => allocation.key !== key);
}

function distribute(): void {
  if (!props.loot) return;
  if (isMoney.value) {
    const result: GameLootDistribution[] = allocations.value.map((allocation) => ({
      type: allocation.type,
      ...(allocation.characterId !== undefined ? { characterId: allocation.characterId } : {}),
      ...(allocation.npcId !== undefined ? { npcId: allocation.npcId } : {}),
      amount: allocation.amount,
    }));
    if (remaining.value > 0) result.push({ type: 'nowhere', amount: remaining.value });
    emit('distribute', result);
    open.value = false;

    return;
  }
  const recipient = itemRecipient.value;
  if (!recipient) return;
  const distribution: GameLootDistribution[] = [
    {
      type: recipient.type,
      ...(recipient.characterId !== undefined ? { characterId: recipient.characterId } : {}),
      ...(recipient.npcId !== undefined ? { npcId: recipient.npcId } : {}),
    },
  ];
  emit('distribute', distribution);
  open.value = false;
}

watch(open, (value) => {
  if (value) reset();
});
</script>

<template>
  <v-dialog v-model="open" max-width="560">
    <v-card>
      <v-card-title class="text-subtitle-1">Раздача добычи</v-card-title>
      <v-card-text class="d-flex flex-column ga-2">
        <!-- Предмет: один получатель -->
        <template v-if="loot && !isMoney">
          <div class="text-body-2 text-medium-emphasis">
            Выберите, кому достанется предмет. Заинтересованные игроки отмечены «(интерес)».
          </div>
          <v-radio-group v-model="itemRecipient" density="compact">
            <v-radio
              v-for="option in recipientOptions"
              :key="option.key"
              :value="option"
              :label="option.label"
              density="compact"
            />
          </v-radio-group>
        </template>

        <!-- Деньги: доли -->
        <template v-else-if="loot">
          <div class="d-flex align-center ga-2">
            <div class="text-body-2 text-medium-emphasis">Всего: {{ totalAmount }} гм</div>
            <v-spacer />
            <v-chip size="small" variant="tonal" :color="remaining >= 0 ? 'info' : 'error'">
              Остаток: {{ remaining }} гм{{ remaining > 0 ? ' → вникуда' : '' }}
            </v-chip>
          </div>

          <v-btn
            variant="tonal"
            size="small"
            color="primary"
            prepend-icon="mdi-scale-balance"
            :disabled="loot.interestedUserIds.length === 0"
            @click="splitEqually"
          >
            Поровну между заинтересованными
          </v-btn>

          <div v-if="allocations.length > 0" class="d-flex flex-column ga-2">
            <div
              v-for="allocation in allocations"
              :key="allocation.key"
              class="d-flex align-center ga-2 allocation-row"
            >
              <span class="text-body-2 allocation-label">{{ allocationLabel(allocation) }}</span>
              <v-text-field
                v-model.number="allocation.amount"
                label="гм"
                type="number"
                min="1"
                density="compact"
                hide-details
                class="allocation-amount"
              />
              <v-btn icon variant="text" size="x-small" title="Убрать долю" @click="removeAllocation(allocation.key)">
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </div>
          </div>

          <div class="d-flex align-center ga-2">
            <v-select
              v-model="draftKind"
              :items="[
                { value: 'character', title: 'Персонаж' },
                { value: 'npc', title: 'НПС' },
                { value: 'nowhere', title: 'Вникуда' },
              ]"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
              class="allocation-kind"
            />
            <v-select
              v-if="draftKind !== 'nowhere'"
              v-model="draftRecipientKey"
              :items="draftKind === 'character' ? characterOptions : npcOptions"
              item-title="label"
              item-value="key"
              density="compact"
              hide-details
              class="allocation-recipient"
            />
            <v-btn variant="tonal" size="small" color="primary" prepend-icon="mdi-plus" @click="addAllocation">
              Доля
            </v-btn>
          </div>
        </template>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn
          color="primary"
          variant="tonal"
          :disabled="isMoney ? !canDistributeMoney : !canDistributeItem"
          @click="distribute"
        >
          Раздать
        </v-btn>
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.allocation-row {
  border: 1px solid rgb(var(--v-theme-outline));
  border-radius: 6px;
  padding: 6px 8px;
}
.allocation-label {
  min-width: 0;
  flex: 1 1 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.allocation-amount {
  width: 110px;
  flex: 0 0 110px;
}
.allocation-kind {
  width: 120px;
  flex: 0 0 120px;
}
.allocation-recipient {
  width: 200px;
  flex: 0 0 200px;
}
</style>
