<script setup lang="ts">
import { computed, inject, onMounted, onUnmounted, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useChatChannel } from '@/modules/Messages/Chat/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameParticipantCandidate } from '@/modules/Roleplay/Game/Dto/GameParticipantCandidate';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameInitiative, GameInitiativeParticipant } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import InitiativeDialog from '@/modules/Roleplay/Game/Component/InitiativeDialog.vue';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { concentrationTokenService } from '@/modules/Roleplay/Game/Service/Instance/concentrationTokenService';
import { CONCENTRATION_TOKEN_ASK_INJECT_KEY } from '@/modules/Roleplay/Game/Constant/CONCENTRATION_TOKEN_ASK_INJECT_KEY';

import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { bloodLossService } from '@/modules/Roleplay/Game/Service/Instance/bloodLossService';

import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';

import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';
import { actionExecutionService } from '@/modules/Roleplay/Game/Service/Instance/actionExecutionService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { committedActionFlowService } from '@/modules/Roleplay/Game/Service/Instance/committedActionFlowService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import {
  asActionAbilitySpec,
  asProcessAbilitySpec,
  combatActionRule,
  findRuleByRef,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { formatProcessEffect } from '@/modules/Roleplay/Game/Utils/processMessage';
import SpellSustainDialog from '@/modules/Roleplay/Game/Component/SpellSustainDialog.vue';
import CommittedActionContinueDialog from '@/modules/Roleplay/Game/Component/CommittedActionContinueDialog.vue';
import { electrochargeService } from '@/modules/Roleplay/Game/Service/Instance/electrochargeService';
import { activeSpellService } from '@/modules/Roleplay/Game/Service/Instance/activeSpellService';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import { spellCastOptionsService } from '@/modules/Roleplay/Game/Service/Instance/spellCastOptionsService';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellDeviationService } from '@/modules/Roleplay/Game/Service/Instance/spellDeviationService';
import { formatSustainDropMessage } from '@/modules/Roleplay/Game/Utils/attackDamageMessage';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';

import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';

type SystemNotification = { content: string; kind: ChatMessage['kind']; thread?: ChatThreadRef };

/**
 * Шкала инициативы (ТР §8 «Чат игры»). Жизненный цикл: «Инициатива» (окно проверки, ГМ) →
 * активная шкала (порядок + «Передать ход»/«Добавить»/«Закончить») → завершена («Продолжить», только пока сессия запущена).
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
  runtimeProjections?: Record<CombatEntityKey, GameRuntimeEntityProjection>;
  /** Правила ревизии игры (характеристики + дефолты «Бросок»). */
  rules: Rule[];
  /** Механики ревизии (броски инициативы через RollEngine). */
  mechanics: Mechanic[];
  /** Счётчик мутаций боевых оверлеев: при изменении — перечитать оверлеи (Истощение). */
  overlayRevision: number;
  /** Создать session admission перед первым броском инициативы. */
  ensureSession?: () => Promise<void>;
  /** Загрузить full projections выбранных новых участников одним batch-запросом. */
  ensureRuntimeProjections?: (entityKeys: string[]) => Promise<void>;
  sessionRunning: boolean;
}>();

const emit = defineEmits<{
  turn: [participantId: string | null];
  /** Открыть боевую карточку участника (entityKey: `character:{id}` | `npc:{id}`). */
  'open-card': [entityKey: string];
  'overlay-changed': [];
  participants: [keys: string[]];
  'runtime-projections': [projections: GameRuntimeEntityProjection[]];
}>();

const { currentUser } = useCurrentUser();
const chatStore = useChatChannel();
const combatThread = useCombatChatThread(() => props.gameId);
const sendChat = combatChatSendService.sendCombatChat(props.gameId);
const askTokenSpend = inject(CONCENTRATION_TOKEN_ASK_INJECT_KEY, undefined);

const initiative = ref<GameInitiative | null>(null);
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);

const dialogOpen = ref(false);
const addMenuOpen = ref(false);
const addCandidateQuery = ref('');
const addCandidates = ref<GameParticipantCandidate[]>([]);
const addCandidateNextCursor = ref<string | null>(null);
const addCandidateLoading = ref(false);
const selectedAddEntityKeys = ref<string[]>([]);
const addCandidateError = ref<string | null>(null);
const addProjectionLoading = ref(false);
let addSearchTimer: ReturnType<typeof setTimeout> | null = null;
let addSearchSequence = 0;
const waitDialogOpen = ref(false);
const waitBusy = ref(false);
const waitError = ref<string | null>(null);
const sustainOpen = ref(false);
const sustainSpell = ref<ActiveSpell | null>(null);
const { keywords, fetchTags } = useKeywords();
onMounted(() => {
  if (keywords.value.length === 0) {
    void fetchTags();
  }
});
onUnmounted(() => {
  if (addSearchTimer) clearTimeout(addSearchTimer);
});
const processSessions = ref<Record<CombatEntityKey, ProcessSession>>({});
const committedSessions = ref<Record<CombatEntityKey, CommittedActionSession>>({});
const pendingEffectsByEntity = ref<Record<CombatEntityKey, PendingActionEffect[]>>({});
const committedContinueOpen = ref(false);
const committedContinueBusy = ref(false);
const committedContinueSession = ref<CommittedActionSession | null>(null);
const committedContinueSpeaker = ref<ChatSpeaker | null>(null);
const committedContinueActorName = ref('');

// Оверлеи боевых изменений участников: для текущего Истощения (сумма состояния 'exhaustion').
const overlays = ref<GameCombatOverlay[]>([]);

function runtimeProjectionOf(key: CombatEntityKey): GameRuntimeEntityProjection | null {
  return props.runtimeProjections?.[key] ?? null;
}

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
  () => combatActionRule(props.rules, 'wait') ?? null,
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

  return addCandidates.value
    .filter((candidate) => !existing.has(candidate.entityKey))
    .map((candidate) => ({
      id: candidate.entityKey,
      name: candidate.name,
      kind: candidate.kind,
      entityId: candidate.id,
    }));
});

async function loadAddCandidates(query = '', append = false): Promise<void> {
  const sequence = ++addSearchSequence;
  addCandidateLoading.value = true;
  addCandidateError.value = null;
  try {
    const result = await getGameApi().getParticipantCandidates({
      gameId: props.gameId,
      query,
      cursor: append ? (addCandidateNextCursor.value ?? undefined) : undefined,
      limit: 50,
    });
    if (sequence !== addSearchSequence) return;
    const selectedKeys = new Set(selectedAddEntityKeys.value);
    const retained = append
      ? [...addCandidates.value]
      : addCandidates.value.filter((candidate) => selectedKeys.has(candidate.entityKey));
    addCandidates.value = [
      ...retained,
      ...result.items.filter((candidate) => !retained.some((item) => item.entityKey === candidate.entityKey)),
    ];
    addCandidateNextCursor.value = result.nextCursor;
  } catch (caught) {
    if (sequence === addSearchSequence) {
      addCandidateError.value = caught instanceof Error ? caught.message : 'Не удалось загрузить участников';
    }
  } finally {
    if (sequence === addSearchSequence) addCandidateLoading.value = false;
  }
}

function onAddCandidateQueryChange(query: string): void {
  addCandidateQuery.value = query;
  addCandidateNextCursor.value = null;
  if (addSearchTimer) clearTimeout(addSearchTimer);
  addSearchTimer = setTimeout(() => {
    void loadAddCandidates(query);
  }, 250);
}

watch(addMenuOpen, (isOpen) => {
  if (isOpen) {
    addCandidateNextCursor.value = null;
    void loadAddCandidates(addCandidateQuery.value);
  } else {
    selectedAddEntityKeys.value = [];
  }
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    const [nextInitiative, nextProcesses, nextPendingEffects, nextCommitted] = await Promise.all([
      getGameApi().getInitiative(props.gameId),
      getGameApi()
        .getProcessSessions(props.gameId)
        .catch(() => ({})),
      getGameApi()
        .getPendingActionEffects(props.gameId)
        .catch(() => ({})),
      getGameApi()
        .getCommittedActionSessions(props.gameId)
        .catch(() => ({})),
    ]);
    initiative.value = nextInitiative;
    processSessions.value = nextProcesses;
    pendingEffectsByEntity.value = nextPendingEffects;
    committedSessions.value = nextCommitted;
    emit(
      'participants',
      (initiative.value?.active ? initiative.value.participants : []).map((participant) => participant.id),
    );
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
      runtimeProjectionOf(participant.id as CombatEntityKey),
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
      runtimeProjectionOf(participant.id as CombatEntityKey),
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
const hasActiveCommitted = computed(() => {
  const participantKey = activeParticipantKey.value;

  return participantKey ? Boolean(committedSessions.value[participantKey]) : false;
});
const committedContinueName = computed(() => {
  const session = committedContinueSession.value;
  if (!session) return '';

  return findRuleByRef(props.rules, session.actionRuleCode)?.name ?? session.actionRuleCode;
});
const committedContinueAvailableOd = computed(() => {
  const key = committedContinueSession.value?.entityKey;

  return key ? (actionPointsByEntity.value.get(key) ?? 0) : 0;
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
      runtimeProjectionOf(participant.id as CombatEntityKey),
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
  const model = combatCardModelService.combatCardModel(
    entityKey,
    props.characters,
    props.npcs,
    true,
    null,
    overlay,
    runtimeProjectionOf(entityKey),
  );
  const version = model.effectiveVersion;
  if (!version) return;
  const ap = combatCardModelService.combatActionPoints(version, props.rules);
  if (!ap) return;
  const rule = combatCardModelService.turnResourceRule(props.rules);
  if (!rule) return;
  const resource = version.resources.find((item) => item.ruleCode === rule.code);
  if (!resource) return;
  await getGameApi().setCombatResource(props.gameId, entityKey, resource.ruleCode, {
    base: ap.max,
    size: resource.current.size,
  });
}

async function refillConcentration(entityKey: CombatEntityKey): Promise<void> {
  const overlay = overlays.value.find((item) => item.entityKey === entityKey) ?? null;
  const model = combatCardModelService.combatCardModel(
    entityKey,
    props.characters,
    props.npcs,
    true,
    null,
    overlay,
    runtimeProjectionOf(entityKey),
  );
  const version = model.effectiveVersion;
  if (!version) return;
  await concentrationTokenService.refillIfUnused(getGameApi(), props.gameId, entityKey, version, overlay, props.rules);
}

async function refillParticipants(keys: string[]): Promise<void> {
  await loadOverlays();
  for (const key of keys) {
    try {
      await refillActionPoints(key as CombatEntityKey);
    } catch {
      // Нет ОД на листе — шкалу не блокируем.
    }
    try {
      await refillConcentration(key as CombatEntityKey);
    } catch {
      // Нет жетонов на листе — шкалу не блокируем.
    }
  }
  await loadOverlays();
  emit('overlay-changed');
}

async function onInitiativeSaved(projections: GameRuntimeEntityProjection[]): Promise<void> {
  emit('runtime-projections', projections);
  await load();
  const keys = initiative.value?.participants.map((participant) => participant.id) ?? [];
  await props.ensureRuntimeProjections?.(keys);
  await refillParticipants(keys);
}

async function save(next: GameInitiative): Promise<boolean> {
  saving.value = true;
  error.value = null;
  try {
    initiative.value = await getGameApi().saveInitiative(props.gameId, next);
    emit(
      'participants',
      (initiative.value.active ? initiative.value.participants : []).map((participant) => participant.id),
    );

    return true;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось сохранить шкалу';

    return false;
  } finally {
    saving.value = false;
  }
}

function saveAndNotify(next: GameInitiative, notifications: SystemNotification[]): void {
  void save(next).then((saved) => {
    if (!saved) return;
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
    const committed = committedSessions.value[key];
    if (committed) {
      await committedActionFlowService.abort(
        props.gameId,
        committed,
        props.rules,
        props.chatId,
        speakerFor(participant),
        sendChat,
      );
      const nextCommitted = { ...committedSessions.value };
      delete nextCommitted[key];
      committedSessions.value = nextCommitted;
    }
    const model = combatCardModelService.combatCardModel(
      key,
      props.characters,
      props.npcs,
      true,
      null,
      overlays.value.find((item) => item.entityKey === key) ?? null,
      runtimeProjectionOf(key),
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
    runtimeProjectionOf(entityKey as CombatEntityKey),
  );
  const version = model.effectiveVersion;
  if (!version) return;
  const endurance =
    stateRuntimeEffectsService.effectiveCharacteristicValues(version, props.rules).get('endurance')?.base ?? 1;
  const next = await bloodLossService.applyTurnWoundBleed({
    version,
    overlay,
    endurance,
    rules: props.rules,
    mechanics: props.mechanics,
    gameId: props.gameId,
    targetKey: entityKey as CombatEntityKey,
    targetName: model.name,
    chatId: props.chatId,
    speaker: { kind: 'gm' },
    sendMessage: (content, attachments, chatId, speaker) => sendChat(content, attachments, chatId, speaker),
    askTokenSpend,
  });
  if (next) emit('overlay-changed');
  await decayCoreDeviation(entityKey as CombatEntityKey);
}

async function decayCoreDeviation(entityKey: CombatEntityKey): Promise<void> {
  const overlay = overlays.value.find((item) => item.entityKey === entityKey) ?? null;
  const model = combatCardModelService.combatCardModel(
    entityKey,
    props.characters,
    props.npcs,
    true,
    null,
    overlay,
    runtimeProjectionOf(entityKey),
  );
  const version = model.effectiveVersion;
  if (!version) {
    return;
  }
  const patches = spellDeviationService.decayPatches(version.states, props.rules);
  if (patches.length === 0) {
    return;
  }
  for (const patch of [...patches].reverse()) {
    if (patch.next == null) {
      await getGameApi().removeCombatState(props.gameId, entityKey, patch.index);
    } else {
      await getGameApi().replaceCombatState(props.gameId, entityKey, patch.index, patch.next);
    }
  }
  emit('overlay-changed');
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
  if (nextParticipant) {
    await promptSustains(nextParticipant.id as CombatEntityKey, nextRound);
    await promptCommittedContinue(nextParticipant);
  }
}

async function promptSustains(participantId: CombatEntityKey, round: number): Promise<void> {
  const spells = await getGameApi().getActiveSpells(props.gameId);
  for (const spell of spells) {
    if (!activeSpellService.shouldPromptSustain(spell, round, participantId)) {
      continue;
    }
    const overlay = overlays.value.find((item) => item.entityKey === spell.casterKey) ?? null;
    const version = combatCardModelService.combatCardModel(
      spell.casterKey,
      props.characters,
      props.npcs,
      props.canEdit,
      currentUser.value?.id ?? null,
      overlay,
      runtimeProjectionOf(spell.casterKey),
    ).effectiveVersion;
    if (!activeSpellService.isSourceAvailable(spell.sourceKey, version ?? null)) {
      await dropSustain(spell, true);
      continue;
    }
    sustainSpell.value = spell;
    sustainOpen.value = true;
    await new Promise<void>((resolve) => {
      const stop = watch(sustainOpen, (open) => {
        if (!open) {
          stop();
          resolve();
        }
      });
    });
  }
}

function actionOptionForCommitted(session: CommittedActionSession): CombatActionOption {
  const rule = findRuleByRef(props.rules, session.actionRuleCode);
  if (!rule) throw new Error('Правило действия не найдено в текущей ревизии');
  const spec = asActionAbilitySpec(rule);

  return {
    ruleCode: rule.code,
    code: rule.code,
    name: rule.name,
    odCost: session.totalOd,
    effects: spec ? actionEffectService.effectsOf(rule) : [],
    operations: spec?.operations,
    isAttack: false,
  };
}

async function promptCommittedContinue(participant: GameInitiativeParticipant): Promise<void> {
  const participantId = participant.id as CombatEntityKey;
  const sessions: Record<CombatEntityKey, CommittedActionSession> = await getGameApi()
    .getCommittedActionSessions(props.gameId)
    .catch(() => ({}));
  committedSessions.value = sessions;
  const session = sessions[participantId];
  if (!session) return;
  committedContinueSession.value = session;
  committedContinueSpeaker.value = speakerFor(participant);
  committedContinueActorName.value = participant.name;
  committedContinueOpen.value = true;
  await new Promise<void>((resolve) => {
    const stop = watch(committedContinueOpen, (open) => {
      if (!open) {
        stop();
        resolve();
      }
    });
  });
}

async function continueCommittedTurn(): Promise<void> {
  const session = committedContinueSession.value;
  const speaker = committedContinueSpeaker.value;
  if (!session || !speaker) return;
  committedContinueBusy.value = true;
  error.value = null;
  try {
    await loadOverlays();
    const overlay = overlays.value.find((item) => item.entityKey === session.entityKey) ?? null;
    const model = combatCardModelService.combatCardModel(
      session.entityKey,
      props.characters,
      props.npcs,
      true,
      null,
      overlay,
      runtimeProjectionOf(session.entityKey),
    );
    if (!model.effectiveVersion) throw new Error('Лист участника не найден');
    const available = combatCardModelService.combatActionPoints(model.effectiveVersion, props.rules)?.current ?? 0;
    const next = await committedActionFlowService.continueTurn({
      gameId: props.gameId,
      session,
      version: model.effectiveVersion,
      rules: props.rules,
      mechanics: props.mechanics,
      available,
      action: actionOptionForCommitted(session),
      chatId: props.chatId,
      speaker,
      attackerName: committedContinueActorName.value,
      sendChat,
      pendingEffects: pendingEffectsByEntity.value[session.entityKey] ?? [],
    });
    const nextMap = { ...committedSessions.value };
    if (next) nextMap[session.entityKey] = next;
    else delete nextMap[session.entityKey];
    committedSessions.value = nextMap;
    await loadOverlays();
    emit('overlay-changed');
    committedContinueOpen.value = false;
    committedContinueSession.value = null;
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось продолжить действие';
  } finally {
    committedContinueBusy.value = false;
  }
}

async function abortCommittedTurn(): Promise<void> {
  const session = committedContinueSession.value;
  const speaker = committedContinueSpeaker.value;
  if (!session || !speaker) return;
  committedContinueBusy.value = true;
  error.value = null;
  try {
    await committedActionFlowService.abort(props.gameId, session, props.rules, props.chatId, speaker, sendChat);
    const nextMap = { ...committedSessions.value };
    delete nextMap[session.entityKey];
    committedSessions.value = nextMap;
    committedContinueOpen.value = false;
    committedContinueSession.value = null;
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось сорвать действие';
  } finally {
    committedContinueBusy.value = false;
  }
}

async function dropSustain(spell: ActiveSpell, lostSource: boolean): Promise<void> {
  const states =
    runtimeProjectionOf(spell.casterKey)?.version?.states ??
    combatCardModelService.combatCardModel(
      spell.casterKey,
      props.characters,
      props.npcs,
      props.canEdit,
      currentUser.value?.id ?? null,
      overlays.value.find((item) => item.entityKey === spell.casterKey) ?? null,
      runtimeProjectionOf(spell.casterKey),
    ).effectiveVersion?.states ??
    [];
  for (const index of electrochargeService.boundIndices(states, spell.id)) {
    await getGameApi().removeCombatState(props.gameId, spell.casterKey, index);
  }
  emit('overlay-changed');
  await getGameApi().dropActiveSpell(props.gameId, spell.id);
  sustainOpen.value = false;
  sustainSpell.value = null;
  if (props.chatId === null) {
    return;
  }
  const rule = findRuleByRef(props.rules, spell.spellCode);
  const model = combatCardModelService.combatCardModel(
    spell.casterKey,
    props.characters,
    props.npcs,
    props.canEdit,
    currentUser.value?.id ?? null,
    overlays.value.find((item) => item.entityKey === spell.casterKey) ?? null,
    runtimeProjectionOf(spell.casterKey),
  );
  await sendChat(
    formatSustainDropMessage({
      casterKey: spell.casterKey,
      casterName: model.name,
      spellRuleCode: spell.spellCode,
      spellName: rule?.name ?? spell.spellCode,
      lostSource,
      rules: props.rules,
    }),
    [],
    props.chatId,
    { kind: 'gm' },
  );
}

async function continueSustain(power: DimensionalNumberValue): Promise<void> {
  const spell = sustainSpell.value;
  if (!spell) {
    return;
  }
  const overlay = overlays.value.find((item) => item.entityKey === spell.casterKey) ?? null;
  const version = combatCardModelService.combatCardModel(
    spell.casterKey,
    props.characters,
    props.npcs,
    props.canEdit,
    currentUser.value?.id ?? null,
    overlay,
    runtimeProjectionOf(spell.casterKey),
  ).effectiveVersion;
  const overview = version ? characterOverviewService.build(version, props.rules) : null;
  const pathRule = spell.pathCode ? findRuleByRef(props.rules, spell.pathCode) : null;
  const pathSpec = pathRule?.spec?.type === 'magic_path' ? (pathRule.spec as MagicPathSpec) : null;
  const maxPower = spellCastOptionsService.defaultUsedPower(
    overview,
    version?.states ?? [],
    spell.sourceKey,
    pathSpec?.power_characteristic_code ?? null,
    props.rules,
  );
  const clamped = spellCastDifficultyService.clampToAtMost(power, maxPower);
  const spec = spellCastDifficultyService.asSpellAbilitySpec(findRuleByRef(props.rules, spell.spellCode));
  const requiredPower = spec
    ? spellCastDifficultyService.resolveSpellValue(spec.spell.power, spell.parameterValues)
    : { base: 0, size: 0 };
  await getGameApi().upsertActiveSpell(
    props.gameId,
    activeSpellService.withSustainPower(spell, clamped, requiredPower),
  );
  const chargeSpec = electrochargeService.chargeSpec(spell.spellCode, props.rules);
  if (chargeSpec && version) {
    const cap = electrochargeService.cap(spell.spellCode, version.abilities, props.rules, keywords.value);
    const next = electrochargeService.grant(version.states, spell.id, chargeSpec, cap);
    const index = electrochargeService.boundIndex(version.states, chargeSpec.state_code, spell.id);
    if (index >= 0) {
      await getGameApi().replaceCombatState(props.gameId, spell.casterKey, index, next);
    } else {
      await getGameApi().addCombatState(props.gameId, spell.casterKey, next);
    }
    emit('overlay-changed');
  }
  sustainOpen.value = false;
  sustainSpell.value = null;
}

async function clearAccumulatedDamage(entityKey: string): Promise<void> {
  const states = runtimeProjectionOf(entityKey as CombatEntityKey)?.version?.states ?? [];
  const rule = attackDamageService.accumulatedDamageRule(props.rules);
  const index = rule ? states.findIndex((state) => state.stateRuleCode === rule.code) : -1;
  if (index == null || index < 0) return;

  await getGameApi().removeCombatState(props.gameId, entityKey as CombatEntityKey, index);
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
  if (!data || !props.sessionRunning) return;
  void save({ ...data, active: true }).then(() => {
    if (props.chatId != null) combatThread.recoverFromMessages(chatStore.messagesOf(props.chatId));
  });
}

async function addToBattle(): Promise<void> {
  const data = initiative.value;
  const options = selectedAddEntityKeys.value.flatMap((entityKey) => {
    const option = addOptions.value.find((candidate) => candidate.id === entityKey);

    return option ? [option] : [];
  });
  if (!data || options.length === 0) return;

  addProjectionLoading.value = true;
  error.value = null;
  try {
    await props.ensureRuntimeProjections?.(options.map((option) => option.id));
    const unresolved = options.filter(
      (option) => runtimeProjectionOf(option.id as CombatEntityKey)?.projectionLevel !== 'full',
    );
    if (unresolved.length > 0) {
      throw new Error(`Не удалось загрузить листы: ${unresolved.map((option) => option.name).join(', ')}`);
    }

    const saved = await save({
      ...data,
      participants: [
        ...data.participants,
        ...options.map((option) => ({
          id: option.id,
          name: option.name,
          kind: option.kind,
          entityId: option.entityId,
        })),
      ],
    });
    if (!saved) return;

    const addedKeys = options.map((option) => option.id);
    await refillParticipants(addedKeys);
    if (props.chatId !== null) {
      for (const option of options) {
        await chatStore.postSystemMessage(
          `${option.name} присоединяется к шкале инициативы.`,
          props.chatId,
          'default',
          combatThread.stamp(),
        );
      }
    }
    selectedAddEntityKeys.value = [];
    addMenuOpen.value = false;
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось добавить участников';
  } finally {
    addProjectionLoading.value = false;
  }
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
  () => props.sessionRunning,
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
                  <v-autocomplete
                    v-model="selectedAddEntityKeys"
                    v-model:search="addCandidateQuery"
                    :items="addOptions"
                    item-title="name"
                    item-value="id"
                    label="Участник"
                    multiple
                    chips
                    closable-chips
                    density="compact"
                    variant="outlined"
                    hide-details
                    clearable
                    :loading="addCandidateLoading"
                    no-data-text="Некого добавить"
                    @update:search="onAddCandidateQueryChange"
                  />
                  <v-btn
                    v-if="addCandidateNextCursor"
                    size="small"
                    variant="text"
                    :loading="addCandidateLoading"
                    :disabled="addCandidateLoading"
                    @click="loadAddCandidates(addCandidateQuery, true)"
                  >
                    Загрузить ещё участников
                  </v-btn>
                  <v-alert v-if="addCandidateError" type="error" variant="tonal" density="compact" class="mt-2">
                    <div class="d-flex align-center ga-2">
                      <span>{{ addCandidateError }}</span>
                      <v-btn size="small" variant="text" @click="loadAddCandidates(addCandidateQuery)">
                        Повторить
                      </v-btn>
                    </div>
                  </v-alert>
                  <v-btn
                    class="mt-2"
                    size="small"
                    color="success"
                    variant="tonal"
                    block
                    :disabled="
                      selectedAddEntityKeys.length === 0 || addCandidateLoading || addProjectionLoading || saving
                    "
                    :loading="addProjectionLoading"
                    @click="addToBattle"
                  >
                    Добавить выбранных
                  </v-btn>
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
            v-if="canEdit && sessionRunning"
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
    :rules="rules"
    :mechanics="mechanics"
    :chat-id="chatId"
    :ensure-session="ensureSession"
    :cached-projections="runtimeProjections"
    @saved="onInitiativeSaved"
  />

  <v-dialog v-model="waitDialogOpen" max-width="460">
    <v-card>
      <v-card-title>Передать ход</v-card-title>
      <v-card-text>
        <p>Для передачи хода будет выполнено действие «Ожидание» за {{ activeActionPoints }} ОД.</p>
        <p v-if="hasActiveProcess" class="text-warning mt-2">Активный процесс будет прерван.</p>
        <p v-if="hasActiveCommitted" class="text-warning mt-2">Незавершённое действие будет сорвано, ОД не вернутся.</p>
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
  <SpellSustainDialog
    :open="sustainOpen"
    :spell="sustainSpell"
    :spell-name="
      sustainSpell ? (findRuleByRef(props.rules, sustainSpell.spellCode)?.name ?? sustainSpell.spellCode) : ''
    "
    :max-power="sustainSpell ? { base: 5, size: 1 } : { base: 3, size: 0 }"
    @update:open="sustainOpen = $event"
    @continue="continueSustain"
    @drop="sustainSpell && dropSustain(sustainSpell, false)"
  />
  <CommittedActionContinueDialog
    :open="committedContinueOpen"
    :session="committedContinueSession"
    :action-name="committedContinueName"
    :available-od="committedContinueAvailableOd"
    :busy="committedContinueBusy"
    @update:open="committedContinueOpen = $event"
    @continue="continueCommittedTurn"
    @abort="abortCommittedTurn"
  />
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
