import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameBattleState } from '@/modules/Roleplay/Game/Dto/GameBattleState';
import type { GameCleanupSummary } from '@/modules/Roleplay/Game/Dto/GameCleanupSummary';
import type { GameLifecycleCommand } from '@/modules/Roleplay/Game/Dto/GameLifecycleCommand';
import type { GameLifecycleResult } from '@/modules/Roleplay/Game/Dto/GameLifecycleResult';
import type { GameSessionState } from '@/modules/Roleplay/Game/Dto/GameSessionState';
import type { GameStateSnapshot } from '@/modules/Roleplay/Game/Dto/GameStateSnapshot';
import type { GameCombatCommandRecord } from '@/modules/Roleplay/Game/Dto/GameCombatCommandRecord';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';

interface StoredCommand {
  commandId: string;
  fingerprint: string;
  sessionId: string | null;
  battleId: string | null;
  scope: 'session' | 'battle';
  result: GameLifecycleResult;
}

interface GameStateStore {
  session: GameSessionState | null;
  battle: GameBattleState | null;
  commands: Map<string, StoredCommand>;
  combatCommands: Map<string, GameCombatCommandRecord>;
}

interface SerializedGameState {
  snapshot: GameStateSnapshot;
  terminalSnapshot?: GameStateSnapshot;
  commands: StoredCommand[];
  combatCommands?: GameCombatCommandRecord[];
  terminalCommands: StoredCommand[];
  closedCommandIds: string[];
}

const stateStores = new Map<number, GameStateStore>();
const closedCommandIds = new Map<number, Set<string>>();
const terminalCommandRecords = new Map<number, Map<string, StoredCommand>>();
const terminalSnapshots = new Map<number, GameStateSnapshot>();
const mutationQueues = new Map<number, Promise<unknown>>();
let gameReader: (gameId: number) => Omit<Game, 'sessionRunning'> | null = () => null;
let participantValidator: (
  gameId: number,
  participantEntityKeys: readonly CombatEntityKey[],
) => boolean | Promise<boolean> = async () => true;
let participantResolver: (
  gameId: number,
) => readonly CombatEntityKey[] | Promise<readonly CombatEntityKey[]> = async () => [];

function emptyInitiative(gameId: number): GameInitiative {
  return { gameId, active: false, participants: [], activeIndex: null, round: 1, updatedAt: '' };
}

function validateInitiative(data: GameInitiative): void {
  if (data.activeIndex !== null && (data.activeIndex < 0 || data.activeIndex >= data.participants.length)) {
    throw new Error('Индекс текущего хода вне диапазона участников');
  }
  if (typeof data.round !== 'number' || data.round < 1) {
    throw new Error('Номер раунда должен быть >= 1');
  }
  for (const participant of data.participants) {
    if (!participant.id || !participant.name) throw new Error('Участник инициативы должен иметь id и имя');
    if (participant.kind !== 'character' && participant.kind !== 'npc') {
      throw new Error('Неизвестный тип участника инициативы');
    }
  }
}

export function configureMockGameState(options: {
  getGame: (gameId: number) => Omit<Game, 'sessionRunning'> | null;
  validateParticipants?: (
    gameId: number,
    participantEntityKeys: readonly CombatEntityKey[],
  ) => boolean | Promise<boolean>;
  resolveParticipants?: (gameId: number) => readonly CombatEntityKey[] | Promise<readonly CombatEntityKey[]>;
}): void {
  gameReader = options.getGame;
  participantValidator = options.validateParticipants ?? (async () => true);
  participantResolver = options.resolveParticipants ?? (async () => []);
}

export function hasActiveGameSession(gameId: number): boolean {
  return stateStores.get(gameId)?.session != null;
}

export function isGameSessionParticipant(gameId: number, entityKey: CombatEntityKey): boolean {
  return stateStores.get(gameId)?.session?.participantEntityKeys.includes(entityKey) ?? false;
}

export async function withMockGameStateLock<T>(gameId: number, operation: () => Promise<T>): Promise<T> {
  const previous = mutationQueues.get(gameId) ?? Promise.resolve();
  const current = previous.catch(() => undefined).then(operation);
  mutationQueues.set(gameId, current);

  try {
    return await current;
  } finally {
    if (mutationQueues.get(gameId) === current) mutationQueues.delete(gameId);
  }
}

export async function withMockGameBattleState<T>(
  gameId: number,
  operation: (state: { session: GameSessionState; battle: GameBattleState }) => Promise<T>,
): Promise<T> {
  return withMockGameStateLock(gameId, async () => {
    const store = stateStores.get(gameId);
    if (!store?.session || !store.battle) throw new Error('No active Game battle');

    return operation({ session: store.session, battle: store.battle });
  });
}

export async function cancelGameParticipantProcesses(
  gameId: number,
  entityKey: CombatEntityKey,
): Promise<GameCleanupSummary | null> {
  return withMockGameStateLock(gameId, async () => {
    const store = stateStores.get(gameId);
    const battle = store?.battle;
    if (!store?.session || !battle) return null;

    const cancelledProcessIds = Object.values(battle.combatProcesses)
      .filter((process) => process.actorKey === entityKey || process.targetKey === entityKey)
      .map((process) => process.processId);
    for (const processId of cancelledProcessIds) delete battle.combatProcesses[processId];

    const cancelledProcessEntityKeys = Object.keys(battle.processSessions).filter(
      (key) => key === entityKey,
    ) as CombatEntityKey[];
    for (const processEntityKey of cancelledProcessEntityKeys) delete battle.processSessions[processEntityKey];

    const cancelledOffers = battle.checkOffers.filter(
      (offer) =>
        offer.status === 'pending' &&
        (offer.initiator === entityKey ||
          offer.opponent === entityKey ||
          offer.waitingOnTargets?.includes(entityKey) === true ||
          offer.waitingOnCoverers?.includes(entityKey) === true),
    );
    const cancelledOfferIds = cancelledOffers.map((offer) => offer.id);
    battle.checkOffers = battle.checkOffers.filter((offer) => !cancelledOfferIds.includes(offer.id));

    const clearedPendingEffectEntityKeys = Object.prototype.hasOwnProperty.call(battle.pendingActionEffects, entityKey)
      ? [entityKey]
      : [];
    delete battle.pendingActionEffects[entityKey];

    const clearedCommittedActionEntityKeys = Object.prototype.hasOwnProperty.call(
      battle.committedActionSessions,
      entityKey,
    )
      ? [entityKey]
      : [];
    delete battle.committedActionSessions[entityKey];

    const clearedActiveSpellIds = battle.activeSpells
      .filter((spell) => spell.casterKey === entityKey)
      .map((spell) => spell.id);
    battle.activeSpells = battle.activeSpells.filter((spell) => spell.casterKey !== entityKey);

    const clearedMovementEntityKeys = Object.prototype.hasOwnProperty.call(battle.currentSpeed, entityKey)
      ? [entityKey]
      : [];
    delete battle.currentSpeed[entityKey];

    const clearedQuickRollEntityKeys = Object.prototype.hasOwnProperty.call(battle.quickRolls, entityKey)
      ? [entityKey]
      : [];
    delete battle.quickRolls[entityKey];

    if (battle.initiative) {
      const participantIndex = battle.initiative.participants.findIndex((participant) => participant.id === entityKey);
      if (participantIndex >= 0) {
        battle.initiative.participants.splice(participantIndex, 1);
        if (battle.initiative.participants.length === 0) {
          battle.initiative.activeIndex = null;
        } else if (battle.initiative.activeIndex !== null && participantIndex <= battle.initiative.activeIndex) {
          battle.initiative.activeIndex = Math.min(
            battle.initiative.activeIndex - 1,
            battle.initiative.participants.length - 1,
          );
        }
      }
    }

    battle.battleStateVersion += 1;
    battle.updatedAt = new Date().toISOString();

    return {
      gameId,
      sessionId: store.session.sessionId,
      battleId: battle.battleId,
      reason: 'character_returned',
      cancelledProcessIds,
      cancelledProcessEntityKeys,
      cancelledOfferIds,
      clearedPendingEffectEntityKeys,
      clearedCommittedActionEntityKeys,
      clearedActiveSpellIds,
      clearedMovementEntityKeys,
      clearedQuickRollEntityKeys,
      initiativeCleared: false,
      clearedMarkerKeys: [],
    };
  });
}

export async function startGameSession(command: GameLifecycleCommand): Promise<GameLifecycleResult> {
  if (!isCommandType(command, 'startSession')) {
    return conflictResult(command, undefined, 'invalid_command_type');
  }

  return withMockGameStateLock(command.gameId, async () => {
    const store = stateStores.get(command.gameId);
    const replayed = replayIfAvailable(command, store);
    if (replayed) return replayed;

    if (closedCommandIds.get(command.gameId)?.has(command.commandId)) {
      return conflictResult(command, store, 'closed_session');
    }

    if (store?.session) {
      const result = transitionResult(
        command,
        snapshotOf(store),
        {
          type: 'session_already_active',
          sessionId: store.session.sessionId,
          battleId: store.session.activeBattleId,
          endedBattleId: null,
          cleanup: null,
        },
        'already_active',
      );

      return rememberResult(command, store, result, 'session');
    }

    const participantEntityKeys = [
      ...(command.participantAdmission === 'currentEligible'
        ? await participantResolver(command.gameId)
        : (command.participantEntityKeys ?? [])),
    ];
    if (!(await participantValidator(command.gameId, participantEntityKeys))) {
      return rememberResult(command, store, conflictResult(command, store, 'participant_not_eligible'), 'session');
    }

    const now = new Date().toISOString();
    const session: GameSessionState = {
      gameId: command.gameId,
      sessionId: createRandomId(),
      status: 'active',
      sessionStateVersion: 0,
      participantEntityKeys,
      activeBattleId: null,
      sessionMarkers: {},
      startedAt: now,
      updatedAt: now,
    };
    const nextStore: GameStateStore = {
      session,
      battle: null,
      commands: new Map(),
      combatCommands: new Map(),
    };
    stateStores.set(command.gameId, nextStore);
    terminalSnapshots.delete(command.gameId);
    const result = transitionResult(
      command,
      snapshotOf(nextStore),
      {
        type: 'session_created',
        sessionId: session.sessionId,
        battleId: null,
        endedBattleId: null,
        cleanup: null,
      },
      'created',
    );

    return rememberResult(command, nextStore, result, 'session');
  });
}

export async function startGameBattle(command: GameLifecycleCommand): Promise<GameLifecycleResult> {
  if (!isCommandType(command, 'startBattle')) {
    return conflictResult(command, undefined, 'invalid_command_type');
  }

  return withMockGameStateLock(command.gameId, async () => {
    const store = stateStores.get(command.gameId);
    const replayed = replayIfAvailable(command, store);
    if (replayed) return replayed;
    if (closedCommandIds.get(command.gameId)?.has(command.commandId)) {
      return conflictResult(command, store, 'closed_battle');
    }

    if (!store?.session) return conflictResult(command, store, 'no_active_session');
    if (store.session.sessionId !== command.sessionId) return conflictResult(command, store, 'closed_session');
    if (isStale(command.expectedSessionStateVersion, store.session.sessionStateVersion)) {
      return conflictResult(command, store, 'stale_version');
    }

    if (store.battle) {
      const result = transitionResult(
        command,
        snapshotOf(store),
        {
          type: 'battle_already_active',
          sessionId: store.session.sessionId,
          battleId: store.battle.battleId,
          endedBattleId: null,
          cleanup: null,
        },
        'already_active',
      );

      return rememberResult(command, store, result, 'battle');
    }

    const now = new Date().toISOString();
    const battle: GameBattleState = {
      gameId: command.gameId,
      sessionId: store.session.sessionId,
      battleId: createRandomId(),
      status: 'active',
      battleStateVersion: 0,
      initiative: null,
      processSessions: {},
      combatProcesses: {},
      checkOffers: [],
      pendingActionEffects: {},
      committedActionSessions: {},
      activeSpells: [],
      currentSpeed: {},
      quickRolls: {},
      battleMarkers: {},
      startedAt: now,
      updatedAt: now,
    };
    store.battle = battle;
    store.session.activeBattleId = battle.battleId;
    store.session.sessionStateVersion += 1;
    store.session.updatedAt = now;
    const result = transitionResult(
      command,
      snapshotOf(store),
      {
        type: 'battle_created',
        sessionId: store.session.sessionId,
        battleId: battle.battleId,
        endedBattleId: null,
        cleanup: null,
      },
      'created',
    );

    return rememberResult(command, store, result, 'battle');
  });
}

export async function endGameBattle(command: GameLifecycleCommand): Promise<GameLifecycleResult> {
  if (!isCommandType(command, 'endBattle')) {
    return conflictResult(command, undefined, 'invalid_command_type');
  }

  return withMockGameStateLock(command.gameId, async () => {
    const store = stateStores.get(command.gameId);
    const replayed = replayIfAvailable(command, store);
    if (replayed) return replayed;
    if (closedCommandIds.get(command.gameId)?.has(command.commandId)) {
      return conflictResult(command, store, 'closed_battle');
    }

    if (!store?.session) return conflictResult(command, store, 'no_active_session');
    if (store.session.sessionId !== command.sessionId) return conflictResult(command, store, 'closed_session');
    if (!store.battle || store.battle.battleId !== command.battleId) {
      return conflictResult(command, store, store.battle ? 'closed_battle' : 'no_active_battle');
    }
    if (
      isStale(command.expectedSessionStateVersion, store.session.sessionStateVersion) ||
      isStale(command.expectedBattleStateVersion, store.battle.battleStateVersion)
    ) {
      return conflictResult(command, store, 'stale_version');
    }

    const battleId = store.battle.battleId;
    const cleanup = cleanupBattle(store, 'battle_ended');
    const now = new Date().toISOString();
    store.battle = null;
    store.session.activeBattleId = null;
    store.session.sessionStateVersion += 1;
    store.session.updatedAt = now;
    moveClosedBattleCommands(store, battleId, command.gameId);
    const result = transitionResult(
      command,
      snapshotOf(store),
      {
        type: 'battle_ended',
        sessionId: store.session.sessionId,
        battleId: null,
        endedBattleId: battleId,
        cleanup,
      },
      'ended',
    );

    const closed = closedCommandIds.get(command.gameId) ?? new Set<string>();
    closed.add(command.commandId);
    closedCommandIds.set(command.gameId, closed);
    rememberTerminalResult(command, result, 'battle');
    moveClosedCombatCommands(store, battleId, command.gameId);

    return result;
  });
}

export async function stopGameStateSession(command: GameLifecycleCommand): Promise<GameLifecycleResult> {
  if (!isCommandType(command, 'stopSession')) {
    return conflictResult(command, undefined, 'invalid_command_type');
  }

  return withMockGameStateLock(command.gameId, async () => {
    const store = stateStores.get(command.gameId);
    const replayed = replayIfAvailable(command, store);
    if (replayed) return replayed;
    if (closedCommandIds.get(command.gameId)?.has(command.commandId)) {
      return conflictResult(command, store, 'closed_session');
    }

    if (!store?.session) return conflictResult(command, store, 'no_active_session');
    if (store.session.sessionId !== command.sessionId) return conflictResult(command, store, 'closed_session');
    if (isStale(command.expectedSessionStateVersion, store.session.sessionStateVersion)) {
      return conflictResult(command, store, 'stale_version');
    }

    const activeBattleId = store.battle?.battleId ?? null;
    const cleanup = store.battle ? cleanupBattle(store, 'session_stopped') : null;
    const sessionId = store.session.sessionId;
    const terminalSnapshot = snapshotOf(store);
    closeAllCommands(store, command.gameId);
    stateStores.delete(command.gameId);
    terminalSnapshots.set(command.gameId, terminalSnapshot);
    const result = transitionResult(
      command,
      emptySnapshot(command.gameId),
      {
        type: 'session_stopped',
        sessionId: null,
        battleId: null,
        endedBattleId: activeBattleId,
        cleanup: cleanup ? { ...cleanup, sessionId } : null,
      },
      'stopped',
    );
    rememberTerminalResult(command, result, 'session');

    return result;
  });
}

export async function getGameStateSnapshot(gameId: number): Promise<GameStateSnapshot> {
  return cloneData(snapshotOf(stateStores.get(gameId), gameId));
}

export async function getGameInitiative(gameId: number): Promise<GameInitiative> {
  const battle = stateStores.get(gameId)?.battle;

  return cloneData(battle?.initiative ?? emptyInitiative(gameId));
}

export async function saveGameInitiative(gameId: number, data: GameInitiative): Promise<GameInitiative> {
  validateInitiative(data);

  return withMockGameBattleState(gameId, async ({ battle }) => {
    const stored = cloneData({
      ...data,
      gameId,
      participants: data.participants.map((participant) => ({ ...participant })),
      updatedAt: new Date().toISOString(),
    });
    battle.initiative = stored;
    battle.battleStateVersion += 1;
    battle.updatedAt = stored.updatedAt;

    return cloneData(stored);
  });
}

export function getMockGameStateVersions(gameId: number): {
  sessionStateVersion: number | null;
  battleStateVersion: number | null;
} {
  const snapshot = stateStores.has(gameId)
    ? snapshotOf(stateStores.get(gameId), gameId)
    : (terminalSnapshots.get(gameId) ?? emptySnapshot(gameId));

  return {
    sessionStateVersion: snapshot.session?.sessionStateVersion ?? null,
    battleStateVersion: snapshot.battle?.battleStateVersion ?? null,
  };
}

export function getMockCombatCommandRecord(gameId: number, commandId: string): GameCombatCommandRecord | null {
  const record = stateStores.get(gameId)?.combatCommands.get(commandId);

  return record ? cloneData(record) : null;
}

export function rememberMockCombatCommandRecord(gameId: number, record: GameCombatCommandRecord): void {
  const store = stateStores.get(gameId);
  if (!store) throw new Error('No active Game state for combat command');

  store.combatCommands.set(record.commandId, cloneData(record));
}

export function isMockCommandClosed(gameId: number, commandId: string): boolean {
  return closedCommandIds.get(gameId)?.has(commandId) ?? false;
}

export function serializeGameStateSnapshot(gameId: number): string {
  const store = stateStores.get(gameId);
  const terminalCommands = terminalCommandRecords.get(gameId);

  const serialized: SerializedGameState = {
    snapshot: getSnapshotData(store, gameId),
    terminalSnapshot: terminalSnapshots.get(gameId),
    commands: store ? [...store.commands.values()] : [],
    combatCommands: store ? [...store.combatCommands.values()] : [],
    terminalCommands: terminalCommands ? [...terminalCommands.values()] : [],
    closedCommandIds: [...(closedCommandIds.get(gameId) ?? [])],
  };

  return JSON.stringify(serialized);
}

export function restoreGameStateSnapshot(snapshotOrState: GameStateSnapshot | SerializedGameState): void {
  const serialized = isSerializedGameState(snapshotOrState)
    ? snapshotOrState
    : {
        snapshot: snapshotOrState,
        terminalSnapshot: undefined,
        commands: [],
        combatCommands: [],
        terminalCommands: [],
        closedCommandIds: [],
      };
  const snapshot = serialized.snapshot;
  validateRestoredSnapshot(snapshot);
  if (serialized.terminalSnapshot) validateRestoredSnapshot(serialized.terminalSnapshot);
  const restoredBattle = cloneData(snapshot.battle);
  if (restoredBattle && !restoredBattle.combatProcesses) restoredBattle.combatProcesses = {};
  const restored: GameStateStore = {
    session: cloneData(snapshot.session),
    battle: restoredBattle,
    commands: new Map(serialized.commands.map((record) => [record.commandId, cloneData(record)])),
    combatCommands: new Map((serialized.combatCommands ?? []).map((record) => [record.commandId, cloneData(record)])),
  };
  if (restored.session) stateStores.set(snapshot.gameId, restored);
  else stateStores.delete(snapshot.gameId);
  if (serialized.terminalSnapshot) {
    terminalSnapshots.set(snapshot.gameId, cloneData(serialized.terminalSnapshot));
  } else {
    terminalSnapshots.delete(snapshot.gameId);
  }

  if (serialized.terminalCommands.length > 0) {
    terminalCommandRecords.set(
      snapshot.gameId,
      new Map(serialized.terminalCommands.map((record) => [record.commandId, cloneData(record)])),
    );
  } else {
    terminalCommandRecords.delete(snapshot.gameId);
  }

  if (serialized.closedCommandIds.length > 0) {
    closedCommandIds.set(snapshot.gameId, new Set(serialized.closedCommandIds));
  } else {
    closedCommandIds.delete(snapshot.gameId);
  }
}

export function clearMockGameState(): void {
  stateStores.clear();
  closedCommandIds.clear();
  terminalCommandRecords.clear();
  terminalSnapshots.clear();
}

export async function clearMockGameStateForGame(gameId: number): Promise<void> {
  await withMockGameStateLock(gameId, async () => {
    stateStores.delete(gameId);
    closedCommandIds.delete(gameId);
    terminalCommandRecords.delete(gameId);
    terminalSnapshots.delete(gameId);
  });
}

function getSnapshotData(store: GameStateStore | undefined, gameId: number): GameStateSnapshot {
  if (!store?.session) return emptySnapshot(gameId);

  return {
    gameId: store.session.gameId,
    session: cloneData(store.session),
    battle: cloneData(store.battle),
  };
}

function snapshotOf(store: GameStateStore | undefined, gameId = store?.session?.gameId ?? 0): GameStateSnapshot {
  return getSnapshotData(store, gameId);
}

function emptySnapshot(gameId: number): GameStateSnapshot {
  return { gameId, session: null, battle: null };
}

function validateRestoredSnapshot(snapshot: GameStateSnapshot): void {
  if (snapshot.session && snapshot.session.gameId !== snapshot.gameId) {
    throw new Error('Restored session gameId does not match snapshot');
  }

  if (!snapshot.battle) {
    if (snapshot.session?.activeBattleId !== null) {
      throw new Error('Restored session points to a missing battle');
    }

    return;
  }

  if (
    !snapshot.session ||
    snapshot.battle.gameId !== snapshot.gameId ||
    snapshot.battle.sessionId !== snapshot.session.sessionId ||
    snapshot.session.activeBattleId !== snapshot.battle.battleId
  ) {
    throw new Error('Restored battle identity does not match session');
  }
}

function replayIfAvailable(
  command: GameLifecycleCommand,
  store: GameStateStore | undefined,
): GameLifecycleResult | null {
  const previous =
    store?.commands.get(command.commandId) ?? terminalCommandRecords.get(command.gameId)?.get(command.commandId);
  if (!previous) return null;

  if (previous.fingerprint !== fingerprintOf(command)) {
    return conflictResult(command, store, 'command_fingerprint_conflict');
  }

  return cloneData(previous.result);
}

function rememberResult(
  command: GameLifecycleCommand,
  store: GameStateStore | undefined,
  result: GameLifecycleResult,
  scope: 'session' | 'battle',
): GameLifecycleResult {
  if (!store) return result;
  store.commands.set(command.commandId, {
    commandId: command.commandId,
    fingerprint: fingerprintOf(command),
    sessionId: command.sessionId,
    battleId: command.battleId,
    scope,
    result: cloneData(result),
  });

  return result;
}

function rememberTerminalResult(
  command: GameLifecycleCommand,
  result: GameLifecycleResult,
  scope: 'session' | 'battle',
): void {
  const records = terminalCommandRecords.get(command.gameId) ?? new Map<string, StoredCommand>();
  records.set(command.commandId, {
    commandId: command.commandId,
    fingerprint: fingerprintOf(command),
    sessionId: command.sessionId,
    battleId: command.battleId,
    scope,
    result: cloneData(result),
  });
  terminalCommandRecords.set(command.gameId, records);
}

function transitionResult(
  command: GameLifecycleCommand,
  snapshot: GameStateSnapshot,
  transition: {
    type:
      | 'session_created'
      | 'session_already_active'
      | 'battle_created'
      | 'battle_already_active'
      | 'battle_ended'
      | 'session_stopped';
    sessionId: string | null;
    battleId: string | null;
    endedBattleId: string | null;
    cleanup: GameCleanupSummary | null;
  },
  status: 'created' | 'already_active' | 'ended' | 'stopped',
): GameLifecycleResult {
  return {
    kind: 'transition',
    commandId: command.commandId,
    status,
    snapshot,
    transition,
  };
}

function conflictResult(
  command: GameLifecycleCommand,
  store: GameStateStore | undefined,
  code:
    | 'game_not_playing'
    | 'no_active_session'
    | 'no_active_battle'
    | 'closed_session'
    | 'closed_battle'
    | 'participant_not_eligible'
    | 'stale_version'
    | 'invalid_command_type'
    | 'command_fingerprint_conflict',
): GameLifecycleResult {
  const versions = getMockGameStateVersions(command.gameId);

  return {
    kind: 'conflict',
    commandId: command.commandId,
    status: 'rejected',
    snapshot: snapshotOf(store, command.gameId),
    conflict: {
      code,
      currentSessionStateVersion: versions.sessionStateVersion,
      currentBattleStateVersion: versions.battleStateVersion,
      retriable: code === 'stale_version',
    },
  };
}

function isSerializedGameState(value: GameStateSnapshot | SerializedGameState): value is SerializedGameState {
  return 'snapshot' in value && 'commands' in value && 'terminalCommands' in value && 'closedCommandIds' in value;
}

function isCommandType<T extends GameLifecycleCommand['commandType']>(
  command: GameLifecycleCommand,
  commandType: T,
): command is Extract<GameLifecycleCommand, { commandType: T }> {
  return command.commandType === commandType;
}

function cleanupBattle(store: GameStateStore, reason: 'battle_ended' | 'session_stopped'): GameCleanupSummary {
  const battle = store.battle;
  if (!battle || !store.session) {
    return {
      gameId: store.session?.gameId ?? 0,
      sessionId: store.session?.sessionId ?? '',
      battleId: null,
      reason,
      cancelledProcessIds: [],
      cancelledProcessEntityKeys: [],
      cancelledOfferIds: [],
      clearedPendingEffectEntityKeys: [],
      clearedCommittedActionEntityKeys: [],
      clearedActiveSpellIds: [],
      clearedMovementEntityKeys: [],
      clearedQuickRollEntityKeys: [],
      initiativeCleared: false,
      clearedMarkerKeys: [],
    };
  }

  const cancelledProcessIds = Object.keys(battle.combatProcesses);
  const cancelledProcessEntityKeys = Object.keys(battle.processSessions) as CombatEntityKey[];

  return {
    gameId: battle.gameId,
    sessionId: battle.sessionId,
    battleId: battle.battleId,
    reason,
    cancelledProcessIds,
    cancelledProcessEntityKeys,
    cancelledOfferIds: battle.checkOffers.map((offer) => offer.id),
    clearedPendingEffectEntityKeys: Object.keys(battle.pendingActionEffects) as CombatEntityKey[],
    clearedCommittedActionEntityKeys: Object.keys(battle.committedActionSessions) as CombatEntityKey[],
    clearedActiveSpellIds: battle.activeSpells.map((spell) => spell.id),
    clearedMovementEntityKeys: Object.keys(battle.currentSpeed) as CombatEntityKey[],
    clearedQuickRollEntityKeys: Object.keys(battle.quickRolls) as CombatEntityKey[],
    initiativeCleared: battle.initiative !== null,
    clearedMarkerKeys: Object.keys(battle.battleMarkers),
  };
}

function moveClosedBattleCommands(store: GameStateStore, battleId: string, gameId: number): void {
  const closed = closedCommandIds.get(gameId) ?? new Set<string>();
  for (const [commandId, record] of store.commands) {
    if (record.battleId === battleId || record.scope === 'battle') {
      closed.add(commandId);
      store.commands.delete(commandId);
    }
  }
  closedCommandIds.set(gameId, closed);
}

function closeAllCommands(store: GameStateStore, gameId: number): void {
  const closed = closedCommandIds.get(gameId) ?? new Set<string>();
  for (const commandId of store.commands.keys()) closed.add(commandId);
  for (const commandId of store.combatCommands.keys()) closed.add(commandId);
  closedCommandIds.set(gameId, closed);
}

function moveClosedCombatCommands(store: GameStateStore, battleId: string, gameId: number): void {
  const closed = closedCommandIds.get(gameId) ?? new Set<string>();
  for (const [commandId, record] of store.combatCommands) {
    if (record.battleId === battleId) {
      closed.add(commandId);
      store.combatCommands.delete(commandId);
    }
  }
  closedCommandIds.set(gameId, closed);
}

function isStale(expectedVersion: number | undefined, currentVersion: number): boolean {
  return expectedVersion !== undefined && expectedVersion !== currentVersion;
}

function fingerprintOf(command: GameLifecycleCommand): string {
  const { commandId: _commandId, ...request } = command;

  return JSON.stringify(normalizeForFingerprint(request));
}

function normalizeForFingerprint(value: unknown): unknown {
  if (Array.isArray(value)) return value.map((entry) => normalizeForFingerprint(entry));
  if (value === null || typeof value !== 'object') return value;

  return Object.fromEntries(
    Object.entries(value)
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([key, entry]) => [key, normalizeForFingerprint(entry)]),
  );
}
