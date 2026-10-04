<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import { computed, onUnmounted, provide, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { getGameApi, getGameRealtimePort } from '@/modules/Roleplay/Game/init';
import { getMechanicApi } from '@/modules/Roleplay/Mechanic/init';
import { gameMembershipEligibilityService } from '@/modules/Roleplay/Game/Service/Instance/gameMembershipEligibilityService';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';

import { gameStatusTransitionsService } from '@/modules/Roleplay/Game/Service/Instance/gameStatusTransitionsService';

import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameStateSnapshot } from '@/modules/Roleplay/Game/Dto/GameStateSnapshot';
import type { GameLifecycleResult } from '@/modules/Roleplay/Game/Dto/GameLifecycleResult';
import type { ChatSpeakerOption } from '@/modules/Messages/Chat/Dto/ChatSpeakerOption';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterModerationProjection } from '@/modules/Roleplay/Game/Dto/CharacterModerationProjection';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameNpcSummary } from '@/modules/Roleplay/Game/Dto/GameNpcSummary';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import { ChatThread, chatInlineRendererContext } from '@/modules/Messages/Chat/init';
import InitiativeTrack from '@/modules/Roleplay/Game/Component/InitiativeTrack.vue';
import CombatQuickRolls from '@/modules/Roleplay/Game/Component/CombatQuickRolls.vue';
import CombatCardPanel from '@/modules/Roleplay/Game/Component/Detail/CombatCardPanel.vue';
import type { SpellCastLaunchContext } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastLaunchContext';
import type { ActionLaunchHint } from '@/modules/Roleplay/Game/Dto/ActionLaunchHint';
import CheckLaunchDialog from '@/modules/Roleplay/Game/Component/CheckLaunchDialog.vue';
import HitLaunchDialog from '@/modules/Roleplay/Game/Component/HitLaunchDialog.vue';
import ActionLaunchDialog from '@/modules/Roleplay/Game/Component/ActionLaunchDialog.vue';
import AttackLaunchDialog from '@/modules/Roleplay/Game/Component/AttackLaunchDialog.vue';
import SpellCastDialog from '@/modules/Roleplay/Game/Component/SpellCastDialog.vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';

import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { ProcessActionContext } from '@/modules/Roleplay/Game/Dto/ProcessActionContext';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { AttackAction } from '@/modules/Roleplay/Game/Dto/AttackAction';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';
import { useConcentrationTokenAsk } from '@/modules/Roleplay/Game/Composables/useConcentrationTokenAsk';
import ConcentrationTokenAskDialog from '@/modules/Roleplay/Game/Component/ConcentrationTokenAskDialog.vue';
import { CONCENTRATION_TOKEN_ASK_INJECT_KEY } from '@/modules/Roleplay/Game/Constant/CONCENTRATION_TOKEN_ASK_INJECT_KEY';
import { combatChatFoldService } from '@/modules/Roleplay/Game/Service/Instance/combatChatFoldService';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';

const props = defineProps<{
  /** Активна ли вкладка: чат монтируется только при открытии (D7 — освобождает глобальный чат при уходе). */
  active: boolean;
  detail: GameDetail;
  canEdit: boolean;
}>();

const { currentUser } = useCurrentUser();
const store = useGameStore();
const spaceRevision = useSpaceRevision();

const chatId = computed(() => props.detail.gameChatId);
const gameId = computed(() => props.detail.game.id);
const combatThread = useCombatChatThread(gameId);
const {
  open: concentrationAskOpen,
  maxSpend: concentrationAskMax,
  remaining: concentrationAskRemaining,
  amount: concentrationAskAmount,
  askTokenSpend,
  confirm: confirmConcentrationAsk,
  skip: skipConcentrationAsk,
} = useConcentrationTokenAsk();
provide(CONCENTRATION_TOKEN_ASK_INJECT_KEY, (input) => askTokenSpend(input.maxSpend, input.remaining));
const messageThread = computed(() => combatThread.stamp());
const liveFoldIds = combatThread.liveIds;

function buildCombatChatFolds(messages: ChatMessage[]): ChatFoldChild[] {
  return combatChatFoldService.buildCombatChatFolds(messages);
}

// Кнопки статуса — в глобальный топбар (#editor-actions), видны только на этой вкладке.
const statusUpdating = ref(false);
const statusError = ref<string | null>(null);
const statusNotice = ref<string | null>(null);

const showStartGame = computed(
  () =>
    props.canEdit &&
    gameStatusTransitionsService.canStartGame(props.detail.game.status) &&
    !props.detail.game.sessionRunning,
);
const showStopSession = computed(
  () => props.canEdit && gameStatusTransitionsService.canStopSession(props.detail.game.sessionRunning),
);

async function ensureSessionForInitiative(): Promise<void> {
  if (!props.canEdit) throw new Error('Только ведущий может запустить игровую сессию');

  if (!gameStateSnapshot.value?.session && gameStatusTransitionsService.canStartGame(props.detail.game.status)) {
    await startSession();
    if (statusError.value) throw new Error(statusError.value);
  }

  if (!gameStateSnapshot.value?.session) {
    const lifecycleResult = await getGameApi().startGameSession({
      commandId: createRandomId(),
      commandType: 'startSession',
      gameId: gameId.value,
      sessionId: null,
      battleId: null,
      participantAdmission: 'currentEligible',
      payload: {},
    });
    if (lifecycleResult.kind === 'conflict') {
      throw new Error(lifecycleResult.conflict.code);
    }
    gameStateSnapshot.value = lifecycleResult.snapshot;
  }
  if (!gameStateSnapshot.value.session) throw new Error('Игровая session не создана');
  if (gameStateSnapshot.value.battle) return;

  const battleResult = await getGameApi().startGameBattle({
    commandId: createRandomId(),
    commandType: 'startBattle',
    gameId: gameId.value,
    sessionId: gameStateSnapshot.value.session.sessionId,
    battleId: null,
    expectedSessionStateVersion: gameStateSnapshot.value.session.sessionStateVersion,
    payload: {},
  });
  if (battleResult.kind === 'conflict') throw new Error(battleResult.conflict.code);
  gameStateSnapshot.value = battleResult.snapshot;
}

async function startSession(): Promise<void> {
  statusUpdating.value = true;
  statusError.value = null;
  statusNotice.value = null;
  try {
    const lifecycleResult: GameLifecycleResult = await getGameApi().startGameSession({
      commandId: createRandomId(),
      commandType: 'startSession',
      gameId: gameId.value,
      sessionId: null,
      battleId: null,
      participantAdmission: 'currentEligible',
      payload: {},
    });
    if (lifecycleResult.kind === 'conflict') {
      throw new Error(lifecycleResult.conflict.code);
    }
    if (lifecycleResult.status === 'already_active') {
      statusNotice.value = 'Сессия уже была запущена. Используется существующее состояние сессии.';
    }
    gameStateSnapshot.value = lifecycleResult.snapshot;
    store.applyGameUpdate(await getGameApi().getGame(gameId.value));
  } catch (e) {
    statusError.value = e instanceof Error ? e.message : 'Не удалось начать сессию';
  } finally {
    statusUpdating.value = false;
  }
}

async function stopSession(): Promise<void> {
  statusUpdating.value = true;
  statusError.value = null;
  statusNotice.value = null;
  try {
    const updated = await getGameApi().stopGameSession(gameId.value);
    store.applyGameUpdate(updated);
    gameStateSnapshot.value = await getGameApi().getGameStateSnapshot(gameId.value);
  } catch (e) {
    statusError.value = e instanceof Error ? e.message : 'Не удалось остановить сессию';
  } finally {
    statusUpdating.value = false;
  }
}

// Ревизия игры: правила и механики → контекст чата (чипы [[rule:...]], «Вставить ссылку»,
// броски через RollEngine). Собирается общим buildChatRulesContext.
const revisionRules = ref<Rule[]>([]);
const hitCheckCode = computed(() => checkResolutionService.firstCheckCode(revisionRules.value, 'hit_check'));
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
        .filter(
          (membership) =>
            membership.membershipStatus === 'active' &&
            !gameMembershipEligibilityService.sheetNeedsModeration(
              membership.approvedCharacterVersion,
              moderationByCharacterId.value[membership.characterId]?.actualCharacterVersion ?? null,
            ),
        )
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
      const result = await getGameApi().getNpcSummaries({
        gameId: gameId.value,
        query,
        status: 'active',
        limit: 50,
      });

      return result.items.map((npc) => ({ value: `${npc.id},${npc.name}`, label: npc.name }));
    },
  },
]);

const rendererContext = computed(() => chatInlineRendererContext(rulesContext.value));

const processAttachments = (attachments: ChatAttachment[]): ChatAttachment[] =>
  rulesContext.value.processAttachments(attachments);

const memberships = ref<GameCharacterMembership[]>([]);
const actualById = ref<Record<number, CharacterVersion>>({});
const moderationByCharacterId = ref<Record<number, CharacterModerationProjection>>({});
const npcs = ref<GameNpc[]>([]);
const runtimeByKey = ref<Record<CombatEntityKey, GameRuntimeEntityProjection>>({});
const gameStateSnapshot = ref<GameStateSnapshot | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);
let loadSequence = 0;
const projectionSequences = new Map<CombatEntityKey, number>();
const staleNpcProjectionKeys = new Set<CombatEntityKey>();
const pendingNpcProjectionKeys = new Set<CombatEntityKey>();
let projectionEpoch = 0;
let realtimeCursor = 0;
let stopRealtimeSubscription: (() => void) | null = null;
let realtimeApplyChain = Promise.resolve();
let realtimeSyncRequired = false;
let realtimeRecoveryPromise: Promise<void> | null = null;

const runtimeParticipantKeys = computed(() => new Set(gameStateSnapshot.value?.session?.participantEntityKeys ?? []));

const eligibleMemberships = computed(() => {
  const isPlaying = props.detail.game.sessionRunning;
  const hasRuntimeSession = Boolean(gameStateSnapshot.value?.session);

  return memberships.value.filter((membership) => {
    const actual = moderationByCharacterId.value[membership.characterId]?.actualCharacterVersion ?? null;
    if (isPlaying) {
      return (
        gameMembershipEligibilityService.isActiveSessionParticipant({
          membershipStatus: membership.membershipStatus,
          sessionParticipant: hasRuntimeSession
            ? runtimeParticipantKeys.value.has(`character:${membership.characterId}`)
            : false,
          returned: membership.reviewState === 'returned',
        }) && !gameMembershipEligibilityService.sheetNeedsModeration(membership.approvedCharacterVersion, actual)
      );
    }

    return gameMembershipEligibilityService.canStartSession({
      membershipStatus: membership.membershipStatus,
      returned: membership.reviewState === 'returned',
      approved: membership.approvedCharacterVersion,
      actual,
      gameSpaceCode: props.detail.game.spaceCode,
      gameRulesRevision: props.detail.game.rulesRevision,
      needsFix: false,
    });
  });
});

// Макросы быстрых бросков per entityKey (CD-8): звёздочка в карточке и блок в сайдбаре.
const quickRolls = ref<Record<string, string[]>>({});

// «От лица кого» писать: игрок — только свои approved-персонажи; ведущий — роль ведущего,
// свои approved-персонажи и НПС игры (ТР §8 «Чат игры»).
const speakerOptions = computed<ChatSpeakerOption[]>(() => {
  const user = currentUser.value;
  if (!user) return [];
  const ownCharacters = memberships.value
    .filter((membership) =>
      gameMembershipEligibilityService.canSpeakAsCharacter({
        membershipStatus: membership.membershipStatus,
        characterOwnerId: membership.characterOwnerId,
        currentUserId: user.id,
        approved: membership.approvedCharacterVersion,
        actual: moderationByCharacterId.value[membership.characterId]?.actualCharacterVersion ?? null,
      }),
    )
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
  const requestSequence = ++loadSequence;
  const requestGameId = gameId.value;
  const requestSessionRunning = props.detail.game.sessionRunning;
  const requestSpaceId = props.detail.game.spaceId;
  const requestRulesRevision = props.detail.game.rulesRevision;
  loading.value = true;
  loadError.value = null;
  try {
    let snapshot: GameStateSnapshot | null = null;
    try {
      snapshot = await getGameApi().getGameStateSnapshot(requestGameId);
    } catch {
      // Legacy Game API не имеет snapshot boundary; сохраняем совместимость до его подключения.
      snapshot = null;
    }
    const membershipsResult = await getGameApi().getGameCharacters(requestGameId);
    if (requestSequence !== loadSequence || requestGameId !== gameId.value) return;
    const characterKeys = membershipsResult.map(
      (membership) => `character:${membership.characterId}` as CombatEntityKey,
    );
    const projectionLevel = 'summary';
    const [runtimeResult, npcResult, moderationProjections] = await Promise.all([
      getGameApi().getRuntimeEntities(requestGameId, { entityKeys: characterKeys, projectionLevel }),
      getGameApi().getNpcSummaries({ gameId: requestGameId, status: 'active', limit: 100 }),
      getGameApi().getCharacterModerationProjections(
        requestGameId,
        membershipsResult.map((membership) => membership.characterId),
      ),
    ]);
    if (requestSequence !== loadSequence || requestGameId !== gameId.value) return;
    gameStateSnapshot.value = snapshot;
    memberships.value = membershipsResult;
    const actuals: Record<number, CharacterVersion> = { ...actualById.value };
    const nextRuntimeProjections: Record<CombatEntityKey, GameRuntimeEntityProjection> = {
      ...runtimeByKey.value,
    };
    for (const projection of runtimeResult.projections) {
      const currentProjection = nextRuntimeProjections[projection.entityKey];
      if (
        currentProjection?.projectionLevel === 'full' &&
        projection.projectionLevel === 'summary' &&
        projection.actualVersion <= currentProjection.actualVersion
      ) {
        continue;
      }
      nextRuntimeProjections[projection.entityKey] = projection;
      if (projection.kind === 'character') {
        if (projection.version) actuals[projection.id] = projection.version;
        else delete actuals[projection.id];
      }
    }
    actualById.value = actuals;
    moderationByCharacterId.value = Object.fromEntries(
      moderationProjections.map((projection) => [projection.characterId, projection]),
    );
    runtimeByKey.value = nextRuntimeProjections;
    npcs.value = npcResult.items.map(toLegacyNpc);
    void loadRuntimeProjections(
      snapshot?.battle?.initiative?.participants.map((participant) => participant.id) ?? [],
      requestGameId,
    );
    const nextQuickRolls = await getGameApi().getQuickRolls(requestGameId);
    if (requestSequence !== loadSequence || requestGameId !== gameId.value) return;
    quickRolls.value = nextQuickRolls;
    await refreshProcessSessions(requestGameId, requestSequence);
    if (requestSequence !== loadSequence || requestGameId !== gameId.value) return;
    const nextMechanics = await getMechanicApi().getMechanics();
    if (requestSequence !== loadSequence || requestGameId !== gameId.value) return;
    mechanics.value = nextMechanics;
    await loadRevision(requestSpaceId, requestRulesRevision, requestGameId, requestSequence);
    connectRealtime(requestGameId);
    try {
      await syncRealtime(requestGameId, characterKeys, requestSessionRunning, requestSequence);
    } catch {
      // Основной snapshot уже загружен; sync transport подключится при следующем reload/reconnect.
    }
  } catch (e) {
    if (requestSequence === loadSequence) {
      loadError.value = e instanceof Error ? e.message : 'Не удалось загрузить данные чата';
    }
  } finally {
    if (requestSequence === loadSequence) loading.value = false;
  }
}

function toLegacyNpc(summary: GameNpcSummary): GameNpc {
  const { actualSpaceCode: _actualSpaceCode, actualRulesRevision: _actualRulesRevision, ...npc } = summary;

  return { ...npc, version: null };
}

function disconnectRealtime(): void {
  stopRealtimeSubscription?.();
  stopRealtimeSubscription = null;
}

function isKnownRealtimeEntity(entityKey: CombatEntityKey): boolean {
  if (runtimeByKey.value[entityKey] || runtimeParticipantKeys.value.has(entityKey)) return true;
  if (!entityKey.startsWith('npc:')) return false;
  const npcId = Number(entityKey.slice('npc:'.length));

  return npcs.value.some((npc) => npc.id === npcId);
}

async function applyRealtimeEvent(event: GameRealtimeEvent): Promise<boolean> {
  if (event.gameId !== gameId.value || event.cursor <= realtimeCursor) return true;
  if (!isKnownRealtimeEntity(event.entityKey)) {
    realtimeCursor = event.cursor;

    return true;
  }
  const knownProjection = runtimeByKey.value[event.entityKey];
  const projection = await getGameApi().getRuntimeEntity(
    event.gameId,
    event.entityKey,
    knownProjection?.projectionLevel === 'full' || initiativeKeys.value.includes(event.entityKey) ? 'full' : 'summary',
  );
  if (!projection || projection.actualVersion < event.actualVersion) return false;

  applyRuntimeProjection(projection);
  realtimeCursor = event.cursor;

  return true;
}

function enqueueRealtimeEvent(event: GameRealtimeEvent): void {
  realtimeApplyChain = realtimeApplyChain
    .then(async () => {
      if (realtimeSyncRequired) return;
      if (await applyRealtimeEvent(event)) return;

      realtimeSyncRequired = true;
      await recoverRealtime();
    })
    .catch(() => {
      realtimeSyncRequired = true;
      void recoverRealtime();
    });
}

function realtimeEntityKeys(): CombatEntityKey[] {
  return [
    ...new Set([
      ...memberships.value.map((membership) => `character:${membership.characterId}` as CombatEntityKey),
      ...npcs.value.map((npc) => `npc:${npc.id}` as CombatEntityKey),
      ...(Object.keys(runtimeByKey.value) as CombatEntityKey[]),
    ]),
  ];
}

async function syncRealtime(
  requestGameId: number,
  entityKeys: readonly CombatEntityKey[],
  requestSessionRunning: boolean,
  requestSequence: number,
): Promise<boolean> {
  const result = await getGameRealtimePort().sync(requestGameId, {
    lastCursor: realtimeCursor === 0 ? null : realtimeCursor,
    entityKeys: [...entityKeys],
    projectionLevel: requestSessionRunning ? 'summary' : 'full',
  });
  if (requestSequence !== loadSequence || requestGameId !== gameId.value) return false;

  if (result.kind === 'snapshot') {
    realtimeCursor = result.currentCursor;
    gameStateSnapshot.value = result.snapshot;
    for (const projection of result.projections) applyRuntimeProjection(projection);
    const projectionsRecovered = await loadRuntimeProjections(
      result.snapshot.battle?.initiative?.participants.map((participant) => participant.id) ?? [],
      requestGameId,
    );

    return projectionsRecovered;
  }

  for (const event of result.events) {
    if (!(await applyRealtimeEvent(event))) return false;
  }
  realtimeCursor = Math.max(realtimeCursor, result.currentCursor);

  return true;
}

async function recoverRealtime(): Promise<void> {
  if (realtimeRecoveryPromise) return realtimeRecoveryPromise;

  realtimeRecoveryPromise = syncRealtime(gameId.value, realtimeEntityKeys(), props.detail.game.sessionRunning, loadSequence)
    .then((recovered) => {
      if (recovered) realtimeSyncRequired = false;
    })
    .catch(() => undefined)
    .finally(() => {
      realtimeRecoveryPromise = null;
    });

  return realtimeRecoveryPromise;
}

function connectRealtime(requestGameId: number): void {
  disconnectRealtime();
  if (!props.active) return;
  realtimeSyncRequired = false;
  stopRealtimeSubscription = getGameRealtimePort().subscribe(
    requestGameId,
    (event) => {
      enqueueRealtimeEvent(event);
    },
    (realtimeError) => {
      loadError.value = realtimeError.message;
    },
  );
}

async function loadRuntimeProjection(entityKey: CombatEntityKey): Promise<void> {
  const requestSequence = (projectionSequences.get(entityKey) ?? 0) + 1;
  const requestGameId = gameId.value;
  const requestLoadSequence = loadSequence;
  const requestProjectionEpoch = projectionEpoch;
  projectionSequences.set(entityKey, requestSequence);
  if (entityKey.startsWith('npc:')) pendingNpcProjectionKeys.add(entityKey);
  try {
    const projection = await getGameApi().getRuntimeEntity(requestGameId, entityKey, 'full');
    if (
      requestGameId !== gameId.value ||
      requestLoadSequence !== loadSequence ||
      requestProjectionEpoch !== projectionEpoch
    )
      return;
    if (projectionSequences.get(entityKey) !== requestSequence) return;
    if (!projection) return;

    applyRuntimeProjection(projection);
  } finally {
    if (projectionSequences.get(entityKey) === requestSequence) {
      pendingNpcProjectionKeys.delete(entityKey);
    }
  }
}

async function ensureRuntimeProjection(entityKey: CombatEntityKey): Promise<void> {
  if (!entityKey.startsWith('npc:')) return;
  const projection = runtimeByKey.value[entityKey];
  if (projection?.kind === 'npc' && projection.projectionLevel === 'full' && !staleNpcProjectionKeys.has(entityKey)) {
    return;
  }

  try {
    await loadRuntimeProjection(entityKey);
  } catch {
    // The dialog keeps its summary fallback when a full projection is unavailable.
  }
}

function applyRuntimeProjection(projection: GameRuntimeEntityProjection): void {
  const current = runtimeByKey.value[projection.entityKey];
  if (
    current?.projectionLevel === 'full' &&
    projection.projectionLevel === 'summary' &&
    projection.actualVersion <= current.actualVersion
  ) {
    return;
  }
  runtimeByKey.value = { ...runtimeByKey.value, [projection.entityKey]: projection };
  if (projection.kind === 'character' && projection.version) {
    actualById.value = { ...actualById.value, [projection.id]: projection.version };
  }
  if (projection.kind !== 'npc' || projection.projectionLevel !== 'full') return;
  staleNpcProjectionKeys.delete(projection.entityKey);

  npcs.value = npcs.value.map((npc) =>
    npc.id === projection.id ? { ...npc, version: projection.version, actualVersion: projection.actualVersion } : npc,
  );
}

async function loadRuntimeProjections(
  entityKeys: readonly string[],
  targetGameId: number = gameId.value,
  force = false,
): Promise<boolean> {
  const projectionKeys = [
    ...new Set(
      entityKeys
        .filter((key) => {
          const candidateKey = key as CombatEntityKey;
          const projection = runtimeByKey.value[candidateKey];

          return (
            projection?.projectionLevel !== 'full' ||
            force ||
            (candidateKey.startsWith('npc:') && staleNpcProjectionKeys.has(candidateKey))
          );
        })
        .map((key) => key as CombatEntityKey),
    ),
  ];
  if (projectionKeys.length === 0) return true;

  const requestSequences = new Map<CombatEntityKey, number>();
  for (const key of projectionKeys) {
    const requestSequence = (projectionSequences.get(key) ?? 0) + 1;
    projectionSequences.set(key, requestSequence);
    requestSequences.set(key, requestSequence);
    if (key.startsWith('npc:')) pendingNpcProjectionKeys.add(key);
  }

  try {
    const requestGameId = targetGameId;
    const requestLoadSequence = loadSequence;
    const requestProjectionEpoch = projectionEpoch;
    const result = await getGameApi().getRuntimeEntities(requestGameId, {
      entityKeys: projectionKeys,
      projectionLevel: 'full',
    });
    if (
      requestGameId !== gameId.value ||
      requestLoadSequence !== loadSequence ||
      requestProjectionEpoch !== projectionEpoch
    )
      return false;
    for (const projection of result.projections) {
      if (projectionSequences.get(projection.entityKey) === requestSequences.get(projection.entityKey)) {
        applyRuntimeProjection(projection);
      }
    }

    return result.missingEntityKeys.length === 0;
  } catch {
    // A projection is best-effort; the summary remains usable when the full read is unavailable.
    return false;
  } finally {
    for (const key of projectionKeys) {
      if (projectionSequences.get(key) === requestSequences.get(key)) {
        if (key.startsWith('npc:')) pendingNpcProjectionKeys.delete(key);
      }
    }
  }
}

async function ensureRuntimeProjectionsForInitiative(entityKeys: string[]): Promise<void> {
  const loaded = await loadRuntimeProjections(entityKeys);
  if (!loaded) throw new Error('Не удалось загрузить полные листы выбранных участников');
}

function invalidateRuntimeProjections(): void {
  projectionEpoch += 1;
  const fullProjectionEntries = Object.entries(runtimeByKey.value).filter(
    ([, projection]) => projection.projectionLevel === 'full',
  );

  const staleKeys: CombatEntityKey[] = [];
  for (const [entityKey] of fullProjectionEntries) {
    const combatEntityKey = entityKey as CombatEntityKey;
    if (combatEntityKey.startsWith('npc:')) staleNpcProjectionKeys.add(combatEntityKey);
    projectionSequences.set(combatEntityKey, (projectionSequences.get(combatEntityKey) ?? 0) + 1);
    staleKeys.push(combatEntityKey);
  }
  for (const pendingKey of pendingNpcProjectionKeys) {
    if (!staleKeys.includes(pendingKey)) {
      staleNpcProjectionKeys.add(pendingKey);
      projectionSequences.set(pendingKey, (projectionSequences.get(pendingKey) ?? 0) + 1);
      staleKeys.push(pendingKey);
    }
  }
  if (staleKeys.length === 0) return;
  void loadRuntimeProjections(staleKeys, gameId.value, true);
}

// Правила ревизии игры: чипы и источники «Вставить ссылку» резолвятся из неё (D72).
async function loadRevision(
  spaceId: number = props.detail.game.spaceId,
  rulesRevision: number = props.detail.game.rulesRevision,
  targetGameId: number = gameId.value,
  targetLoadSequence: number = loadSequence,
): Promise<void> {
  const revision = await spaceRevision.fetchRevision(spaceId, rulesRevision);
  if (targetGameId !== gameId.value || targetLoadSequence !== loadSequence) return;
  revisionRules.value = revision.rules;
}

watch(
  () => props.active,
  (value) => {
    if (value) {
      void load();
    } else {
      disconnectRealtime();
    }
  },
  { immediate: true },
);

watch(gameId, () => {
  realtimeCursor = 0;
  disconnectRealtime();
  runtimeByKey.value = {};
  actualById.value = {};
  moderationByCharacterId.value = {};
  if (props.active) void load();
});

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

const initiativeKeys = ref<string[]>([]);

function onInitiativeParticipants(keys: string[]): void {
  initiativeKeys.value = keys;
  void loadRuntimeProjections(keys);
}

function onInitiativeRuntimeProjections(projections: GameRuntimeEntityProjection[]): void {
  for (const projection of projections) applyRuntimeProjection(projection);
}

// Боевая карточка: слайд-овер по клику на участника шкалы инициативы (CD-5).
const cardOpen = ref(false);
const cardKey = ref<CombatEntityKey | null>(null);

// Ревизия оверлеев — счётчик мутаций боевых изменений: шкала инициативы перечитывает
// оверлеи (Истощение) после правок в боевой карточке.
const overlayRevision = ref(0);
const processSessionsByEntity = ref<Record<CombatEntityKey, ProcessSession>>({});

function onOverlayChanged(): void {
  overlayRevision.value += 1;
  invalidateRuntimeProjections();
  void refreshProcessSessions();
}

async function refreshProcessSessions(
  targetGameId: number = gameId.value,
  targetLoadSequence: number = loadSequence,
): Promise<void> {
  const processSessions = await getGameApi()
    .getProcessSessions(targetGameId)
    .catch(() => ({}));
  if (targetGameId !== gameId.value || targetLoadSequence !== loadSequence) return;
  processSessionsByEntity.value = processSessions;
}

async function onOpenCard(entityKey: string): Promise<void> {
  if (entityKey.startsWith('process:continue:')) {
    const processEntityKey = entityKey.slice('process:continue:'.length) as CombatEntityKey;
    if (
      !combatCardModelService.combatCardCanEdit(
        processEntityKey,
        props.canEdit,
        currentUser.value?.id ?? null,
        memberships.value,
      )
    )
      return;
    attackActorKey.value = processEntityKey;
    attackOpen.value = true;

    return;
  }
  const key = entityKey as CombatEntityKey;
  if (!combatCardModelService.combatCardCanEdit(key, props.canEdit, currentUser.value?.id ?? null, memberships.value))
    return;
  cardKey.value = key;
  cardOpen.value = true;
  try {
    await loadRuntimeProjection(key);
  } catch {
    // Legacy card remains available if the on-demand projection is unavailable.
  }
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
async function toggleQuickRoll(entityKey: string, ruleCode: string): Promise<void> {
  const key = entityKey as CombatEntityKey;
  try {
    const current = quickRolls.value[key] ?? [];
    const next = current.includes(ruleCode)
      ? await getGameApi().removeQuickRoll(gameId.value, key, ruleCode)
      : await getGameApi().addQuickRoll(gameId.value, key, ruleCode);
    quickRolls.value = { ...quickRolls.value, [key]: next };
  } catch {
    // Прототип: сбой тумблера не блокирует интерфейс (запись остаётся прежней).
  }
}

watch(speakerOptions, () => applySpeakerKey());
watch(activeSpeakerKey, (key) => {
  if (key && key !== 'gm') void loadRuntimeProjections([key]);
});

const checkOpen = ref(false);
const actionOpen = ref(false);
const actionLaunchHint = ref<ActionLaunchHint | null>(null);
const attackOpen = ref(false);
const attackActorKey = ref<CombatEntityKey | null>(null);
const hitOpen = ref(false);
const injuryOpen = ref(false);
const spellOpen = ref(false);
const spellCasterKey = ref<CombatEntityKey | null>(null);
const spellLaunchContext = ref<SpellCastLaunchContext>({ kind: 'free' });
const resumeOffer = ref<CheckOffer | null>(null);
const hitResumeOffer = ref<CheckOffer | null>(null);
const hitAttackerKey = ref<CombatEntityKey | null>(null);
const hitAttack = ref<AttackOverview | null>(null);
const processActionContext = ref<ProcessActionContext | null>(null);
const attackAction = ref<AttackAction | null>(null);
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
    (offer.waitingOn === 'covering' && Boolean(offer.waitingOnCoverers?.includes(key))) ||
    (offer.waitingOn === 'opponent' && (offer.opponent === key || Boolean(offer.waitingOnTargets?.includes(key)))) ||
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
    (offer.waitingOn === 'covering' && Boolean(offer.waitingOnCoverers?.includes(key))) ||
    (offer.waitingOn === 'opponent' && (offer.opponent === key || Boolean(offer.waitingOnTargets?.includes(key)))) ||
    (offer.waitingOn === 'initiator' && offer.initiator === key)
  );
}

const actionableOfferCount = computed(
  () =>
    pendingOffers.value.filter(
      (offer) => isWaitingOnYou(offer, speakerEntityKey.value) && !dismissedOfferIds.value.has(offer.id),
    ).length,
);

function isHitOffer(offer: CheckOffer): boolean {
  const code = hitCheckCode.value;

  return code !== '' && offer.checkCode === code;
}

function pendingToResume(): CheckOffer | undefined {
  if (revisionRules.value.length === 0) return undefined;
  const key = speakerEntityKey.value;
  const asGm = props.canEdit;
  const mine = pendingOffers.value.filter((offer) => isWaitingOnSpeaker(offer, key, asGm));

  return mine.find((offer) => isHitOffer(offer)) ?? mine[0];
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
  if (revisionRules.value.length === 0) return;
  const actionable = pendingOffers.value.filter((offer) => isActionableOffer(offer, key, asGm));
  const first =
    actionable.find(
      (offer) => isHitOffer(offer) && (offer.waitingOn === 'covering' || offer.waitingOn === 'opponent'),
    ) ?? actionable[0];
  if (!checkOpen.value && !hitOpen.value && first) {
    await loadRuntimeProjections([first.initiator, first.opponent]);
    if (isHitOffer(first)) {
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
  if (revisionRules.value.length === 0) return;
  dismissedOfferIds.value = new Set([...dismissedOfferIds.value].filter((id) => id !== offer.id));
  if (isHitOffer(offer)) {
    hitResumeOffer.value = offer;
    hitAttackerKey.value = offer.initiator;
    hitAttack.value = null;
    hitOpen.value = true;

    return;
  }
  resumeOffer.value = offer;
  checkOpen.value = true;
}

async function openCheckLaunch(): Promise<void> {
  if (revisionRules.value.length === 0) return;
  if (speakerEntityKey.value) await loadRuntimeProjections([speakerEntityKey.value]);
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

async function openActionLaunch(): Promise<void> {
  if (speakerEntityKey.value) await loadRuntimeProjections([speakerEntityKey.value]);
  actionLaunchHint.value = null;
  actionOpen.value = true;
}

async function onLaunchStateAction(hint: ActionLaunchHint): Promise<void> {
  await loadRuntimeProjections(
    [speakerEntityKey.value, hint.targetKey].filter((key): key is CombatEntityKey => key !== null),
  );
  actionLaunchHint.value = hint;
  actionOpen.value = true;
}

function onActionClosed(open: boolean): void {
  actionOpen.value = open;
  if (!open) actionLaunchHint.value = null;
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
      ? current.waitingOn === 'covering'
        ? (current.waitingOnCoverers?.[0] ?? null)
        : current.waitingOn === 'opponent'
          ? current.opponent
          : current.initiator
      : null;
  if (current && (props.canEdit || (key !== null && key === waitingKey))) {
    dismissedOfferIds.value = new Set([...dismissedOfferIds.value, current.id]);
  }
  hitResumeOffer.value = null;
  hitAttack.value = null;
  processActionContext.value = null;
  attackAction.value = null;
  hitAttackerKey.value = null;
  hitOpen.value = false;
  void refreshPendingOffers();
}

async function onLaunchHit(payload: { attackerKey: CombatEntityKey; attack: AttackOverview }): Promise<void> {
  await loadRuntimeProjections([payload.attackerKey]);
  hitResumeOffer.value = null;
  attackAction.value = null;
  hitAttackerKey.value = payload.attackerKey;
  hitAttack.value = payload.attack;
  hitOpen.value = true;
}

async function onLaunchProcessStep(payload: ProcessActionContext & { attack: AttackOverview }): Promise<void> {
  await loadRuntimeProjections([payload.session.entityKey]);
  hitResumeOffer.value = null;
  attackAction.value = null;
  hitAttackerKey.value = payload.session.entityKey;
  hitAttack.value = payload.attack;
  processActionContext.value = { session: payload.session, stepCode: payload.stepCode };
  hitOpen.value = true;
}

async function openAttackLaunch(): Promise<void> {
  const initiativeActorKey =
    props.canEdit && lastTurnId.value && lastTurnId.value !== 'gm'
      ? (lastTurnId.value as CombatEntityKey)
      : speakerEntityKey.value;
  if (initiativeActorKey) await loadRuntimeProjections([initiativeActorKey]);
  attackActorKey.value = initiativeActorKey;
  attackOpen.value = true;
}

function onAttackClosed(open: boolean): void {
  attackOpen.value = open;
  if (!open) attackActorKey.value = null;
}

async function onLaunchAttack(payload: AttackAction): Promise<void> {
  await loadRuntimeProjections([payload.initiator, ...payload.strikes.map((strike) => strike.targetKey)]);
  attackAction.value = payload;
  hitAttackerKey.value = payload.initiator;
  hitAttack.value = payload.strikes[0]?.profile ?? null;
  processActionContext.value = payload.source.kind === 'process' ? payload.source.process : null;
  hitResumeOffer.value = null;
  hitOpen.value = true;
}

function onLaunchInjury(): void {
  injuryOpen.value = true;
}

async function openSpellLaunch(): Promise<void> {
  if (speakerEntityKey.value) await loadRuntimeProjections([speakerEntityKey.value]);
  spellCasterKey.value = null;
  spellLaunchContext.value = { kind: 'free' };
  spellOpen.value = true;
}

async function onLaunchChargeCast(payload: { casterKey: CombatEntityKey; sustainId: string }): Promise<void> {
  await loadRuntimeProjections([payload.casterKey]);
  spellCasterKey.value = payload.casterKey;
  spellLaunchContext.value = { kind: 'charge_spend', sustainId: payload.sustainId };
  spellOpen.value = true;
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
  disconnectRealtime();
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
      @click="startSession"
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
      @click="stopSession"
    >
      Остановить сессию
    </v-btn>
    <v-btn-group v-if="chatId !== null" class="check-split" variant="tonal" divided rounded="lg">
      <v-btn size="small" prepend-icon="mdi-shield-check-outline" @click="openCheckLaunch">
        Проверка
        <span v-if="actionableOfferCount > 0" class="check-split__count">{{ actionableOfferCount }}</span>
      </v-btn>
      <v-menu location="bottom end">
        <template #activator="{ props: menuProps }">
          <v-btn
            v-bind="menuProps"
            size="small"
            class="check-split__plus"
            aria-label="Другие броски"
            title="Другие броски"
          >
            <v-icon size="18">mdi-chevron-down</v-icon>
          </v-btn>
        </template>
        <v-list density="compact">
          <v-list-item prepend-icon="mdi-plus" title="Новая проверка" @click="openNewCheck" />
          <v-list-item prepend-icon="mdi-sword-cross" title="Атака" @click="openAttackLaunch" />
          <v-list-item prepend-icon="mdi-auto-fix" title="Заклинание" @click="openSpellLaunch" />
          <v-list-item prepend-icon="mdi-run-fast" title="Действие" @click="openActionLaunch" />
        </v-list>
      </v-menu>
    </v-btn-group>
  </Teleport>

  <div class="game-chat-tab">
    <v-alert v-if="statusNotice" type="info" variant="tonal" density="compact" class="mb-3">
      {{ statusNotice }}
    </v-alert>
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
          :characters="eligibleMemberships"
          :npcs="npcs"
          :runtime-projections="runtimeByKey"
          :rules="revisionRules"
          :mechanics="mechanics"
          :overlay-revision="overlayRevision"
          :ensure-session="ensureSessionForInitiative"
          :ensure-runtime-projections="ensureRuntimeProjectionsForInitiative"
          :session-running="detail.game.sessionRunning"
          class="game-chat-sidebar__initiative"
          @turn="onTurn"
          @open-card="onOpenCard"
          @overlay-changed="onOverlayChanged"
          @participants="onInitiativeParticipants"
          @runtime-projections="onInitiativeRuntimeProjections"
        />

        <CombatQuickRolls
          :game-id="gameId"
          :chat-id="chatId"
          :can-edit="canEdit"
          :current-user-id="currentUser?.id ?? null"
          :memberships="eligibleMemberships"
          :npcs="npcs"
          :runtime-projections="runtimeByKey"
          :rules="revisionRules"
          :mechanics="mechanics"
          :quick-rolls="quickRolls"
          :active-entity-key="activeSpeakerKey"
          :overlay-revision="overlayRevision"
          class="game-chat-sidebar__quickrolls"
          @toggle-quick-roll="toggleQuickRoll"
          @launch-hit="onLaunchHit"
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
      :current-user-id="currentUser?.id ?? null"
      :quick-rolls="quickRolls"
      :space-id="detail.game.spaceId"
      :rules-revision="detail.game.rulesRevision"
      :overlay-revision="overlayRevision"
      :process-sessions="processSessionsByEntity"
      :runtime-projection="cardKey ? (runtimeByKey[cardKey] ?? null) : null"
      @update:open="onCloseCard"
      @toggle-quick-roll="toggleQuickRoll"
      @overlay-changed="onOverlayChanged"
      @launch-hit="onLaunchHit"
      @launch-injury="onLaunchInjury"
      @launch-charge-cast="onLaunchChargeCast"
      @launch-action="onLaunchStateAction"
    />

    <CheckLaunchDialog
      :open="checkOpen"
      :game-id="gameId"
      :space-id="detail.game.spaceId"
      :chat-id="chatId"
      :characters="eligibleMemberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="currentUser?.id ?? null"
      :active-speaker-key="activeSpeakerKey"
      :initiative-keys="initiativeKeys"
      :resume-offer="resumeOffer"
      :runtime-projections="runtimeByKey"
      :ensure-runtime-projection="ensureRuntimeProjection"
      @update:open="onCheckClosed"
      @settled="refreshPendingOffers"
    />

    <ActionLaunchDialog
      :open="actionOpen"
      :game-id="gameId"
      :chat-id="chatId"
      :characters="eligibleMemberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="currentUser?.id ?? null"
      :active-speaker-key="activeSpeakerKey"
      :launch-hint="actionLaunchHint"
      :runtime-projections="runtimeByKey"
      @launch-process-step="onLaunchProcessStep"
      @update:open="onActionClosed"
      @settled="onOverlayChanged"
      @overlay-changed="onOverlayChanged"
    />

    <AttackLaunchDialog
      :open="attackOpen"
      :game-id="gameId"
      :chat-id="chatId"
      :characters="eligibleMemberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :active-speaker-key="activeSpeakerKey"
      :actor-key="attackActorKey"
      :initiative-keys="initiativeKeys"
      :runtime-projections="runtimeByKey"
      @update:open="onAttackClosed"
      @launch-attack="onLaunchAttack"
    />

    <HitLaunchDialog
      :open="hitOpen"
      :game-id="gameId"
      :chat-id="chatId"
      :characters="eligibleMemberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="currentUser?.id ?? null"
      :active-speaker-key="activeSpeakerKey"
      :attacker-key="hitAttackerKey"
      :attack="hitAttack"
      :attack-action="attackAction"
      :resume-offer="hitResumeOffer"
      :process-context="processActionContext"
      :initiative-keys="initiativeKeys"
      :runtime-projections="runtimeByKey"
      @update:open="onHitClosed"
      @settled="refreshPendingOffers"
      @overlay-changed="onOverlayChanged"
    />
    <InjuryLaunchDialog
      :open="injuryOpen"
      :game-id="gameId"
      :chat-id="chatId"
      :characters="eligibleMemberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :target-key="cardKey"
      @update:open="injuryOpen = $event"
      @overlay-changed="onOverlayChanged"
    />
    <SpellCastDialog
      :open="spellOpen"
      :caster-key="spellCasterKey"
      :launch-context="spellLaunchContext"
      :active-speaker-key="activeSpeakerKey"
      :game-id="gameId"
      :chat-id="chatId"
      :characters="eligibleMemberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="currentUser?.id ?? null"
      :initiative-keys="initiativeKeys"
      :runtime-projections="runtimeByKey"
      :ensure-runtime-projection="ensureRuntimeProjection"
      @update:open="spellOpen = $event"
      @overlay-changed="onOverlayChanged"
      @settled="refreshPendingOffers"
    />
    <ConcentrationTokenAskDialog
      :open="concentrationAskOpen"
      :max-spend="concentrationAskMax"
      :remaining="concentrationAskRemaining"
      :amount="concentrationAskAmount"
      @update:amount="concentrationAskAmount = $event"
      @confirm="confirmConcentrationAsk"
      @skip="skipConcentrationAsk"
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
  --chat-thread-chrome: 70px;
  flex: 79 1 0;
  min-width: 0;
  height: calc(100vh - var(--v-layout-top) - 70px);
}
.game-chat-thread :deep(.chat-thread-root),
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
