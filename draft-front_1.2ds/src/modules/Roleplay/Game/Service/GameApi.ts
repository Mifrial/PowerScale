import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameStateSnapshot } from '@/modules/Roleplay/Game/Dto/GameStateSnapshot';
import type { GameLifecycleCommand } from '@/modules/Roleplay/Game/Dto/GameLifecycleCommand';
import type { GameLifecycleResult } from '@/modules/Roleplay/Game/Dto/GameLifecycleResult';
import type { GameCombatCommand } from '@/modules/Roleplay/Game/Dto/GameCombatCommand';
import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';
import type { GameRuntimeMutationCommand } from '@/modules/Roleplay/Game/Dto/GameRuntimeMutationCommand';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import type { UpdateGameMemberData } from '@/modules/Roleplay/Game/Dto/UpdateGameMemberData';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameInvitation } from '@/modules/Roleplay/Game/Dto/GameInvitation';
import type { GameModerationAction } from '@/modules/Roleplay/Game/Enum/GameModerationAction';
import type { GameCharacterModerationAction } from '@/modules/Roleplay/Game/Enum/GameCharacterModerationAction';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CreateNpcData } from '@/modules/Roleplay/Game/Dto/CreateNpcData';
import type { UpdateNpcData } from '@/modules/Roleplay/Game/Dto/UpdateNpcData';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterGameContext } from '@/modules/Roleplay/Game/Dto/CharacterGameContext';
import type { GameLoot } from '@/modules/Roleplay/Game/Dto/GameLoot';
import type { CreateLootData } from '@/modules/Roleplay/Game/Dto/CreateLootData';
import type { DistributeLootData } from '@/modules/Roleplay/Game/Dto/DistributeLootData';
import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Chronicle } from '@/modules/Roleplay/Game/Dto/Chronicle';
import type { ChronicleEntry } from '@/modules/Roleplay/Game/Dto/ChronicleEntry';
import type { CreateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/CreateChronicleEntryData';
import type { UpdateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/UpdateChronicleEntryData';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CreateCheckOfferData } from '@/modules/Roleplay/Game/Dto/CreateCheckOfferData';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';
import type { GameRuntimeEntityBatchRequest } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityBatchRequest';
import type { GameRuntimeEntityBatchResult } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityBatchResult';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import type { GameNpcListQuery } from '@/modules/Roleplay/Game/Dto/GameNpcListQuery';
import type { GameNpcListResult } from '@/modules/Roleplay/Game/Dto/GameNpcListResult';
import type { GameRoster } from '@/modules/Roleplay/Game/Dto/GameRoster';
import type { GameSheets } from '@/modules/Roleplay/Game/Dto/GameSheets';
import type { GameParticipantCandidateQuery } from '@/modules/Roleplay/Game/Dto/GameParticipantCandidateQuery';
import type { GameParticipantCandidateResult } from '@/modules/Roleplay/Game/Dto/GameParticipantCandidateResult';
import type { CharacterModerationProjection } from '@/modules/Roleplay/Game/Dto/CharacterModerationProjection';
import type { GameCharacterModerationCommand } from '@/modules/Roleplay/Game/Dto/GameCharacterModerationCommand';
import type { GameCharacterModerationResult } from '@/modules/Roleplay/Game/Dto/GameCharacterModerationResult';
import type { GameBattleSheets } from '@/modules/Roleplay/Game/Dto/GameBattleSheets';
import type { GamePhpStrikeAttack } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeAttack';
import type { GamePhpWideStrikeAttack } from '@/modules/Roleplay/Game/Dto/GamePhpWideStrikeAttack';
import type { GamePhpStrikeResult } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeResult';
import type { GamePhpTurnResult } from '@/modules/Roleplay/Game/Dto/GamePhpTurnResult';
import type { GamePhpWoundParty } from '@/modules/Roleplay/Game/Dto/GamePhpWoundParty';
import type { GameInitiativeRollRequest } from '@/modules/Roleplay/Game/Dto/GameInitiativeRollRequest';
import type { GameInitiativeRollResult } from '@/modules/Roleplay/Game/Dto/GameInitiativeRollResult';
import type { GameInitiativeOrderEntry } from '@/modules/Roleplay/Game/Dto/GameInitiativeOrderEntry';
import { GameApiError } from '@/modules/Roleplay/Game/Service/GameApiError';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';
import { gamePhpCardService } from '@/modules/Roleplay/Game/Service/Instance/gamePhpCardService';
import { characterHttpViewService } from '@/modules/Roleplay/Character/init';

/** HTTP-клиент `game.*` для PHP. Action, которых нет в бэке, не уходят в сеть. */
export class GameApi implements IGameApi {
  constructor(private readonly engine: Engine) {}

  async getGames(signal?: AbortSignal): Promise<Game[]> {
    const rows = await this.requireData<unknown>('game.getList', undefined, signal);

    return gamePhpCardService.listOf(rows);
  }

  async getGame(id: number, signal?: AbortSignal): Promise<GameDetail> {
    const row = await this.requireData<unknown>('game.get', { id }, signal);

    return this.detailWithMembers(gamePhpCardService.detailOf(row), signal);
  }

  async createGame(data: CreateGameData, signal?: AbortSignal): Promise<GameDetail> {
    const row = await this.requireData<unknown>('game.create', gamePhpCardService.createBody(data), signal);

    return this.detailWithMembers(gamePhpCardService.detailOf(row), signal);
  }

  async updateGame(id: number, data: CreateGameData, signal?: AbortSignal): Promise<GameDetail> {
    const row = await this.requireData<unknown>('game.update', gamePhpCardService.updateBody(id, data), signal);

    return this.detailWithMembers(gamePhpCardService.detailOf(row), signal);
  }

  async startGameSession(command: GameLifecycleCommand, signal?: AbortSignal): Promise<GameLifecycleResult> {
    const res = await this.engine.runAction<GameDetail | GameLifecycleResult>(
      'game.startSession',
      { gameId: command.gameId },
      signal,
    );
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new GameApiError('GAME_EMPTY_RESPONSE', 'Game session start failed');
    if ('kind' in res.data) return res.data;

    return {
      kind: 'transition',
      commandId: command.commandId,
      status: 'created',
      snapshot: {
        gameId: command.gameId,
        session: {
          gameId: command.gameId,
          sessionId: 'php',
          status: 'active',
          sessionStateVersion: 1,
          participantEntityKeys: [],
          activeBattleId: null,
          sessionMarkers: {},
          startedAt: '',
          updatedAt: '',
        },
        battle: null,
      },
      transition: {
        type: 'session_created',
        sessionId: 'php',
        battleId: null,
        endedBattleId: null,
        cleanup: null,
      },
    };
  }

  async startGameBattle(command: GameLifecycleCommand, signal?: AbortSignal): Promise<GameLifecycleResult> {
    if (command.commandType !== 'startBattle') {
      throw new GameApiError('INVALID_PARAMS', 'startBattle expected');
    }
    const participants = command.participants ?? [];
    const res = await this.engine.runAction<
      { battleId: number; version: number; ended: boolean } | GameLifecycleResult
    >(
      'game.startBattle',
      {
        gameId: command.gameId,
        idempotencyKey: command.commandId,
        participants,
      },
      signal,
    );
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new GameApiError('GAME_EMPTY_RESPONSE', 'Game battle start failed');
    if ('kind' in res.data) return res.data;
    gamePhpBattleContext.rememberBattle(res.data.battleId, res.data.version);
    const battleIdText = String(res.data.battleId);

    return {
      kind: 'transition',
      commandId: command.commandId,
      status: 'created',
      snapshot: {
        gameId: command.gameId,
        session: {
          gameId: command.gameId,
          sessionId: command.sessionId,
          status: 'active',
          sessionStateVersion: command.expectedSessionStateVersion,
          participantEntityKeys: [],
          activeBattleId: battleIdText,
          sessionMarkers: {},
          startedAt: '',
          updatedAt: '',
        },
        battle: {
          gameId: command.gameId,
          sessionId: command.sessionId,
          battleId: battleIdText,
          status: 'active',
          battleStateVersion: res.data.version,
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
          startedAt: '',
          updatedAt: '',
        },
      },
      transition: {
        type: 'battle_created',
        sessionId: command.sessionId,
        battleId: battleIdText,
        endedBattleId: null,
        cleanup: null,
      },
    };
  }

  async endGameBattle(command: GameLifecycleCommand, signal?: AbortSignal): Promise<GameLifecycleResult> {
    const res = await this.engine.runAction<GameLifecycleResult>('game.endBattle', command, signal);
    if (!res.data) throw new Error('Game battle end failed');

    return res.data;
  }

  async stopGameStateSession(_command: GameLifecycleCommand, _signal?: AbortSignal): Promise<GameLifecycleResult> {
    this.throwPhpUnsupported();
  }

  async stopGameSession(gameId: number, signal?: AbortSignal): Promise<GameDetail> {
    const res = await this.engine.runAction<GameDetail>('game.stopSession', { gameId }, signal);
    if (!res.data) throw new Error('Game stop session failed');

    return res.data;
  }

  async getGameStateSnapshot(gameId: number, _signal?: AbortSignal): Promise<GameStateSnapshot> {
    return { gameId, session: null, battle: null };
  }

  async submitCombatCommand(
    _command: GameCombatCommand,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async mutateRuntimeEntity(
    _command: GameRuntimeMutationCommand,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async getRuntimeEntity(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _projectionLevel: 'summary' | 'full',
    _signal?: AbortSignal,
  ): Promise<GameRuntimeEntityProjection | null> {
    return null;
  }

  async getRuntimeEntities(
    _gameId: number,
    request: GameRuntimeEntityBatchRequest,
    _signal?: AbortSignal,
  ): Promise<GameRuntimeEntityBatchResult> {
    return { projections: [], missingEntityKeys: request.entityKeys };
  }

  async getCharacterModerationProjections(
    _gameId: number,
    _characterIds: number[],
    _signal?: AbortSignal,
  ): Promise<CharacterModerationProjection[]> {
    return [];
  }

  async getGameCharacters(gameId: number, signal?: AbortSignal): Promise<GameCharacterMembership[]> {
    return this.getCharacterList({ gameId }, signal);
  }

  async submitCharacterToGame(
    gameId: number,
    characterId: number,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    const res = await this.engine.runAction<GameCharacterMembership>(
      'game.submitCharacter',
      { gameId, characterId },
      signal,
    );
    if (!res.data) throw new Error('Submit character failed');

    return res.data;
  }

  async moderateCharacter(
    _gameId: number,
    _characterId: number,
    _action: GameCharacterModerationAction,
    _signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    this.throwPhpUnsupported();
  }

  async moderateCharacterCommand(
    command: GameCharacterModerationCommand,
    signal?: AbortSignal,
  ): Promise<GameCharacterModerationResult> {
    const body = {
      gameId: command.gameId,
      characterId: command.characterId,
      membershipRevision: command.expectedMembershipRevision,
    };
    const status =
      command.action === 'approve' ? 'approved' : command.action === 'returnForRework' ? 'returned' : 'rejected';
    let membershipRow: Record<string, unknown> = {
      gameId: command.gameId,
      characterId: command.characterId,
      characterOwnerId: 0,
      status: status === 'approved' ? 'active' : status === 'rejected' ? 'left' : 'submitted',
      membershipRevision: command.expectedMembershipRevision,
    };
    if (command.action === 'approve') {
      membershipRow = await this.requireData(
        'game.approveCharacter',
        {
          ...body,
          actualVersion: command.expectedActualVersion,
        },
        signal,
      );
    } else if (command.action === 'returnForRework') {
      membershipRow = await this.requireData('game.returnCharacter', { ...body, reason: 'Возврат' }, signal);
    } else {
      await this.engine.runAction('game.rejectCharacter', body, signal);
    }

    return {
      kind: 'transition',
      commandId: command.commandId,
      status,
      membership: this.membershipOfPhp(membershipRow),
    };
  }

  async leaveGame(_gameId: number, _characterId: number, _signal?: AbortSignal): Promise<GameCharacterMembership> {
    this.throwPhpUnsupported();
  }

  async updateMembershipVisibility(
    _gameId: number,
    _characterId: number,
    _visibility: SheetVisibility,
    _signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    this.throwPhpUnsupported();
  }

  async updateCharacterGrants(
    _gameId: number,
    _characterId: number,
    _data: { osBonus: number; orBonus: number; olBonus: number },
    _signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    this.throwPhpUnsupported();
  }

  async submitCharacterMigration(
    _gameId: number,
    _characterId: number,
    _version: CharacterVersion,
    _expectedActualToken?: string,
    _signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    this.throwPhpUnsupported();
  }

  async getCharacterGameContexts(_characterId: number, _signal?: AbortSignal): Promise<CharacterGameContext[]> {
    return [];
  }

  async updateGameMember(
    gameId: number,
    userId: number,
    data: UpdateGameMemberData,
    signal?: AbortSignal,
  ): Promise<GameMember> {
    const row = await this.requireData<unknown>('game.updateMember', { gameId, userId, role: data.role }, signal);

    return { ...gamePhpCardService.memberOf(row), permissions: data.permissions };
  }

  async addGameMember(gameId: number, userId: number, role: GameMemberRole, signal?: AbortSignal): Promise<GameMember> {
    const row = await this.requireData<unknown>('game.addMember', { gameId, userId, role }, signal);

    return gamePhpCardService.memberOf(row);
  }

  async removeGameMember(gameId: number, userId: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction<void>('game.removeMember', { gameId, userId }, signal);
  }

  async getGameInvitations(gameId: number, signal?: AbortSignal): Promise<GameInvitation[]> {
    const res = await this.engine.runAction<unknown[]>('game.getInvitations', { gameId }, signal);

    return (res.data ?? []).map((row) => this.invitationOfPhp(row));
  }

  async getMyInvitations(signal?: AbortSignal): Promise<GameInvitation[]> {
    const res = await this.engine.runAction<unknown[]>('game.getMyInvitations', {}, signal);

    return (res.data ?? []).map((row) => this.invitationOfPhp(row));
  }

  async createInvitation(gameId: number, inviteeId: number, signal?: AbortSignal): Promise<GameInvitation> {
    const res = await this.engine.runAction<GameInvitation>('game.invite', { gameId, inviteeId }, signal);
    if (!res.data) throw new Error('Create invitation failed');

    return res.data;
  }

  async respondInvitation(
    invitationId: number,
    action: 'accept' | 'decline',
    signal?: AbortSignal,
  ): Promise<GameInvitation> {
    const res = await this.engine.runAction<GameInvitation>('game.respondInvitation', { invitationId, action }, signal);
    if (!res.data) throw new Error('Respond invitation failed');

    return res.data;
  }

  async getJoinRequests(gameId: number, signal?: AbortSignal): Promise<GameJoinRequest[]> {
    const res = await this.engine.runAction<GameJoinRequest[]>('game.getJoinRequests', { gameId }, signal);

    return res.data ?? [];
  }

  async requestJoinGame(gameId: number, signal?: AbortSignal): Promise<GameJoinRequest> {
    const res = await this.engine.runAction<GameJoinRequest>('game.requestJoin', { gameId }, signal);
    if (!res.data) throw new Error('Join request failed');

    return res.data;
  }

  async respondJoinRequest(
    gameId: number,
    userId: number,
    action: 'accept' | 'decline',
    signal?: AbortSignal,
  ): Promise<GameJoinRequest> {
    const res = await this.engine.runAction<GameJoinRequest>(
      'game.respondJoinRequest',
      { gameId, userId, action },
      signal,
    );
    if (!res.data) throw new Error('Respond join request failed');

    return res.data;
  }

  async getNpcSummaries(query: GameNpcListQuery, signal?: AbortSignal): Promise<GameNpcListResult> {
    const roster = await this.getRoster({ gameId: query.gameId }, signal);
    const needle = query.query?.trim().toLocaleLowerCase() ?? '';
    const items = roster.npcs.filter((npc) => needle === '' || npc.name.toLocaleLowerCase().includes(needle));

    return { items, nextCursor: null };
  }

  async getRoster(query: { gameId: number }, signal?: AbortSignal): Promise<GameRoster> {
    const data = await this.requireData<Record<string, unknown>>('game.getRoster', { gameId: query.gameId }, signal);
    const characters = Array.isArray(data.characters) ? data.characters : [];
    const npcs = Array.isArray(data.npcs) ? data.npcs : [];

    return {
      characters: characters.map((row) => {
        const record = row && typeof row === 'object' ? (row as Record<string, unknown>) : {};

        return {
          id: Number(record.id),
          name: typeof record.name === 'string' ? record.name : '',
          status: typeof record.status === 'string' ? record.status : '',
          actualVersion: Number(record.actualVersion ?? 0),
        };
      }),
      npcs: npcs.map((row) => {
        const record = row && typeof row === 'object' ? (row as Record<string, unknown>) : {};

        return {
          id: Number(record.id),
          name: typeof record.name === 'string' ? record.name : '',
          actualVersion: Number(record.actualVersion ?? 0),
        };
      }),
    };
  }

  async getSheets(
    query: { gameId: number; keys: { type: 'character' | 'npc'; id: number }[] },
    signal?: AbortSignal,
  ): Promise<GameSheets> {
    const data = await this.requireData<GameSheets>('game.getSheets', query, signal);

    return {
      sheets: Array.isArray(data.sheets) ? data.sheets : [],
      missing: Array.isArray(data.missing) ? data.missing : [],
    };
  }

  async getParticipantCandidates(
    _query: GameParticipantCandidateQuery,
    _signal?: AbortSignal,
  ): Promise<GameParticipantCandidateResult> {
    return { items: [], nextCursor: null };
  }

  async getNpc(gameId: number, npcId: number, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>('game.getNpc', { gameId, npcId }, signal);
    if (!res.data) throw new Error('NPC not found');

    return res.data;
  }

  async createNpc(gameId: number, data: CreateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>('game.createNpc', { gameId, ...data }, signal);
    if (!res.data) throw new Error('Create npc failed');

    return res.data;
  }

  async proposeNpc(_gameId: number, _data: CreateNpcData, _signal?: AbortSignal): Promise<GameNpc> {
    this.throwPhpUnsupported();
  }

  async updateNpc(npcId: number, data: UpdateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>('game.updateNpc', { npcId, ...data }, signal);
    if (!res.data) throw new Error('Update npc failed');

    return res.data;
  }

  async moderateNpc(_npcId: number, _action: GameModerationAction, _signal?: AbortSignal): Promise<GameNpc> {
    this.throwPhpUnsupported();
  }

  async deleteNpc(_npcId: number, _signal?: AbortSignal): Promise<void> {
    this.throwPhpUnsupported();
  }

  async getLoot(_gameId: number, _signal?: AbortSignal): Promise<GameLoot[]> {
    return [];
  }

  async addLoot(_gameId: number, _data: CreateLootData, _signal?: AbortSignal): Promise<GameLoot> {
    this.throwPhpUnsupported();
  }

  async updateLoot(_lootId: number, _data: CreateLootData, _signal?: AbortSignal): Promise<GameLoot> {
    this.throwPhpUnsupported();
  }

  async handoutLoot(_lootIds: number[], _signal?: AbortSignal): Promise<GameLoot[]> {
    this.throwPhpUnsupported();
  }

  async toggleLootInterest(_lootId: number, _signal?: AbortSignal): Promise<GameLoot> {
    this.throwPhpUnsupported();
  }

  async distributeLoot(_lootId: number, _data: DistributeLootData, _signal?: AbortSignal): Promise<GameLoot> {
    this.throwPhpUnsupported();
  }

  async deleteLoot(_lootId: number, _signal?: AbortSignal): Promise<void> {
    this.throwPhpUnsupported();
  }

  async getInitiative(gameId: number, _signal?: AbortSignal): Promise<GameInitiative> {
    return {
      gameId,
      active: false,
      participants: [],
      activeIndex: null,
      round: 1,
      updatedAt: '',
    };
  }

  async saveInitiative(_gameId: number, _data: GameInitiative, _signal?: AbortSignal): Promise<GameInitiative> {
    this.throwPhpUnsupported();
  }

  async rollInitiative(body: GameInitiativeRollRequest, signal?: AbortSignal): Promise<GameInitiativeRollResult> {
    const data = await this.requireData<GameInitiativeRollResult>('game.rollInitiative', body, signal);
    gamePhpBattleContext.rememberBattle(data.battleId, data.version);
    const order: GameInitiativeOrderEntry[] = Array.isArray(data.order) ? data.order : [];

    return { battleId: data.battleId, version: data.version, order };
  }

  async getCombatOverlays(_gameId: number, _signal?: AbortSignal): Promise<GameCombatOverlay[]> {
    return [];
  }

  async getPendingActionEffects(
    _gameId: number,
    _signal?: AbortSignal,
  ): Promise<Record<CombatEntityKey, PendingActionEffect[]>> {
    return {};
  }

  async setCombatActionEffects(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _effects: PendingActionEffect[],
    _signal?: AbortSignal,
  ): Promise<PendingActionEffect[]> {
    this.throwPhpUnsupported();
  }

  async getProcessSessions(_gameId: number, _signal?: AbortSignal): Promise<Record<CombatEntityKey, ProcessSession>> {
    return {};
  }

  async setProcessSession(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _session: ProcessSession | null,
    _signal?: AbortSignal,
  ): Promise<ProcessSession | null> {
    this.throwPhpUnsupported();
  }

  async getCommittedActionSessions(
    _gameId: number,
    _signal?: AbortSignal,
  ): Promise<Record<CombatEntityKey, CommittedActionSession>> {
    return {};
  }

  async setCommittedActionSession(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _session: CommittedActionSession | null,
    _signal?: AbortSignal,
  ): Promise<CommittedActionSession | null> {
    this.throwPhpUnsupported();
  }

  async getActiveSpells(_gameId: number, _signal?: AbortSignal): Promise<ActiveSpell[]> {
    return [];
  }

  async upsertActiveSpell(_gameId: number, _spell: ActiveSpell, _signal?: AbortSignal): Promise<ActiveSpell> {
    this.throwPhpUnsupported();
  }

  async dropActiveSpell(_gameId: number, _id: string, _signal?: AbortSignal): Promise<void> {
    this.throwPhpUnsupported();
  }

  async getCurrentSpeed(_gameId: number, _entityKey: CombatEntityKey, _signal?: AbortSignal): Promise<CurrentSpeed> {
    return this.emptyCurrentSpeed();
  }

  async setCurrentSpeed(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _currentSpeed: CurrentSpeed,
    _signal?: AbortSignal,
  ): Promise<CurrentSpeed> {
    this.throwPhpUnsupported();
  }

  async setCombatResource(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _ruleCode: string,
    _current: DimensionalNumberValue,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async setCombatConcentrationUsedInCycle(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _used: boolean,
    _signal?: AbortSignal,
  ): Promise<GameCombatOverlay> {
    this.throwPhpUnsupported();
  }

  async setCombatWoundBandagedOnce(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _bandaged: boolean,
    _signal?: AbortSignal,
  ): Promise<GameCombatOverlay> {
    this.throwPhpUnsupported();
  }

  async addCombatState(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _state: CharacterStateValue,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async replaceCombatState(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _index: number,
    _state: CharacterStateValue,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async setCombatStateValue(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _index: number,
    _value?: number,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async removeCombatState(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _index: number,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async setCombatItemEquipped(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _itemId: number,
    _equipped: boolean,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async setCombatItemOccupyHands(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _itemId: number,
    _occupyHands: number,
    _signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    this.throwPhpUnsupported();
  }

  async getQuickRolls(_gameId: number, _signal?: AbortSignal): Promise<Record<CombatEntityKey, string[]>> {
    return {};
  }

  async addQuickRoll(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _ruleCode: string,
    _signal?: AbortSignal,
  ): Promise<string[]> {
    this.throwPhpUnsupported();
  }

  async removeQuickRoll(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _ruleCode: string,
    _signal?: AbortSignal,
  ): Promise<string[]> {
    this.throwPhpUnsupported();
  }

  async getChronicle(gameId: number, signal?: AbortSignal): Promise<Chronicle> {
    const res = await this.engine.runAction<Chronicle>('game.getChronicle', { gameId }, signal);
    if (!res.data) throw new Error('Get chronicle failed');

    return res.data;
  }

  async getChronicleEntries(gameId: number, signal?: AbortSignal): Promise<ChronicleEntry[]> {
    const res = await this.engine.runAction<ChronicleEntry[]>('game.getChronicleEntries', { gameId }, signal);

    return res.data ?? [];
  }

  async createChronicleEntry(
    gameId: number,
    data: CreateChronicleEntryData,
    signal?: AbortSignal,
  ): Promise<ChronicleEntry> {
    const res = await this.engine.runAction<ChronicleEntry>('game.createChronicleEntry', { gameId, ...data }, signal);
    if (!res.data) throw new Error('Create chronicle entry failed');

    return res.data;
  }

  async updateChronicleEntry(
    entryId: number,
    data: UpdateChronicleEntryData,
    signal?: AbortSignal,
  ): Promise<ChronicleEntry> {
    const res = await this.engine.runAction<ChronicleEntry>('game.updateChronicleEntry', { entryId, ...data }, signal);
    if (!res.data) throw new Error('Update chronicle entry failed');

    return res.data;
  }

  async deleteChronicleEntry(entryId: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction<void>('game.deleteChronicleEntry', { entryId }, signal);
  }

  async updatePersonalNotes(_gameId: number, _notes: string, _signal?: AbortSignal): Promise<GameDetail> {
    this.throwPhpUnsupported();
  }

  async createCheckOffer(_gameId: number, _data: CreateCheckOfferData, _signal?: AbortSignal): Promise<CheckOffer> {
    this.throwPhpUnsupported();
  }

  async reviseCheckOffer(
    _offerId: number,
    _actorKey: CombatEntityKey,
    _proposal: CheckOfferProposal,
    _signal?: AbortSignal,
  ): Promise<CheckOffer> {
    this.throwPhpUnsupported();
  }

  async acceptCheckOffer(
    _offerId: number,
    _actorKey: CombatEntityKey,
    _proposal?: CheckOfferProposal,
    _signal?: AbortSignal,
  ): Promise<CheckOffer> {
    this.throwPhpUnsupported();
  }

  async cancelCheckOffer(_offerId: number, _actorKey: CombatEntityKey, _signal?: AbortSignal): Promise<CheckOffer> {
    this.throwPhpUnsupported();
  }

  async getPendingCheckOffers(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _signal?: AbortSignal,
  ): Promise<CheckOffer[]> {
    return [];
  }

  async getCheckOffersForEntity(
    _gameId: number,
    _entityKey: CombatEntityKey,
    _signal?: AbortSignal,
  ): Promise<CheckOffer[]> {
    return [];
  }

  async getCheckOffersForGame(_gameId: number, _signal?: AbortSignal): Promise<CheckOffer[]> {
    return [];
  }

  async getCharacterList(query: { gameId: number }, signal?: AbortSignal): Promise<GameCharacterMembership[]> {
    const rows = await this.requireData<Record<string, unknown>[]>(
      'game.getCharacterList',
      { gameId: query.gameId },
      signal,
    );
    let names = new Map<number, string>();
    try {
      const roster = await this.getRoster({ gameId: query.gameId }, signal);
      names = new Map(roster.characters.map((entry) => [entry.id, entry.name]));
    } catch {
      names = new Map();
    }

    return rows.map((row) => {
      const membership = this.membershipOfPhp(row);

      return {
        ...membership,
        characterName: names.get(membership.characterId) || membership.characterName,
      };
    });
  }

  async getBattleSheets(query: { gameId: number; battleId: number }, signal?: AbortSignal): Promise<GameBattleSheets> {
    return this.requireData<GameBattleSheets>('game.getBattleSheets', query, signal);
  }

  async declareStrike(
    body: {
      gameId: number;
      battleId: number;
      idempotencyKey: string;
      expectedVersion: number;
      attack: GamePhpStrikeAttack;
    },
    signal?: AbortSignal,
  ): Promise<GamePhpStrikeResult> {
    const result = await this.requireData<GamePhpStrikeResult>('game.declareStrike', body, signal);
    gamePhpBattleContext.rememberDebt(result.debt === true, result.version);

    return result;
  }

  async resolveStrike(
    body: {
      gameId: number;
      battleId: number;
      idempotencyKey: string;
      expectedVersion: number;
      expectedSheetVersion: number;
      defense: {
        reaction: string;
        blockItemInventoryId?: number;
        blockItemProfileIndex?: number;
      };
    },
    signal?: AbortSignal,
  ): Promise<GamePhpStrikeResult> {
    const result = await this.requireData<GamePhpStrikeResult>('game.resolveStrike', body, signal);
    gamePhpBattleContext.rememberBattle(body.battleId, result.version);

    return result;
  }

  async declareWideStrike(
    body: {
      gameId: number;
      battleId: number;
      idempotencyKey: string;
      expectedVersion: number;
      attack: GamePhpWideStrikeAttack;
    },
    signal?: AbortSignal,
  ): Promise<GamePhpStrikeResult> {
    const result = await this.requireData<GamePhpStrikeResult>('game.declareWideStrike', body, signal);
    gamePhpBattleContext.rememberDebt(result.debt === true, result.version);

    return result;
  }

  async resolveWideStrike(
    body: {
      gameId: number;
      battleId: number;
      idempotencyKey: string;
      expectedVersion: number;
      defense: {
        defenses: {
          reaction: string;
          expectedSheetVersion: number;
          blockItemInventoryId?: number;
          blockItemProfileIndex?: number;
        }[];
      };
    },
    signal?: AbortSignal,
  ): Promise<GamePhpStrikeResult> {
    const result = await this.requireData<GamePhpStrikeResult>('game.resolveWideStrike', body, signal);
    gamePhpBattleContext.rememberBattle(body.battleId, result.version);

    return result;
  }

  async woundAction(
    body: {
      gameId: number;
      idempotencyKey: string;
      operation: 'hold' | 'release' | 'bandage';
      actor: GamePhpWoundParty;
      target: GamePhpWoundParty;
      stateId: number;
      actionRuleCode: string;
      expectedSheetVersion: number;
      battleId: number;
      expectedBattleVersion: number;
    },
    signal?: AbortSignal,
  ): Promise<GamePhpTurnResult> {
    const result = await this.requireData<GamePhpTurnResult>('game.woundAction', body, signal);
    gamePhpBattleContext.rememberBattle(body.battleId, result.version ?? body.expectedBattleVersion);

    return result;
  }

  async endTurn(
    body: {
      gameId: number;
      idempotencyKey: string;
      battleId: number;
      expectedBattleVersion: number;
      expectedSheetVersion: number;
    },
    signal?: AbortSignal,
  ): Promise<GamePhpTurnResult> {
    return this.turnCommand('game.endTurn', body, signal);
  }

  async continueAction(
    body: {
      gameId: number;
      idempotencyKey: string;
      battleId: number;
      expectedBattleVersion: number;
      expectedSheetVersion: number;
    },
    signal?: AbortSignal,
  ): Promise<GamePhpTurnResult> {
    return this.turnCommand('game.continueAction', body, signal);
  }

  async abortAction(
    body: {
      gameId: number;
      idempotencyKey: string;
      battleId: number;
      expectedBattleVersion: number;
      expectedSheetVersion: number;
    },
    signal?: AbortSignal,
  ): Promise<GamePhpTurnResult> {
    return this.turnCommand('game.abortAction', body, signal);
  }

  private async turnCommand(
    action: string,
    body: {
      gameId: number;
      idempotencyKey: string;
      battleId: number;
      expectedBattleVersion: number;
      expectedSheetVersion: number;
    },
    signal?: AbortSignal,
  ): Promise<GamePhpTurnResult> {
    const result = await this.requireData<GamePhpTurnResult>(action, body, signal);
    gamePhpBattleContext.rememberBattle(body.battleId, result.version);
    gamePhpBattleContext.rememberDebt(false, result.version);

    return result;
  }

  private async detailWithMembers(detail: GameDetail, signal?: AbortSignal): Promise<GameDetail> {
    detail.members = await this.readMemberList(detail.game.id, signal);

    return detail;
  }

  private async readMemberList(gameId: number, signal?: AbortSignal): Promise<GameMember[]> {
    try {
      const rows = await this.requireData<unknown>('game.getMemberList', { gameId }, signal);

      return gamePhpCardService.membersOf(rows);
    } catch (error) {
      if (error instanceof GameApiError && error.code === 'GAME_NOT_FOUND') return [];

      throw error;
    }
  }

  /** Локальный отказ без HTTP: PHP ещё не регистрирует этот action. */
  private throwPhpUnsupported(): never {
    throw new GameApiError('GAME_PHP_UNSUPPORTED', 'Действие пока не реализовано на PHP');
  }

  private emptyCurrentSpeed(): CurrentSpeed {
    return {
      horizontal: { stepsPerActionPoint: 0, direction: null },
      vertical: { stepsPerActionPoint: 0, direction: null },
    };
  }

  private async requireData<T>(action: string, payload: unknown, signal?: AbortSignal): Promise<T> {
    const res = await this.engine.runAction<T>(action, payload, signal);
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.success || res.data === null) {
      throw new GameApiError('GAME_EMPTY_RESPONSE', `${action} failed`);
    }

    return res.data;
  }

  private invitationOfPhp(row: unknown): GameInvitation {
    const record = row && typeof row === 'object' ? (row as Record<string, unknown>) : {};
    const status = record.status;
    const invitationStatus =
      status === 'sent' || status === 'viewed' || status === 'accepted' || status === 'declined' ? status : 'sent';

    return {
      id: Number(record.id),
      gameId: Number(record.gameId),
      inviterId: Number(record.inviterId),
      inviterName: typeof record.inviterName === 'string' ? record.inviterName : '',
      inviteeId: Number(record.inviteeId),
      inviteeName: typeof record.inviteeName === 'string' ? record.inviteeName : '',
      status: invitationStatus,
      createdAt: typeof record.createdAt === 'string' ? record.createdAt : '',
    };
  }

  private membershipOfPhp(row: Record<string, unknown>): GameCharacterMembership {
    const status = row.status === 'submitted' || row.status === 'left' ? row.status : 'active';
    const review = row.reviewState === 'changes_pending' || row.reviewState === 'returned' ? row.reviewState : 'clean';

    return {
      gameId: Number(row.gameId),
      characterId: Number(row.characterId),
      characterName: typeof row.characterName === 'string' ? row.characterName : '',
      characterOwnerId: Number(row.characterOwnerId),
      characterOwnerName: '',
      role: 'player',
      membershipStatus: status,
      approvedCharacterVersion: characterHttpViewService.versionFromStored(row.approvedCharacterVersion),
      reviewState: review,
      membershipRevision: Number(row.membershipRevision ?? 0),
      returnedAt: row.returnedAt == null ? null : String(row.returnedAt),
      returnReason: typeof row.returnReason === 'string' ? row.returnReason : null,
      returnMessageId: typeof row.returnMessageId === 'number' ? row.returnMessageId : null,
      overlay: null,
      visibility: Array.isArray(row.sectionVisibility) ? row.sectionVisibility : [],
      osBonus: Number(row.osBonus ?? 0),
      orBonus: Number(row.orBonus ?? 0),
      olBonus: Number(row.olBonus ?? 0),
      updatedAt: '',
    };
  }
}
