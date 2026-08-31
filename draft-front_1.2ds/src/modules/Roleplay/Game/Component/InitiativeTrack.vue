<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useChatChannel } from '@/modules/Messages/Chat/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import InitiativeDialog from '@/modules/Roleplay/Game/Component/InitiativeDialog.vue';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';

import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';

import { bloodLossService } from '@/modules/Roleplay/Game/Service/Instance/bloodLossService';
import { ACCUMULATED_DAMAGE_STATE_CODE } from '@/modules/Roleplay/Rule/init';

import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';

import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';
import { actionExecutionService } from '@/modules/Roleplay/Game/Service/Instance/actionExecutionService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import {
  asActionAbilitySpec,
  asProcessAbilitySpec,
  WAIT_ACTION_CODE,
  findRuleByRef,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { formatProcessEffect } from '@/modules/Roleplay/Game/Utils/processMessage';

import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';

type SystemNotification = { content: string; kind: ChatMessage['kind']; thread?: ChatThreadRef };

/**
 * Шкала инициативы (ТР §8 «Чат игры»). Жизненный цикл: «Инициатива» (окно проверки, ГМ) →
 * активная шкала (порядок + «Передать ход»/«Добавить»/«Закончить») → завершена («Продолжить», только `playing`).
 * Остановка сессии сбрасывает шкалу.
 * Порядок хода хранится как есть (результат броска не хранится); при передаче хода в чат
 * постится системное уведомление «Ходит Имя». Эмитит `turn` (id активного участника) —
 * для авто-переключения селектора «от лица кого» у владельца хода.
 */
const props = defineProps<{
  gameId: number;
  spaceId: number;
  /** Игровой чат (для системных уведомлений «Ходит Имя»). */
  chatId: number | null;
  /** ГМ управляет шкалой (проверка/добавление/передача/завершение); игроки — просмотр. */
  canEdit: boolean;
  /** Approved-персонажи игры (для выбора в окне проверки и «Добавить»). */
  characters: GameCharacterMembership[];
  /** Активные НПС игры. */
  npcs: GameNpc[];
  /** Правила ревизии игры (характеристики + дефолты «Бросок»). */
  rules: Rule[];
  /** Механики ревизии (броски инициативы через RollEngine). */
  mechanics: Mechanic[];
  /** Счётчик мутаций боевых оверлеев: при изменении — перечитать оверлеи (Истощение). */
  overlayRevision: number;
  /** Начать сессию, если ещё не playing (бросок инициативы = старт сцены). */
  ensurePlaying?: () => Promise<void>;
  gameStatus: GameStatus;
}>();

const emit = defineEmits<{
  turn: [participantId: string | null];
  /** Открыть боевую карточку участника (entityKey: `character:{id}` | `npc:{id}`). */
  'open-card': [entityKey: string];
  'overlay-changed': [];
}>();

const { currentUser } = useCurrentUser();
const chatStore = useChatChannel();
const combatThread = useCombatChatThread(() => props.gameId);
const sendChat = combatChatSendService.sendCombatChat(props.gameId);

const initiative = ref<GameInitiative | null>(null);
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);

const dialogOpen = ref(false);
const addMenuOpen = ref(false);
const waitDialogOpen = ref(false);
const waitBusy = ref(false);
const waitError = ref<string | null>(null);
const processSessions = ref<Record<CombatEntityKey, ProcessSession>>({});
const pendingEffectsByEntity = ref<Record<CombatEntityKey, PendingActionEffect[]>>({});

// Оверлеи боевых изменений участников: для текущего Истощения (сумма состояния 'exhaustion').
const overlays = ref<GameCombatOverlay[]>([]);

const activeParticipant = computed(() => {
  const data = initiative.value;
  if (!data || data.activeIndex === null) return null;

  return data.participants[data.activeIndex] ?? null;
});
const activeParticipantKey = computed<CombatEntityKey | null>(() => {
  const participant = activeParticipant.value;

  return participant?.id ? (participant.id as CombatEntityKey) : null;
});
const activeActionPoints = computed(() =>
  activeParticipantKey.value ? (actionPointsByEntity.value.get(activeParticipantKey.value) ?? 0) : 0,
);
const waitRule = computed(
  () => props.rules.find((rule) => rule.code === WAIT_ACTION_CODE && rule.type === 'ability') ?? null,
);
const waitAction = computed(() => {
  const rule = waitRule.value;
  const spec = rule ? asActionAbilitySpec(rule) : null;

  return rule && spec
    ? {
        ruleCode: rule.code,
        code: rule.code,
        name: rule.name,
        odCost: 0,
        isVariableCost: true,
      }
    : null;
});

// «Передать ход»: ГМ или владелец персонажа, чей сейчас ход; НПС — только ГМ.
const canPass = computed(() => {
  if (props.canEdit) return true;
  const participant = activeParticipant.value;
  const user = currentUser.value;
  if (!participant || !user || participant.kind !== 'character' || participant.entityId === null) return false;
  const membership = props.characters.find((item) => item.characterId === participant.entityId);

  return membership?.characterOwnerId === user.id;
});

const addOptions = computed(() => {
  const existing = new Set(initiative.value?.participants.map((participant) => participant.id) ?? []);
  const characters = props.characters
    .filter((membership) => membership.membershipStatus === 'active')
    .map((membership) => ({
      id: `character:${membership.characterId}`,
      name: membership.characterName,
      kind: 'character' as const,
      entityId: membership.characterId,
    }));
  const npcs = props.npcs
    .filter((npc) => npc.status === 'active')
    .map((npc) => ({ id: `npc:${npc.id}`, name: npc.name, kind: 'npc' as const, entityId: npc.id }));

  return [...characters, ...npcs].filter((option) => !existing.has(option.id));
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    const [nextInitiative, nextProcesses, nextPendingEffects] = await Promise.all([
      getGameApi().getInitiative(props.gameId),
      getGameApi()
        .getProcessSessions(props.gameId)
        .catch(() => ({})),
      getGameApi()
        .getPendingActionEffects(props.gameId)
        .catch(() => ({})),
    ]);
    initiative.value = nextInitiative;
    processSessions.value = nextProcesses;
    pendingEffectsByEntity.value = nextPendingEffects;
    if (initiative.value?.active && props.chatId != null) {
      combatThread.recoverFromMessages(chatStore.messagesOf(props.chatId));
    } else if (initiative.value && !initiative.value.active) {
      combatThread.clearLive();
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить шкалу инициативы';
  } finally {
    loading.value = false;
  }
}

/** Оверлеи боевых изменений (текущее Истощение); сбой не блокирует шкалу. */
async function loadOverlays(): Promise<void> {
  try {
    overlays.value = await getGameApi().getCombatOverlays(props.gameId);
  } catch {
    overlays.value = [];
  }
}

// Истощение per участник: эффективное состояние (версия + оверлей) → сумма 'exhaustion'.
const exhaustionByEntity = computed<Map<string, number>>(() => {
  const map = new Map<string, number>();
  for (const participant of initiative.value?.participants ?? []) {
    if (participant.entityId === null) continue;
    const overlay = overlays.value.find((item) => item.entityKey === participant.id) ?? null;
    const model = combatCardModelService.combatCardModel(
      participant.id as CombatEntityKey,
      props.characters,
      props.npcs,
      false,
      null,
      overlay,
    );
    if (!model.effectiveVersion) continue;
    const value = combatCardModelService.combatExhaustion(model.effectiveVersion.states, props.rules);
    if (value !== null) map.set(participant.id, value);
  }

  return map;
});

const maimByEntity = computed<Map<string, number>>(() => {
  const map = new Map<string, number>();
  for (const participant of initiative.value?.participants ?? []) {
    if (participant.entityId === null) continue;
    const overlay = overlays.value.find((item) => item.entityKey === participant.id) ?? null;
    const model = combatCardModelService.combatCardModel(
      participant.id as CombatEntityKey,
      props.characters,
      props.npcs,
      false,
      null,
      overlay,
    );
    if (!model.effectiveVersion) continue;
    const value = combatCardModelService.combatMaim(model.effectiveVersion.states, props.rules);
    if (value !== null) map.set(participant.id, value);
  }

  return map;
});

const hasActiveProcess = computed(() => {
  const participantKey = activeParticipantKey.value;

  return participantKey ? Boolean(processSessions.value[participantKey]) : false;
});

const actionPointsByEntity = computed<Map<string, number>>(() => {
  const map = new Map<string, number>();
  for (const participant of initiative.value?.participants ?? []) {
    if (participant.entityId === null) continue;
    const overlay = overlays.value.find((item) => item.entityKey === participant.id) ?? null;
    const model = combatCardModelService.combatCardModel(
      participant.id as CombatEntityKey,
      props.characters,
      props.npcs,
      false,
      null,
      overlay,
    );
    if (!model.effectiveVersion) continue;
    const ap = combatCardModelService.combatActionPoints(model.effectiveVersion, props.rules);
    if (ap) map.set(participant.id, ap.current);
  }

  return map;
});

function canInspect(participant: { id: string }): boolean {
  return combatCardModelService.combatCardCanEdit(
    participant.id as CombatEntityKey,
    props.canEdit,
    currentUser.value?.id ?? null,
    props.characters,
  );
}

async function refillActionPoints(entityKey: CombatEntityKey): Promise<void> {
  const overlay = overlays.value.find((item) => item.entityKey === entityKey) ?? null;
  const model = combatCardModelService.combatCardModel(entityKey, props.characters, props.npcs, true, null, overlay);
  const version = model.effectiveVersion;
  if (!version) return;
  const ap = combatCardModelService.combatActionPoints(version, props.rules);
  if (!ap) return;
  const resource = version.resources.find((item) => {
    const rule = props.rules.find((candidate) => candidate.code === item.ruleCode);

    return rule?.code === ACTION_POINTS_CODE;
  });
  if (!resource) return;
  await getGameApi().setCombatResource(props.gameId, entityKey, resource.ruleCode, {
    base: ap.max,
    size: resource.current.size,
  });
}

async function refillParticipants(keys: string[]): Promise<void> {
  await loadOverlays();
  for (const key of keys) {
    try {
      await refillActionPoints(key as CombatEntityKey);
    } catch {
      // Нет ОД на листе — шкалу не блокируем.
    }
  }
  await loadOverlays();
  emit('overlay-changed');
}

async function onInitiativeSaved(): Promise<void> {
  await load();
  if (props.ensurePlaying) await props.ensurePlaying();
  const keys = initiative.value?.participants.map((participant) => participant.id) ?? [];
  await refillParticipants(keys);
}

async function save(next: GameInitiative): Promise<void> {
  saving.value = true;
  error.value = null;
  try {
    initiative.value = await getGameApi().saveInitiative(props.gameId, next);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось сохранить шкалу';
  } finally {
    saving.value = false;
  }
}

function saveAndNotify(next: GameInitiative, notifications: SystemNotification[]): void {
  void save(next).then(() => {
    if (props.chatId !== null) {
      for (const notification of notifications) {
        void chatStore.postSystemMessage(notification.content, props.chatId, notification.kind, notification.thread);
      }
    }
  });
}

function speakerFor(participant: NonNullable<typeof activeParticipant.value>): ChatSpeaker {
  return participant.kind === 'npc'
    ? { kind: 'npc', npcId: participant.entityId ?? 0, npcName: participant.name }
    : { kind: 'character', characterId: participant.entityId ?? 0, characterName: participant.name };
}

function requestTurnPass(): void {
  if (activeActionPoints.value <= 0) {
    void nextTurn();

    return;
  }
  waitError.value = null;
  waitDialogOpen.value = true;
}

async function confirmWaitAndPass(): Promise<void> {
  const participant = activeParticipant.value;
  const key = activeParticipantKey.value;
  const action = waitAction.value;
  const rule = waitRule.value;
  if (!participant || !key || !action || !rule) {
    waitError.value = 'В текущей ревизии отсутствует правило «Ожидание»';

    return;
  }

  waitBusy.value = true;
  waitError.value = null;
  try {
    const processSession = processSessions.value[key];
    const processRule = processSession ? findRuleByRef(props.rules, processSession.processRuleCode) : null;
    const processSpec = processRule ? asProcessAbilitySpec(processRule) : null;
    const processStep =
      processSession && processSpec
        ? processSpec.steps.find((step) => step.code === processSession.currentStepCode)
        : null;
    if (
      processSession &&
      (!processSpec ||
        !processStep ||
        !processSessionService.canInterruptNormally(processSpec, processSession.currentStepCode))
    ) {
      throw new Error('Текущий процесс нельзя прервать обычным способом');
    }
    const completionEffects = processRule ? actionEffectService.effectsAfterProcess(processRule) : [];
    if (processSession) {
      await getGameApi().setProcessSession(props.gameId, key, null);
      pendingEffectsByEntity.value = {
        ...pendingEffectsByEntity.value,
        [key]: [...(pendingEffectsByEntity.value[key] ?? []), ...completionEffects],
      };
      if (props.chatId !== null) {
        const effectText = completionEffects.length
          ? ` Эффект: ${completionEffects.map((item) => formatProcessEffect(item.effect, props.rules)).join('; ')}.`
          : '';
        await sendChat(
          `${processRule?.name ?? 'Процесс'} прерван.${effectText}`,
          [],
          props.chatId,
          speakerFor(participant),
        );
      }
    }
    const model = combatCardModelService.combatCardModel(
      key,
      props.characters,
      props.npcs,
      true,
      null,
      overlays.value.find((item) => item.entityKey === key) ?? null,
    );
    if (!model.effectiveVersion) throw new Error('Лист участника не найден');
    const execution = await actionExecutionService.execute({
      gameId: props.gameId,
      entityKey: key,
      version: model.effectiveVersion,
      rule,
      action,
      rules: props.rules,
      pendingEffects: pendingEffectsByEntity.value[key] ?? [],
      actionPointCost: activeActionPoints.value,
      attackerName: participant.name,
      chatId: props.chatId,
      speaker: speakerFor(participant),
      sendChat,
    });
    overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, execution.overlay);
    pendingEffectsByEntity.value = { ...pendingEffectsByEntity.value, [key]: execution.effects };
    waitDialogOpen.value = false;
    emit('overlay-changed');
    await nextTurn();
  } catch (cause) {
    waitError.value = cause instanceof Error ? cause.message : 'Не удалось выполнить ожидание';
  } finally {
    waitBusy.value = false;
  }
}

/** Передать ход следующему участнику (цикл по порядку шкалы) + уведомление в чат.
 *  При переходе от последнего участника к первому — новый раунд: номер инкрементится,
 *  постится акцентное уведомление «Новый раунд: N» (kind: highlighted), затем «Ходит Имя». */
async function bleedCurrentTurn(entityKey: string): Promise<void> {
  const overlay = overlays.value.find((item) => item.entityKey === entityKey) ?? null;
  const model = combatCardModelService.combatCardModel(
    entityKey as CombatEntityKey,
    props.characters,
    props.npcs,
    true,
    null,
    overlay,
  );
  const version = model.effectiveVersion;
  if (!version) return;
  const endurance =
    stateRuntimeEffectsService.effectiveCharacteristicValues(version, props.rules).get('endurance')?.base ?? 1;
  const next = await bloodLossService.applyTurnWoundBleed({
    version,
    endurance,
    rules: props.rules,
    mechanics: props.mechanics,
    gameId: props.gameId,
    targetKey: entityKey as CombatEntityKey,
    targetName: model.name,
    chatId: props.chatId,
    speaker: { kind: 'gm' },
    sendMessage: (content, attachments, chatId, speaker) => sendChat(content, attachments, chatId, speaker),
  });
  if (next) {
    overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, next);
    emit('overlay-changed');
  }
}

async function nextTurn(): Promise<void> {
  const data = initiative.value;
  if (!data || data.participants.length === 0) return;
  const currentIndex = data.activeIndex ?? 0;
  const currentParticipant = data.participants[currentIndex];
  if (currentParticipant) {
    await bleedCurrentTurn(currentParticipant.id);
    await clearAccumulatedDamage(currentParticipant.id);
    await refillParticipants([currentParticipant.id]);
  }
  const nextIndex = (currentIndex + 1) % data.participants.length;
  const nextParticipant = data.participants[nextIndex];
  const notifications: SystemNotification[] = [];
  let nextRound = data.round;
  if (currentIndex === data.participants.length - 1) {
    nextRound = data.round + 1;
    const round = combatThread.beginRound();
    notifications.push({ content: `Новый раунд: ${nextRound}`, kind: 'highlighted', thread: round });
  }
  if (nextParticipant) {
    const turn = combatThread.beginTurn();
    notifications.push({ content: `Ходит ${nextParticipant.name}`, kind: 'default', thread: turn });
  }
  saveAndNotify({ ...data, activeIndex: nextIndex, round: nextRound }, notifications);
}

async function clearAccumulatedDamage(entityKey: string): Promise<void> {
  const overlay = overlays.value.find((item) => item.entityKey === entityKey);
  const index = overlay?.states.findIndex((state) => {
    const rule = props.rules.find((candidate) => candidate.code === state.stateRuleCode);

    return rule?.type === 'state' && rule.code === ACCUMULATED_DAMAGE_STATE_CODE;
  });
  if (index == null || index < 0) return;

  const next = await getGameApi().removeCombatState(props.gameId, entityKey as CombatEntityKey, index);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, next);
  emit('overlay-changed');
}

function endScale(): void {
  const data = initiative.value;
  if (!data) return;
  combatThread.clearLive();
  void save({ ...data, active: false });
}

function continueScale(): void {
  const data = initiative.value;
  if (!data || props.gameStatus !== 'playing') return;
  void save({ ...data, active: true }).then(() => {
    if (props.chatId != null) combatThread.recoverFromMessages(chatStore.messagesOf(props.chatId));
  });
}

function addToBattle(id: string): void {
  const data = initiative.value;
  if (!data) return;
  const option = addOptions.value.find((item) => item.id === id);
  if (!option) return;
  addMenuOpen.value = false;
  void save({
    ...data,
    participants: [
      ...data.participants,
      { id: option.id, name: option.name, kind: option.kind, entityId: option.entityId },
    ],
  }).then(() => refillParticipants([option.id]));
}

watch(
  () => props.gameId,
  () => {
    void load();
    void loadOverlays();
  },
  { immediate: true },
);

watch(
  () => (props.chatId != null ? chatStore.messagesOf(props.chatId).length : 0),
  () => {
    if (initiative.value?.active && props.chatId != null) {
      combatThread.recoverFromMessages(chatStore.messagesOf(props.chatId));
    }
  },
);

// Правки в боевой карточке (оверлей мутировал) → перечитать оверлеи, чтобы Истощение было актуальным.
watch(
  () => props.overlayRevision,
  () => void loadOverlays(),
);

watch(
  () => props.gameStatus,
  () => void load(),
);

// Текущий ход: эмит для авто-переключения селектора «от лица кого» у владельца хода.
watch(
  () => initiative.value?.activeIndex,
  () => emit('turn', activeParticipant.value?.id ?? null),
  { immediate: true },
);

function kindIcon(kind: 'character' | 'npc'): string {
  return kind === 'npc' ? 'mdi-robot-outline' : 'mdi-account';
}
</script>

<template>
  <v-card variant="flat" class="initiative-track" border>
    <div class="initiative-track__header">
      <span class="text-subtitle-2 font-weight-medium">
        <v-icon icon="mdi-format-list-numbered" size="18" class="mr-1" />
        Инициатива
      </span>
    </div>
    <v-card-text class="initiative-track__body pa-2">
      <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-1">{{ error }}</v-alert>
      <div v-if="loading" class="d-flex justify-center pa-3">
        <v-progress-circular indeterminate width="2" size="22" color="primary" />
      </div>

      <template v-else>
        <!-- Нет шкалы: кнопка запуска (ГМ) -->
        <div v-if="!initiative || initiative.participants.length === 0" class="initiative-track__placeholder">
          <v-btn
            v-if="canEdit"
            variant="tonal"
            color="primary"
            size="small"
            block
            prepend-icon="mdi-dice-d6"
            :disabled="saving"
            @click="dialogOpen = true"
          >
            НАЧАТЬ
          </v-btn>
        </div>

        <!-- Активная шкала: порядок хода (сверху вниз) + управление -->
        <div v-else-if="initiative.active" class="initiative-track__active">
          <div class="initiative-track__list">
            <div
              v-for="(participant, index) in initiative.participants"
              :key="participant.id"
              class="initiative-track__row"
              :class="{
                'initiative-track__row--active': initiative.activeIndex === index,
                'initiative-track__row--clickable': canInspect(participant),
              }"
              @click="canInspect(participant) && emit('open-card', participant.id)"
            >
              <v-icon :size="16" class="initiative-track__row-icon">{{ kindIcon(participant.kind) }}</v-icon>
              <span class="initiative-track__row-name">{{ participant.name }}</span>
              <span v-if="canInspect(participant)" class="initiative-track__metrics">
                <span
                  v-if="actionPointsByEntity.has(participant.id)"
                  class="initiative-track__ap"
                  :title="`ОД: ${actionPointsByEntity.get(participant.id)}`"
                >
                  {{ actionPointsByEntity.get(participant.id) }} ОД
                </span>
                <span
                  v-if="exhaustionByEntity.has(participant.id)"
                  class="initiative-track__exhaustion"
                  :title="`Истощение: ${exhaustionByEntity.get(participant.id)}`"
                >
                  {{ exhaustionByEntity.get(participant.id) }}
                </span>
                <span
                  v-if="maimByEntity.has(participant.id)"
                  class="initiative-track__maim"
                  :title="`Увечья: ${maimByEntity.get(participant.id)}`"
                >
                  {{ maimByEntity.get(participant.id) }}
                </span>
              </span>
            </div>
          </div>

          <div class="initiative-track__actions">
            <v-btn
              size="small"
              variant="tonal"
              color="primary"
              block
              prepend-icon="mdi-skip-next"
              :disabled="!canPass || saving || initiative.participants.length === 0"
              @click="requestTurnPass"
            >
              Передать ход
            </v-btn>
            <v-menu v-if="canEdit" v-model="addMenuOpen" :close-on-content-click="false">
              <template #activator="{ props: menuProps }">
                <v-btn size="small" variant="tonal" color="success" block prepend-icon="mdi-plus" v-bind="menuProps">
                  Добавить
                </v-btn>
              </template>
              <v-card min-width="240" max-width="300" elevation="8" border>
                <v-card-text class="pa-2">
                  <v-list dense max-height="240">
                    <v-list-item
                      v-for="option in addOptions"
                      :key="option.id"
                      density="compact"
                      :prepend-icon="kindIcon(option.kind)"
                      :title="option.name"
                      @click="addToBattle(option.id)"
                    />
                    <div v-if="!addOptions.length" class="text-caption text-medium-emphasis pa-2 text-center">
                      Некого добавить
                    </div>
                  </v-list>
                </v-card-text>
              </v-card>
            </v-menu>
            <v-btn
              v-if="canEdit"
              size="small"
              variant="text"
              color="warning"
              block
              :disabled="saving"
              @click="endScale"
            >
              Закончить
            </v-btn>
          </div>
        </div>

        <!-- Завершена: «Продолжить» (отмена случайного «Закончить») или новый бросок -->
        <div v-else class="initiative-track__ended">
          <span class="text-caption text-medium-emphasis">Шкала завершена</span>
          <v-btn
            v-if="canEdit && gameStatus === 'playing'"
            size="small"
            variant="tonal"
            color="primary"
            block
            :disabled="saving"
            @click="continueScale"
          >
            Продолжить
          </v-btn>
          <v-btn v-if="canEdit" size="small" variant="outlined" color="primary" block @click="dialogOpen = true">
            Новый бросок
          </v-btn>
        </div>
      </template>
    </v-card-text>
  </v-card>

  <InitiativeDialog
    v-model:open="dialogOpen"
    :game-id="gameId"
    :space-id="spaceId"
    :characters="characters"
    :npcs="npcs"
    :rules="rules"
    :mechanics="mechanics"
    :chat-id="chatId"
    @saved="onInitiativeSaved"
  />

  <v-dialog v-model="waitDialogOpen" max-width="460">
    <v-card>
      <v-card-title>Передать ход</v-card-title>
      <v-card-text>
        <p>Для передачи хода будет выполнено действие «Ожидание» за {{ activeActionPoints }} ОД.</p>
        <p v-if="hasActiveProcess" class="text-warning mt-2">Активный процесс будет прерван.</p>
        <v-alert v-if="waitError" type="error" variant="tonal" density="compact" class="mt-3">
          {{ waitError }}
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="waitBusy" @click="waitDialogOpen = false">Отмена</v-btn>
        <v-btn color="primary" :loading="waitBusy" @click="confirmWaitAndPass">Ожидание и передать ход</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.initiative-track {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.initiative-track__header {
  padding: 8px 12px 2px;
  flex-shrink: 0;
}
.initiative-track__body {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.initiative-track__placeholder {
  padding: 4px 0;
}
.initiative-track__active {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  gap: 8px;
}
.initiative-track__list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.initiative-track__row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px;
  border-radius: 8px;
  border-left: 3px solid transparent;
  font-size: 13px;
  flex-shrink: 0;
}
.initiative-track__row--active {
  background: rgba(var(--v-theme-primary), 0.1);
  border-left-color: rgb(var(--v-theme-primary));
  font-weight: 600;
}
.initiative-track__row--clickable {
  cursor: pointer;
}
.initiative-track__row--clickable:hover {
  background: rgba(var(--v-theme-on-surface), 0.06);
}
.initiative-track__row-icon {
  opacity: 0.7;
  flex-shrink: 0;
}
.initiative-track__row-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.initiative-track__metrics {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.initiative-track__ap {
  color: rgb(var(--v-theme-info));
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.initiative-track__exhaustion {
  min-width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 1px solid rgb(var(--v-theme-warning));
  color: rgb(var(--v-theme-warning));
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  flex-shrink: 0;
}
.initiative-track__maim {
  min-width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 1px solid rgb(var(--v-theme-error));
  color: rgb(var(--v-theme-error));
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  flex-shrink: 0;
}
.initiative-track__actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  padding-top: 8px;
  flex-shrink: 0;
}
.initiative-track__ended {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 4px 0;
}
</style>
