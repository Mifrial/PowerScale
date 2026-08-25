<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { rollInitiative, orderInitiative, rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import type { InitiativeRollMethod } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import { initiativeCharacteristics } from '@/modules/Roleplay/Game/Utils/initiativeCharacteristic';
import type { InitiativeCharacteristicView } from '@/modules/Roleplay/Game/Utils/initiativeCharacteristic';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ROLL_DICE_COUNT_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MAX';
import { ROLL_DICE_COUNT_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MIN';
import { ROLL_EFFICIENCY_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MAX';
import { ROLL_EFFICIENCY_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MIN';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameInitiativeParticipant } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

/**
 * Окно проверки на инициативу (ТР §8 «Чат игры»): выбор участников (персонажи + НПС),
 * способ определения значения (характеристика / свободный бросок / фиксированное значение),
 * «Бросить инициативу» → порядок по убыванию, тай-брейк случайно; результаты — в чат,
 * шкала активируется на первом по инициативе.
 */
const props = defineProps<{
  open: boolean;
  gameId: number;
  spaceId: number;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  /** Механики ревизии (для бросков инициативы через RollEngine). */
  mechanics: Mechanic[];
  chatId: number | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  saved: [];
}>();

const chatStore = useChatStore();
const combatThread = useCombatChatThread(props.gameId);

const DEFAULT_CHARACTERISTIC_CODE = 'perception';

interface DialogEntry {
  participant: GameInitiativeParticipant;
  version: CharacterVersion | null;
  method: InitiativeRollMethod;
  characteristicCode: string;
  modifier: number;
  /** characteristic/free: преимущества (>0) / помехи (<0). */
  adv: number;
  fixedValue: number | null;
  /** Свободный бросок: значения вводит ГМ (дефолты — из правила «Бросок» ревизии; грани — d6). */
  freeDiceCount: number;
  freeEfficiency: number;
  characteristicValues: Map<string, InitiativeCharacteristicView>;
  characteristicsReady: boolean;
}

interface CandidateOption {
  participant: GameInitiativeParticipant;
  version: CharacterVersion | null;
}

const entries = ref<DialogEntry[]>([]);
const selectedIds = ref<string[]>([]);
const rolling = ref(false);
const error = ref<string | null>(null);

const poolDefaults = computed(() => rollPoolDefaults(props.rules));

const candidates = computed<CandidateOption[]>(() => {
  const characters: CandidateOption[] = props.characters
    .filter((membership) => membership.membershipStatus === 'approved')
    .map((membership) => ({
      participant: {
        id: `character:${membership.characterId}`,
        name: membership.characterName,
        kind: 'character',
        entityId: membership.characterId,
      },
      version: membership.activeVersion,
    }));
  const npcs: CandidateOption[] = props.npcs
    .filter((npc) => npc.status === 'active')
    .map((npc) => ({
      participant: { id: `npc:${npc.id}`, name: npc.name, kind: 'npc', entityId: npc.id },
      version: npc.version,
    }));

  return [...characters, ...npcs];
});

const candidateSelectItems = computed(() =>
  candidates.value.map((candidate) => ({ value: candidate.participant.id, title: candidate.participant.name })),
);

watch(
  () => props.open,
  (open) => {
    if (open) reset();
  },
);

/** По умолчанию — все approved-персонажи; НПС добавляют вручную. */
function defaultSelectedIds(): string[] {
  return candidates.value
    .filter((candidate) => candidate.participant.kind === 'character')
    .map((candidate) => candidate.participant.id);
}

function reset(): void {
  entries.value = [];
  selectedIds.value = defaultSelectedIds();
  rolling.value = false;
  error.value = null;
}

function candidateOf(id: string): CandidateOption | null {
  return candidates.value.find((candidate) => candidate.participant.id === id) ?? null;
}

function addEntry(id: string): void {
  const candidate = candidateOf(id);
  if (!candidate || entries.value.some((entry) => entry.participant.id === id)) return;
  const hasSheet = candidate.version !== null;
  entries.value.push({
    participant: { ...candidate.participant },
    version: candidate.version,
    method: hasSheet ? 'characteristic' : 'free',
    characteristicCode: DEFAULT_CHARACTERISTIC_CODE,
    modifier: 0,
    adv: 0,
    fixedValue: null,
    freeDiceCount: poolDefaults.value.freeDiceCount,
    freeEfficiency: poolDefaults.value.efficiency,
    characteristicValues: new Map(),
    characteristicsReady: !hasSheet,
  });
  if (hasSheet) void ensureCharacteristics(entries.value[entries.value.length - 1]);
}

function removeEntry(id: string): void {
  selectedIds.value = selectedIds.value.filter((value) => value !== id);
}

// Синхронизация: мультиселект меняет selectedIds — блоки конфига строятся из entries.
watch(selectedIds, (ids) => {
  const idSet = new Set(ids);
  const kept = entries.value.filter((entry) => idSet.has(entry.participant.id));
  if (kept.length !== entries.value.length) entries.value = kept;
  for (const id of ids) {
    if (!kept.some((entry) => entry.participant.id === id)) addEntry(id);
  }
});

async function ensureCharacteristics(entry: DialogEntry): Promise<void> {
  if (!entry.version || entry.characteristicsReady) return;
  try {
    const map = await initiativeCharacteristics(entry.version, props.spaceId, props.rules);
    entry.characteristicValues = map;
    if (!map.has(entry.characteristicCode)) {
      entry.characteristicCode = map.keys().next().value ?? '';
    }
  } catch {
    // Сборка недоступна — характеристика не выбрана; ГМ может использовать свободный бросок/фикс.
    entry.characteristicValues = new Map();
  } finally {
    entry.characteristicsReady = true;
  }
}

function valueLabel(view: InitiativeCharacteristicView | undefined): string {
  return view ? new DimensionalNumber(view.value).toString() : '';
}

function characteristicItems(entry: DialogEntry): { code: string; name: string; valueLabel: string }[] {
  return [...entry.characteristicValues.values()].map((view) => ({
    code: view.code,
    name: view.name,
    valueLabel: valueLabel(view),
  }));
}

/** Методы для участника: характеристика доступна только при заполненном листе (version). */
function methodItems(entry: DialogEntry): { value: InitiativeRollMethod; title: string }[] {
  const items: { value: InitiativeRollMethod; title: string }[] = [
    { value: 'free', title: 'Свободный бросок' },
    { value: 'fixed', title: 'Фиксированное значение' },
  ];
  if (entry.version !== null) items.unshift({ value: 'characteristic', title: 'Характеристика' });

  return items;
}

async function roll(): Promise<void> {
  if (entries.value.length === 0) {
    error.value = 'Выберите хотя бы одного участника';
    rolling.value = false;

    return;
  }
  for (const entry of entries.value) {
    if (entry.method === 'characteristic' && !entry.characteristicValues.get(entry.characteristicCode)) {
      error.value = `Нет значения «${entry.characteristicCode}» у ${entry.participant.name}`;
      rolling.value = false;

      return;
    }
    if (entry.method === 'fixed' && entry.fixedValue === null) {
      error.value = `Укажите фиксированное значение для ${entry.participant.name}`;
      rolling.value = false;

      return;
    }
    if (entry.method === 'free' && (entry.freeDiceCount < 1 || entry.freeEfficiency < 1)) {
      error.value = `Проверьте параметры свободного броска для ${entry.participant.name}`;
      rolling.value = false;

      return;
    }
  }

  rolling.value = true;
  error.value = null;
  try {
    const rollEntries = entries.value.map((entry) => ({
      participant: entry.participant,
      method: entry.method,
      characteristicCode: entry.method === 'characteristic' ? entry.characteristicCode : undefined,
      characteristicValue:
        entry.method === 'characteristic' ? entry.characteristicValues.get(entry.characteristicCode)?.value : undefined,
      modifier: entry.method === 'characteristic' ? entry.modifier : undefined,
      adv: entry.method === 'free' ? entry.adv : entry.method === 'characteristic' ? entry.adv : undefined,
      fixedValue: entry.method === 'fixed' ? (entry.fixedValue ?? undefined) : undefined,
      dieFaces: poolDefaults.value.dieFaces,
      efficiency: entry.method === 'free' ? entry.freeEfficiency : poolDefaults.value.efficiency,
      freeDiceCount: entry.method === 'free' ? entry.freeDiceCount : poolDefaults.value.freeDiceCount,
    }));
    const results = rollInitiative(rollEntries, undefined, props.rules, props.mechanics);
    const ordered = orderInitiative(results);

    await getGameApi().saveInitiative(props.gameId, {
      gameId: props.gameId,
      active: true,
      participants: ordered,
      activeIndex: 0,
      round: 1,
      updatedAt: '',
    });

    if (props.chatId !== null) {
      const round = combatThread.beginRound();
      await chatStore.postSystemMessage(`Новый раунд: 1`, props.chatId, 'highlighted', round);
      const rolled = results.filter((result) => result.result !== null);
      if (rolled.length > 0) {
        await chatStore.sendMessage(
          'Проверка на инициативу',
          rolled.map((result) => ({ type: ROLL_ATTACHMENT_TYPE, payload: result.result })),
          props.chatId,
          { kind: 'gm' },
          undefined,
          round,
        );
      }
      const first = ordered[0];
      if (first) {
        const turn = combatThread.beginTurn();
        await chatStore.postSystemMessage(`Ходит ${first.name}`, props.chatId, 'default', turn);
      }
    }

    emit('saved');
    emit('update:open', false);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось провести проверку на инициативу';
  } finally {
    rolling.value = false;
  }
}
</script>

<template>
  <v-dialog :model-value="open" max-width="720" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-subtitle-1 d-flex align-center">
        <v-icon icon="mdi-format-list-numbered" class="mr-2" />
        Проверка на инициативу
      </v-card-title>
      <v-card-text>
        <v-select
          v-model="selectedIds"
          :items="candidateSelectItems"
          item-title="title"
          item-value="value"
          multiple
          chips
          closable-chips
          density="compact"
          variant="outlined"
          hide-details
          label="Участники"
          class="mb-3"
        />

        <div v-for="entry in entries" :key="entry.participant.id" class="initiative-dialog-entry">
          <div class="d-flex align-center ga-2 mb-1">
            <span class="text-body-2 font-weight-medium">{{ entry.participant.name }}</span>
            <v-spacer />
            <v-select
              :model-value="entry.method"
              :items="methodItems(entry)"
              item-title="title"
              item-value="value"
              density="compact"
              variant="underlined"
              hide-details
              class="initiative-dialog-method"
              @update:model-value="entry.method = $event as InitiativeRollMethod"
            />
            <v-btn icon variant="text" size="x-small" title="Убрать" @click="removeEntry(entry.participant.id)">
              <v-icon size="16">mdi-close</v-icon>
            </v-btn>
          </div>

          <div v-if="!entry.version" class="text-caption text-medium-emphasis mb-1">
            Нет листа персонажа с характеристиками. Заполните профиль, чтобы они появились.
          </div>

          <div v-if="entry.method === 'characteristic'" class="initiative-dialog-config">
            <v-select
              :model-value="entry.characteristicCode"
              :items="characteristicItems(entry)"
              item-title="name"
              item-subtitle="valueLabel"
              item-value="code"
              density="compact"
              variant="outlined"
              hide-details
              label="Характеристика"
              :disabled="!entry.characteristicsReady"
              @update:model-value="entry.characteristicCode = $event as string"
            />
            <ClampedNumberField
              v-model="entry.modifier"
              :min="-20"
              :max="20"
              density="compact"
              hide-details
              label="Модификатор"
            />
            <ClampedNumberField
              v-model="entry.adv"
              :min="-ROLL_ADV_MAX"
              :max="ROLL_ADV_MAX"
              density="compact"
              hide-details
              label="Преим./помехи"
            />
          </div>

          <div v-if="entry.method === 'free'" class="initiative-dialog-config">
            <ClampedNumberField
              v-model="entry.freeDiceCount"
              :min="ROLL_DICE_COUNT_MIN"
              :max="ROLL_DICE_COUNT_MAX"
              density="compact"
              hide-details
              label="Кубы"
            />
            <ClampedNumberField
              v-model="entry.freeEfficiency"
              :min="ROLL_EFFICIENCY_MIN"
              :max="ROLL_EFFICIENCY_MAX"
              density="compact"
              hide-details
              label="Сложность"
            />
            <ClampedNumberField
              v-model="entry.adv"
              :min="-ROLL_ADV_MAX"
              :max="ROLL_ADV_MAX"
              density="compact"
              hide-details
              label="Преим./помехи"
            />
          </div>

          <div v-if="entry.method === 'fixed'" class="initiative-dialog-config">
            <ClampedNumberField
              :model-value="entry.fixedValue ?? 0"
              :min="0"
              :max="100"
              density="compact"
              hide-details
              label="Фиксированное значение"
              @update:model-value="entry.fixedValue = $event"
            />
          </div>
        </div>

        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-3">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn
          color="primary"
          variant="tonal"
          prepend-icon="mdi-dice-d6"
          :loading="rolling"
          :disabled="entries.length === 0"
          @click="roll"
        >
          Бросить инициативу
        </v-btn>
        <v-btn variant="text" @click="emit('update:open', false)">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.initiative-dialog-entry {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  padding: 8px 10px;
  margin-bottom: 8px;
}
.initiative-dialog-method {
  max-width: 220px;
}
.initiative-dialog-config {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 6px;
  align-items: flex-start;
}
.initiative-dialog-config :deep(.v-select) {
  flex: 1 1 200px;
  min-width: 160px;
}
.initiative-dialog-config :deep(.v-number-input) {
  flex: 1 1 160px;
  min-width: 130px;
  max-width: 220px;
}
</style>
