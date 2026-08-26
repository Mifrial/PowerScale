<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';

import { gameStatusTransitionsService } from '@/modules/Roleplay/Game/Service/Instance/gameStatusTransitionsService';

import { toCreateGameData } from '@/modules/Roleplay/Game/Utils/toCreateGameData';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { ChatSpeakerOption } from '@/modules/Messages/Chat/Dto/ChatSpeakerOption';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { ChatThread, chatInlineRendererContext } from '@/modules/Messages/Chat/init';
import InitiativeTrack from '@/modules/Roleplay/Game/Component/InitiativeTrack.vue';
import CombatQuickRolls from '@/modules/Roleplay/Game/Component/CombatQuickRolls.vue';
import CombatCardPanel from '@/modules/Roleplay/Game/Component/Detail/CombatCardPanel.vue';
import CheckLaunchDialog from '@/modules/Roleplay/Game/Component/CheckLaunchDialog.vue';
import HitLaunchDialog from '@/modules/Roleplay/Game/Component/HitLaunchDialog.vue';
import InjuryLaunchDialog from '@/modules/Roleplay/Game/Component/InjuryLaunchDialog.vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';

import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import { CHECK_HIT_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';
import { combatChatFoldService } from '@/modules/Roleplay/Game/Service/Instance/combatChatFoldService';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';

const props = defineProps<{
  /** Активна ли вкладка: чат монтируется только при открытии (D7 — освобождает глобальный чат при уходе). */
  active: boolean;
  detail: GameDetail;
  canEdit: boolean;
}>();

const userStore = useUserStore();
const store = useGameStore();
const spaceRevisionStore = useSpaceRevisionStore();

const chatId = computed(() => props.detail.gameChatId);
const gameId = computed(() => props.detail.game.id);
const combatThread = useCombatChatThread(gameId);
const messageThread = computed(() => combatThread.stamp());
const liveFoldIds = combatThread.liveIds;

function buildCombatChatFolds(messages: ChatMessage[]): ChatFoldChild[] {
  return combatChatFoldService.buildCombatChatFolds(messages);
}

// Кнопки статуса — в глобальный топбар (#editor-actions), видны только на этой вкладке.
const statusUpdating = ref(false);
const statusError = ref<string | null>(null);

const showStartGame = computed(
  () => props.canEdit && gameStatusTransitionsService.canStartGame(props.detail.game.status),
);
const showStopSession = computed(
  () => props.canEdit && gameStatusTransitionsService.canStopSession(props.detail.game.status),
);

// «Начать сессию» → playing, «Остановить сессию» → in_process (межсессионный период). Сессия не
// трогает терминальный статус игры (completed ставится отдельно — селектором в форме игры).
// При остановке сессии боевые изменения (оверлей) персонажей собираются в pendingVersion на модерацию (CD-2).
async function ensurePlaying(): Promise<void> {
  if (!gameStatusTransitionsService.canStartGame(props.detail.game.status)) return;
  await changeStatus('playing');
}

async function changeStatus(target: GameStatus): Promise<void> {
  statusUpdating.value = true;
  statusError.value = null;
  try {
    const updated = await getGameApi().updateGame(gameId.value, { ...toCreateGameData(props.detail), status: target });
    store.applyGameUpdate(updated);
    if (target === 'in_process' || target === 'completed') {
      await getGameApi().submitCombatChanges(gameId.value);
    }
  } catch (e) {
    statusError.value = e instanceof Error ? e.message : 'Не удалось изменить статус';
  } finally {
    statusUpdating.value = false;
  }
}

// Ревизия игры: правила и механики → контекст чата (чипы [[rule:...]], «Вставить ссылку»,
// броски через RollEngine). Собирается общим buildChatRulesContext.
const revisionRules = ref<Rule[]>([]);
const mechanics = ref<Mechanic[]>([]);

const rulesContext = computed(() =>
  gameChatRulesContextService.buildChatRulesContext(
    revisionRules.value,
    mechanics.value,
    props.detail?.game.spaceId,
    props.detail?.game.rulesRevision,
  ),
);

const tokenSources = computed<ITokenSource[]>(() => [
  ...rulesContext.value.tokenSources,
  {
    type: 'character',
    label: 'Персонаж',
    icon: 'mdi-account',
    search: async (query) => {
      const q = query.toLowerCase();

      return memberships.value
        .filter((membership) => membership.membershipStatus === 'approved')
        .filter((membership) => !q || membership.characterName.toLowerCase().includes(q))
        .map((membership) => ({
          value: `${membership.characterId},${membership.characterName}`,
          label: membership.characterName,
        }));
    },
  },
  {
    type: 'npc',
    label: 'НПС',
    icon: 'mdi-account-cowboy-hat',
    search: async (query) => {
      const q = query.toLowerCase();

      return npcs.value
        .filter((npc) => npc.status === 'active')
        .filter((npc) => !q || npc.name.toLowerCase().includes(q))
        .map((npc) => ({ value: `${npc.id},${npc.name}`, label: npc.name }));
    },
  },
]);

const rendererContext = computed(() => chatInlineRendererContext(rulesContext.value));

const processAttachments = (attachments: ChatAttachment[]): ChatAttachment[] =>
  rulesContext.value.processAttachments(attachments);

const memberships = ref<GameCharacterMembership[]>([]);
const npcs = ref<GameNpc[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);

// Макросы быстрых бросков per entityKey (CD-8): звёздочка в карточке и блок в сайдбаре.
const quickRolls = ref<Record<string, string[]>>({});

// «От лица кого» писать: игрок — только свои approved-персонажи; ведущий — роль ведущего,
// свои approved-персонажи и НПС игры (ТР §8 «Чат игры»).
const speakerOptions = computed<ChatSpeakerOption[]>(() => {
  const user = userStore.currentUser;
  if (!user) return [];
  const ownCharacters = memberships.value
    .filter((membership) => membership.membershipStatus === 'approved' && membership.characterOwnerId === user.id)
    .map<ChatSpeakerOption>((membership) => ({
      key: `character:${membership.characterId}`,
      label: membership.characterName,
      speaker: { kind: 'character', characterId: membership.characterId, characterName: membership.characterName },
    }));

  if (!props.canEdit) return ownCharacters;

  return [
    { key: 'gm', label: 'Ведущий', speaker: { kind: 'gm' } },
    ...ownCharacters,
    ...npcs.value
      .filter((npc) => npc.status === 'active')
      .map<ChatSpeakerOption>((npc) => ({
        key: `npc:${npc.id}`,
        label: npc.name,
        speaker: { kind: 'npc', npcId: npc.id, npcName: npc.name },
      })),
  ];
});

async function load(): Promise<void> {
  loading.value = true;
  loadError.value = null;
  try {
    memberships.value = await getGameApi().getGameCharacters(gameId.value);
    npcs.value = await getGameApi().getNpcs(gameId.value);
    quickRolls.value = await getGameApi().getQuickRolls(gameId.value);
    mechanics.value = await getRuleApi().getMechanics();
    await loadRevision();
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : 'Не удалось загрузить данные чата';
  } finally {
    loading.value = false;
  }
}

// Правила ревизии игры: чипы и источники «Вставить ссылку» резолвятся из неё (D72).
async function loadRevision(): Promise<void> {
  const detail = props.detail;
  const revision = await spaceRevisionStore.fetchRevision(detail.game.spaceId, detail.game.rulesRevision);
  revisionRules.value = revision.rules;
}

watch(
  () => props.active,
  (value) => {
    if (value) void load();
  },
  { immediate: true },
);

// Авто-переключение селектора «от лица кого» на активного персонажа инициативы:
// только если этот источник речи доступен текущему пользователю (его персонаж/НПС/роль).
// Канонический выбор живёт здесь (ChatInput эмитит ручной выбор обратно) — его же
// использует блок быстрых бросков вместо собственного селектора.
const activeSpeakerKey = ref<string | null>(null);
const lastTurnId = ref<string | null>(null);

function applySpeakerKey(): void {
  const id = lastTurnId.value;
  if (id !== null && speakerOptions.value.some((item) => item.key === id)) {
    activeSpeakerKey.value = id;
  }
  // Ход недоступен/нет хода — сохраняем текущий выбор (ручной/прежний авто).
}

function onSpeakerKeyChange(key: string | null): void {
  activeSpeakerKey.value = key;
}

function onTurn(participantId: string | null): void {
  lastTurnId.value = participantId;
  applySpeakerKey();
}

// Боевая карточка: слайд-овер по клику на участника шкалы инициативы (CD-5).
const cardOpen = ref(false);
const cardKey = ref<CombatEntityKey | null>(null);

// Ревизия оверлеев — счётчик мутаций боевых изменений: шкала инициативы перечитывает
// оверлеи (Истощение) после правок в боевой карточке.
const overlayRevision = ref(0);

function onOverlayChanged(): void {
  overlayRevision.value += 1;
}

function onOpenCard(entityKey: string): void {
  const key = entityKey as CombatEntityKey;
  if (
    !combatCardModelService.combatCardCanEdit(key, props.canEdit, userStore.currentUser?.id ?? null, memberships.value)
  )
    return;
  cardKey.value = key;
  cardOpen.value = true;
}

function onCloseCard(): void {
  cardOpen.value = false;
  cardKey.value = null;
}

// Действие у селектора «от лица кого»: открыть карточку выбранного участника (CD-5).
const speakerAction = computed<{ icon: string; title: string; onClick: () => void } | null>(() => {
  const key = activeSpeakerKey.value;
  if (key === null || key === 'gm') return null;

  return { icon: 'mdi-card-account-details-outline', title: 'Открыть карточку', onClick: () => onOpenCard(key) };
});

// Добавить/убрать макрос быстрого броска (CD-8): результат хранилища — актуальный список.
async function toggleQuickRoll(entityKey: string, ruleId: string): Promise<void> {
  const key = entityKey as CombatEntityKey;
  try {
    const current = quickRolls.value[key] ?? [];
    const next = current.includes(ruleId)
      ? await getGameApi().removeQuickRoll(gameId.value, key, ruleId)
      : await getGameApi().addQuickRoll(gameId.value, key, ruleId);
    quickRolls.value = { ...quickRolls.value, [key]: next };
  } catch {
    // Прототип: сбой тумблера не блокирует интерфейс (запись остаётся прежней).
  }
}

watch(speakerOptions, () => applySpeakerKey());

const checkOpen = ref(false);
const hitOpen = ref(false);
const injuryOpen = ref(false);
const resumeOffer = ref<CheckOffer | null>(null);
const hitResumeOffer = ref<CheckOffer | null>(null);
const hitAttackerKey = ref<CombatEntityKey | null>(null);
const hitAttack = ref<AttackOverview | null>(null);
const pendingOffers = ref<CheckOffer[]>([]);
const dismissedOfferIds = ref<Set<number>>(new Set());
let pendingPoll: ReturnType<typeof setInterval> | null = null;

const speakerEntityKey = computed<CombatEntityKey | null>(() => {
  const key = activeSpeakerKey.value;
  if (key === null || key === 'gm') return null;

  return key as CombatEntityKey;
});

function isWaitingOnSpeaker(offer: CheckOffer, key: CombatEntityKey | null, asGm: boolean): boolean {
  if (offer.status !== 'pending') return false;
  if (asGm) return true;
  if (key === null) return false;

  return (
    (offer.waitingOn === 'opponent' && offer.opponent === key) ||
    (offer.waitingOn === 'initiator' && offer.initiator === key)
  );
}

function isActionableOffer(offer: CheckOffer, key: CombatEntityKey | null, asGm: boolean): boolean {
  if (dismissedOfferIds.value.has(offer.id)) return false;

  return isWaitingOnSpeaker(offer, key, asGm);
}

function isWaitingOnYou(offer: CheckOffer, key: CombatEntityKey | null): boolean {
  if (offer.status !== 'pending' || key === null) return false;

  return (
    (offer.waitingOn === 'opponent' && offer.opponent === key) ||
    (offer.waitingOn === 'initiator' && offer.initiator === key)
  );
}

const actionableOfferCount = computed(
  () =>
    pendingOffers.value.filter(
      (offer) => isWaitingOnYou(offer, speakerEntityKey.value) && !dismissedOfferIds.value.has(offer.id),
    ).length,
);

function pendingToResume(): CheckOffer | undefined {
  const key = speakerEntityKey.value;
  const asGm = props.canEdit;
  const mine = pendingOffers.value.filter((offer) => isWaitingOnSpeaker(offer, key, asGm));

  return mine.find((offer) => offer.checkCode === CHECK_HIT_CODE) ?? mine[0];
}

async function refreshPendingOffers(): Promise<void> {
  const key = speakerEntityKey.value;
  const asGm = props.canEdit;
  if (!props.active || (key === null && !asGm)) {
    pendingOffers.value = [];

    return;
  }
  try {
    pendingOffers.value = asGm
      ? await getGameApi().getCheckOffersForGame(gameId.value)
      : await getGameApi().getCheckOffersForEntity(gameId.value, key as CombatEntityKey);
  } catch {
    pendingOffers.value = [];
  }
  const actionable = pendingOffers.value.filter((offer) => isActionableOffer(offer, key, asGm));
  const first =
    actionable.find((offer) => offer.checkCode === CHECK_HIT_CODE && offer.waitingOn === 'opponent') ?? actionable[0];
  if (!checkOpen.value && !hitOpen.value && first) {
    if (first.checkCode === CHECK_HIT_CODE) {
      hitResumeOffer.value = first;
      hitAttackerKey.value = first.initiator;
      hitAttack.value = null;
      hitOpen.value = true;
    } else {
      resumeOffer.value = first;
      checkOpen.value = true;
    }
  }
}

function reopenOffer(offer: CheckOffer): void {
  dismissedOfferIds.value = new Set([...dismissedOfferIds.value].filter((id) => id !== offer.id));
  if (offer.checkCode === CHECK_HIT_CODE) {
    hitResumeOffer.value = offer;
    hitAttackerKey.value = offer.initiator;
    hitAttack.value = null;
    hitOpen.value = true;

    return;
  }
  resumeOffer.value = offer;
  checkOpen.value = true;
}

function openCheckLaunch(): void {
  const existing = pendingToResume();
  if (existing) {
    reopenOffer(existing);

    return;
  }
  openNewCheck();
}

function openNewCheck(): void {
  resumeOffer.value = null;
  checkOpen.value = true;
}

function onCheckClosed(open: boolean): void {
  if (open) return;
  if (resumeOffer.value) dismissedOfferIds.value = new Set([...dismissedOfferIds.value, resumeOffer.value.id]);
  resumeOffer.value = null;
  checkOpen.value = false;
  void refreshPendingOffers();
}

function onHitClosed(open: boolean): void {
  if (open) return;
  const current = hitResumeOffer.value;
  const key = speakerEntityKey.value;
  const waitingKey =
    current && current.status === 'pending'
      ? current.waitingOn === 'opponent'
        ? current.opponent
        : current.initiator
      : null;
  if (current && (props.canEdit || (key !== null && key === waitingKey))) {
    dismissedOfferIds.value = new Set([...dismissedOfferIds.value, current.id]);
  }
  hitResumeOffer.value = null;
  hitAttack.value = null;
  hitAttackerKey.value = null;
  hitOpen.value = false;
  void refreshPendingOffers();
}

function onLaunchHit(payload: { attackerKey: CombatEntityKey; attack: AttackOverview }): void {
  hitResumeOffer.value = null;
  hitAttackerKey.value = payload.attackerKey;
  hitAttack.value = payload.attack;
  hitOpen.value = true;
}

function onLaunchInjury(): void {
  injuryOpen.value = true;
}

watch(
  () => [props.active, activeSpeakerKey.value, gameId.value] as const,
  ([active]) => {
    if (pendingPoll) {
      clearInterval(pendingPoll);
      pendingPoll = null;
    }
    if (!active) return;
    void refreshPendingOffers();
    pendingPoll = setInterval(() => void refreshPendingOffers(), 2000);
  },
  { immediate: true },
);

onUnmounted(() => {
  if (pendingPoll) clearInterval(pendingPoll);
});
</script>

<template>
  <Teleport to="#editor-actions">
    <v-btn
      v-if="showStartGame"
      variant="tonal"
      color="success"
      size="small"
      prepend-icon="mdi-play"
      :loading="statusUpdating"
      @click="changeStatus('playing')"
    >
      Начать сессию
    </v-btn>
    <v-btn
      v-if="showStopSession"
      variant="tonal"
      color="warning"
      size="small"
      prepend-icon="mdi-stop"
      :loading="statusUpdating"
      @click="changeStatus('in_process')"
    >
      Остановить сессию
    </v-btn>
    <v-btn-group v-if="chatId !== null" class="check-split" variant="tonal" divided rounded="lg">
      <v-btn size="small" prepend-icon="mdi-shield-check-outline" @click="openCheckLaunch">
        Проверка
        <span v-if="actionableOfferCount > 0" class="check-split__count">{{ actionableOfferCount }}</span>
      </v-btn>
      <v-btn size="small" class="check-split__plus" aria-label="Новая проверка" @click="openNewCheck">
        <v-icon size="18">mdi-plus</v-icon>
      </v-btn>
    </v-btn-group>
  </Teleport>

  <div class="game-chat-tab">
    <v-alert v-if="statusError" type="error" variant="tonal" density="compact" class="mb-3">{{ statusError }}</v-alert>
    <v-alert v-if="loadError" type="error" variant="tonal" density="compact" class="mb-3">
      <div class="d-flex align-center ga-2">
        <span>{{ loadError }}</span>
        <v-btn variant="tonal" color="primary" size="small" @click="load">Попробовать снова</v-btn>
      </div>
    </v-alert>
    <div class="game-chat-body">
      <div class="game-chat-sidebar">
        <InitiativeTrack
          :game-id="gameId"
          :space-id="detail.game.spaceId"
          :chat-id="chatId"
          :can-edit="canEdit"
          :characters="memberships"
          :npcs="npcs"
          :rules="revisionRules"
          :mechanics="mechanics"
          :overlay-revision="overlayRevision"
          :ensure-playing="ensurePlaying"
          class="game-chat-sidebar__initiative"
          @turn="onTurn"
          @open-card="onOpenCard"
          @overlay-changed="onOverlayChanged"
        />

        <CombatQuickRolls
          :game-id="gameId"
          :chat-id="chatId"
          :can-edit="canEdit"
          :current-user-id="userStore.currentUser?.id ?? null"
          :memberships="memberships"
          :npcs="npcs"
          :rules="revisionRules"
          :mechanics="mechanics"
          :quick-rolls="quickRolls"
          :active-entity-key="activeSpeakerKey"
          :overlay-revision="overlayRevision"
          class="game-chat-sidebar__quickrolls"
          @toggle-quick-roll="toggleQuickRoll"
        />
      </div>

      <ChatThread
        v-if="active && chatId !== null"
        :chat-id="chatId"
        :speakers="speakerOptions"
        :active-speaker-key="activeSpeakerKey"
        :speaker-action="speakerAction"
        :renderer-context="rendererContext"
        :open-entity="onOpenCard"
        :token-sources="tokenSources"
        :process-attachments="processAttachments"
        :message-thread="messageThread"
        :build-folds="buildCombatChatFolds"
        :live-fold-ids="liveFoldIds"
        empty-label="Чат игры доступен в мессенджере"
        class="game-chat-thread"
        @update:active-speaker-key="onSpeakerKeyChange"
      />
      <v-card v-else-if="chatId === null" class="game-chat-empty">
        <v-card-text class="text-medium-emphasis text-center pa-8">Игровой чат ещё не создан</v-card-text>
      </v-card>
    </div>

    <CombatCardPanel
      v-model:open="cardOpen"
      :entity-key="cardKey"
      :game-id="gameId"
      :chat-id="chatId"
      :memberships="memberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="userStore.currentUser?.id ?? null"
      :quick-rolls="quickRolls"
      :space-id="detail.game.spaceId"
      :rules-revision="detail.game.rulesRevision"
      :overlay-revision="overlayRevision"
      @update:open="onCloseCard"
      @toggle-quick-roll="toggleQuickRoll"
      @overlay-changed="onOverlayChanged"
      @launch-hit="onLaunchHit"
      @launch-injury="onLaunchInjury"
    />

    <CheckLaunchDialog
      :open="checkOpen"
      :game-id="gameId"
      :space-id="detail.game.spaceId"
      :chat-id="chatId"
      :characters="memberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="userStore.currentUser?.id ?? null"
      :active-speaker-key="activeSpeakerKey"
      :resume-offer="resumeOffer"
      @update:open="onCheckClosed"
      @settled="refreshPendingOffers"
    />

    <HitLaunchDialog
      :open="hitOpen"
      :game-id="gameId"
      :chat-id="chatId"
      :characters="memberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="userStore.currentUser?.id ?? null"
      :active-speaker-key="activeSpeakerKey"
      :attacker-key="hitAttackerKey"
      :attack="hitAttack"
      :resume-offer="hitResumeOffer"
      @update:open="onHitClosed"
      @settled="refreshPendingOffers"
      @overlay-changed="onOverlayChanged"
    />
    <InjuryLaunchDialog
      :open="injuryOpen"
      :game-id="gameId"
      :chat-id="chatId"
      :characters="memberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :target-key="cardKey"
      @update:open="injuryOpen = $event"
      @overlay-changed="onOverlayChanged"
    />
  </div>
</template>

<style scoped>
.game-chat-tab {
  width: 100%;
  min-width: 0;
}
.game-chat-body {
  display: flex;
  align-items: stretch;
  gap: 12px;
  width: 100%;
  min-width: 0;
}
.game-chat-sidebar {
  flex: 21 1 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  /* Высота всегда ровно как у блока чата (тот же calc, что у ChatThread). */
  height: calc(100vh - var(--v-layout-top) - 70px);
  min-height: 360px;
}
.game-chat-thread {
  flex: 79 1 0;
  min-width: 0;
  height: calc(100vh - var(--v-layout-top) - 70px);
}
.game-chat-thread :deep(.chat-thread) {
  width: 100%;
  min-width: 0;
  height: 100%;
}
.game-chat-sidebar__initiative {
  flex: 1 1 55%;
  min-height: 0;
}
.game-chat-sidebar__quickrolls {
  flex: 1 1;
  min-height: 0;
}
.game-chat-empty {
  flex: 79 1 0;
  min-width: 0;
  border: 1px dashed rgba(var(--v-theme-divider), var(--v-border-opacity));
}
</style>

<style>
/* Teleport в #editor-actions: scoped-стили на группу не доезжают. */
.check-split.v-btn-group {
  --v-btn-height: 28px;
  height: var(--v-btn-height);
  overflow: hidden;
  gap: 0 !important;
  align-self: center;
}
.check-split.v-btn-group .v-btn {
  border-radius: 0 !important;
  height: var(--v-btn-height) !important;
  min-width: 0;
}
.check-split.v-btn-group .v-btn:first-child {
  border-top-left-radius: 8px !important;
  border-bottom-left-radius: 8px !important;
}
.check-split.v-btn-group .v-btn:last-child {
  border-top-right-radius: 8px !important;
  border-bottom-right-radius: 8px !important;
}
.check-split__plus {
  padding-inline: 4px !important;
}
.check-split .check-split__count {
  display: inline-flex;
  min-width: 18px;
  height: 18px;
  margin-left: 6px;
  border-radius: 50%;
  background: rgb(var(--v-theme-info));
  color: rgb(var(--v-theme-on-info));
  font-size: 11px;
  font-weight: 600;
  align-items: center;
  justify-content: center;
}
</style>
