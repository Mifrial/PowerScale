<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';

import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import { useAttackFavorites } from '@/modules/Roleplay/Character/init';
import { AttackTile } from '@/modules/Roleplay/Character/init';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { characteristicRollService } from '@/modules/Roleplay/Game/Service/Instance/characteristicRollService';

import type { QuickRollRecord } from '@/modules/Roleplay/Game/Dto/QuickRollRecord';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { attackActionSourceService } from '@/modules/Roleplay/Game/Service/Instance/attackActionSourceService';

import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';

/**
 * Блок «Быстрые броски» в сайдбаре игрового чата (CD-8). Показывает записи участника,
 * выбранного селектором «от лица кого» в чате (активный источник речи): клик — бросок
 * как кубик, «×» — снять звёздочку. Звёздочка в боевой карточке добавляет запись.
 */
const props = defineProps<{
  gameId: number;
  chatId: number | null;
  canEdit: boolean;
  currentUserId: number | null;
  memberships: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  mechanics: Mechanic[];
  /** Макросы быстрых бросков per entityKey. */
  quickRolls: Record<string, string[]>;
  /** Активный источник речи чата (entityKey или 'gm'); без сущности блок пуст. */
  activeEntityKey: string | null;
  /** Мутации оверлея (экип в карточке) — перечитать лист, иначе звёздочка оружия не резолвится. */
  overlayRevision?: number;
}>();

const emit = defineEmits<{
  'toggle-quick-roll': [entityKey: string, ruleCode: string];
  'launch-hit': [payload: { attackerKey: CombatEntityKey; attack: AttackOverview }];
}>();

const sendChat = combatChatSendService.sendCombatChat(props.gameId);

const overlays = ref<GameCombatOverlay[]>([]);
const error = ref<string | null>(null);

const selectedKey = computed<CombatEntityKey | null>(() => {
  const key = props.activeEntityKey;
  if (key === null || key === 'gm') return null;

  return key as CombatEntityKey;
});

async function loadOverlays(): Promise<void> {
  error.value = null;
  try {
    overlays.value = await getGameApi().getCombatOverlays(props.gameId);
  } catch {
    overlays.value = [];
  }
}

watch(
  () => props.gameId,
  () => void loadOverlays(),
  { immediate: true },
);

watch(
  () => props.overlayRevision,
  () => void loadOverlays(),
);

const selectedOverlay = computed(() => overlays.value.find((item) => item.entityKey === selectedKey.value) ?? null);

const model = computed(() =>
  selectedKey.value === null
    ? null
    : combatCardModelService.combatCardModel(
        selectedKey.value,
        props.memberships,
        props.npcs,
        props.canEdit,
        props.currentUserId,
        selectedOverlay.value,
      ),
);

const effectiveVersion = computed(() => model.value?.effectiveVersion ?? null);

const overview = computed(() =>
  effectiveVersion.value ? characterOverviewService.build(effectiveVersion.value, props.rules) : null,
);

const records = computed<QuickRollRecord[]>(() => {
  if (selectedKey.value === null || !overview.value) return [];

  return combatCardModelService.resolveQuickRollRecords(
    props.quickRolls[selectedKey.value] ?? [],
    combatCardModelService.quickRollRecords(overview.value),
  );
});
const attackFavorites = useAttackFavorites();
const favoriteAttack = computed(() =>
  attackActionSourceService.favoriteAttack(
    overview.value?.attacks ?? [],
    selectedKey.value ? (attackFavorites.favoriteOf(selectedKey.value) ?? null) : null,
    props.rules,
  ),
);

const speaker = computed<ChatSpeaker>(() => {
  const current = model.value;
  if (!current) return { kind: 'gm' };

  return current.kind === 'character'
    ? { kind: 'character', characterId: current.entityId, characterName: current.name }
    : { kind: 'npc', npcId: current.entityId, npcName: current.name };
});

async function rollRecord(record: QuickRollRecord): Promise<void> {
  if (props.chatId === null) return;
  error.value = null;
  try {
    const result = characteristicRollService.rollCharacteristic(
      {
        name: record.name,
        value: record.value,
        ruleCode: record.ruleCode,
        actorKey: selectedKey.value ?? undefined,
      },
      props.rules,
      props.mechanics,
    );
    await sendChat(record.name, [{ type: ROLL_ATTACHMENT_TYPE, payload: result }], props.chatId, speaker.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить бросок';
  }
}

function removeRecord(ruleCode: string): void {
  if (selectedKey.value === null) return;
  emit('toggle-quick-roll', selectedKey.value, ruleCode);
}

function launchFavoriteAttack(): void {
  if (!selectedKey.value || !favoriteAttack.value) return;
  emit('launch-hit', { attackerKey: selectedKey.value, attack: favoriteAttack.value });
}
</script>

<template>
  <v-card variant="flat" class="combat-quick-rolls" border>
    <div class="combat-quick-rolls__header">
      <span class="text-subtitle-2 font-weight-medium">
        <v-icon icon="mdi-star-four-points-outline" size="18" class="mr-1" />
        Быстрые броски
      </span>
    </div>
    <v-card-text class="combat-quick-rolls__body pa-2">
      <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-1">{{ error }}</v-alert>

      <div v-if="selectedKey === null" class="text-caption text-medium-emphasis">
        Выберите, от чьего имени писать в чате
      </div>

      <template v-else>
        <template v-if="model">
          <div v-if="!effectiveVersion" class="text-caption text-medium-emphasis pa-1">Лист не заполнен</div>
          <template v-else>
            <div v-if="records.length === 0" class="text-caption text-medium-emphasis pa-1">
              Звёздочка в карточке добавит бросок сюда
            </div>
            <div v-else class="combat-quick-rolls__list">
              <div
                v-for="record in records"
                :key="record.ruleCode"
                class="combat-quick-rolls__record"
                :class="{ 'combat-quick-rolls__record--rollable': chatId !== null }"
                @click="chatId !== null && rollRecord(record)"
              >
                <v-icon icon="mdi-dice-d6-outline" size="16" class="combat-quick-rolls__record-icon" />
                <span class="combat-quick-rolls__record-name text-truncate">{{ record.name }}</span>
                <span class="combat-quick-rolls__record-value">{{ record.valueLabel }}</span>
                <button
                  v-if="model.canEdit"
                  type="button"
                  class="combat-quick-rolls__remove"
                  title="Снять звёздочку"
                  @click.stop="removeRecord(record.ruleCode)"
                >
                  <v-icon icon="mdi-close" size="small" />
                </button>
              </div>
            </div>
            <AttackTile
              v-if="favoriteAttack"
              variant="combat"
              :attack="favoriteAttack"
              class="mt-2"
              @launch="launchFavoriteAttack"
            />
          </template>
        </template>
      </template>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.combat-quick-rolls {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.combat-quick-rolls__header {
  padding: 8px 12px 2px;
  flex-shrink: 0;
}
.combat-quick-rolls__body {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.combat-quick-rolls__list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 4px;
}
.combat-quick-rolls__record {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  font-size: 13px;
  flex-shrink: 0;
}
.combat-quick-rolls__record--rollable {
  cursor: pointer;
}
.combat-quick-rolls__record--rollable:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.combat-quick-rolls__record-icon {
  opacity: 0.7;
  flex-shrink: 0;
}
.combat-quick-rolls__record-name {
  flex: 1;
  min-width: 0;
}
.combat-quick-rolls__record-value {
  font-weight: 500;
  flex-shrink: 0;
}
.combat-quick-rolls__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-shrink: 0;
  color: rgb(var(--v-theme-on-surface));
}
.combat-quick-rolls__remove:hover {
  color: rgb(var(--v-theme-error));
}
</style>
