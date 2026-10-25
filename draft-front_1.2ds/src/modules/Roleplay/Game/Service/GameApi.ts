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
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { CharacterGameContext } from '@/modules/Roleplay/Game/Dto/CharacterGameContext';
import type { GameLoot } from '@/modules/Roleplay/Game/Dto/GameLoot';
import type { CreateLootData } from '@/modules/Roleplay/Game/Dto/CreateLootData';
import type { DistributeLootData } from '@/modules/Roleplay/Game/Dto/DistributeLootData';
import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';
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
import type { GamePhpTurnResult } from '@/modules/Roleplay/Game/Dto/GamePhpTurnResult';
import type { GamePhpWoundParty } from '@/modules/Roleplay/Game/Dto/GamePhpWoundParty';
import type { GamePhpRunActionRequest } from '@/modules/Roleplay/Game/Dto/GamePhpRunActionRequest';
import type { GamePhpRunActionResult } from '@/modules/Roleplay/Game/Dto/GamePhpRunActionResult';
import type { GameInitiativeRollRequest } from '@/modules/Roleplay/Game/Dto/GameInitiativeRollRequest';
import type { GameInitiativeRollResult } from '@/modules/Roleplay/Game/Dto/GameInitiativeRollResult';
import type { GameInitiativeOrderEntry } from '@/modules/Roleplay/Game/Dto/GameInitiativeOrderEntry';
import { GameApiError } from '@/modules/Roleplay/Game/Service/GameApiError';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';
import { gamePhpCardService } from '@/modules/Roleplay/Game/Service/Instance/gamePhpCardService';
import { characterHttpSaveService, characterHttpViewService } from '@/modules/Roleplay/Character/init';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';

/** HTTP-клиент `game.*` для PHP. Action, которых нет в бэке, не уходят в сеть. */
export class GameApi implements IGameApi {
  constructor(private readonly engine: Engine) {}

  async getGames(signal?: AbortSignal): Promise<Game[]> {
    const rows = await this.requireData<unknown>('game.getList', undefined, signal);

    return gamePhpCardService.listOf(rows);
  }

  async getGame(id: number, signal?: AbortSignal): Promise<GameDetail> {
    const row = await this.requireData<unknown>('game.get', { id }, signal);
    this.rememberPhpBattle(id, row);
    await this.applyPhpBattleNames(id, signal);

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
    if (res.error?.code === 'GAME_CONFLICT') {
      return this.phpSessionTransition(command, 'already_active', 'session_already_active');
    }
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new GameApiError('GAME_EMPTY_RESPONSE', 'Game session start failed');
    if ('kind' in res.data) return res.data;

    return this.phpSessionTransition(command, 'created', 'session_created');
  }

  async startGameBattle(command: GameLifecycleCommand, signal?: AbortSignal): Promise<GameLifecycleResult> {
    if (command.commandType !== 'startBattle') {
      throw new GameApiError('INVALID_PARAMS', 'startBattle expected');
    }
    const participants = command.participants ?? [];
    const res = await this.engine.runAction<
      { battleId: number; version: number; ended: boolean; order?: GameInitiativeOrderEntry[] } | GameLifecycleResult
    >(
      'game.startBattle',
      {
        gameId: command.gameId,
        idempotencyKey: command.commandId,
        participants,
        ...(command.rolls !== undefined ? { rolls: command.rolls } : {}),
      },
      signal,
    );
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new GameApiError('GAME_EMPTY_RESPONSE', 'Game battle start failed');
    if ('kind' in res.data) return res.data;
    gamePhpBattleContext.rememberBattle(res.data.battleId, res.data.version);
    if (res.data.order !== undefined) {
      gamePhpBattleContext.rememberOrder(res.data.order);
      gamePhpBattleContext.rememberScale(this.scaleOf(command.gameId, res.data.order, 0, 1));
    }
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
    if (command.commandType !== 'endBattle') {
      throw new GameApiError('INVALID_PARAMS', 'endBattle expected');
    }
    const battleId = Number(command.battleId);
    const res = await this.engine.runAction<{ ended?: boolean }>(
      'game.endBattle',
      {
        gameId: command.gameId,
        battleId,
        idempotencyKey: command.commandId,
        expectedVersion: command.expectedBattleStateVersion,
      },
      signal,
    );
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new GameApiError('GAME_EMPTY_RESPONSE', 'Game battle end failed');
    gamePhpBattleContext.clear();

    return {
      kind: 'transition',
      commandId: command.commandId,
      status: 'ended',
      snapshot: { gameId: command.gameId, session: null, battle: null },
      transition: {
        type: 'battle_ended',
        sessionId: command.sessionId,
        battleId: null,
        endedBattleId: command.battleId,
        cleanup: null,
      },
    };
  }

  async stopGameStateSession(_command: GameLifecycleCommand, _signal?: AbortSignal): Promise<GameLifecycleResult> {
    this.throwPhpUnsupported();
  }

  async stopGameSession(gameId: number, signal?: AbortSignal): Promise<GameDetail> {
    const row = await this.requireData<unknown>('game.stopSession', { gameId }, signal);
    gamePhpBattleContext.clear();

    return this.detailWithMembers(gamePhpCardService.detailOf(row), signal);
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
          id: Number(record.id ?? record.npcId),
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
    const res = await this.engine.runAction<unknown>('game.getNpc', { gameId, npcId }, signal);
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new Error('NPC not found');

    return this.npcOfPhp(res.data);
  }

  async createNpc(gameId: number, data: CreateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>(
      'game.createNpc',
      {
        gameId,
        name: data.name,
        visibility: { scope: 'all', userIds: [], sections: [] },
      },
      signal,
    );
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new Error('Create npc failed');

    return this.npcOfPhp(res.data);
  }

  async proposeNpc(_gameId: number, _data: CreateNpcData, _signal?: AbortSignal): Promise<GameNpc> {
    this.throwPhpUnsupported();
  }

  async updateNpc(npcId: number, data: UpdateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const unlimited: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null };
    const config = data.creationConfig ?? unlimited;
    const choices = data.choices
      ? characterHttpSaveService.documentOf(data.choices, config)
      : data.version
        ? characterHttpSaveService.documentFromVersion(data.version, unlimited)
        : characterHttpSaveService.documentOf(
            {
              name: data.name,
              shortDescription: data.shortDescription,
              fullDescription: data.fullDescription,
              spaceId: data.spaceId ?? 0,
              spaceCode: data.spaceCode ?? '',
              rulesRevision: data.rulesRevision ?? 0,
              raceRuleCode: null,
              characteristicPurchases: [],
              abilities: [],
              resources: [],
              inventory: [],
              states: [],
              money: 0,
              ageYears: null,
              olTotal: 0,
            } satisfies CharacterBuild,
            unlimited,
          );
    const payload: Record<string, unknown> = {
      gameId: data.gameId,
      npcId,
      name: data.name,
      visibility: this.phpNpcVisibilityOf(data.visibility),
      choices,
      expectedNpcActualVersion: data.expectedNpcActualVersion,
    };
    if (data.spaceId !== undefined) payload.spaceId = data.spaceId;
    if (data.spaceCode !== undefined) payload.spaceCode = data.spaceCode;
    if (data.rulesRevision !== undefined) payload.rulesRevision = data.rulesRevision;
    const res = await this.engine.runAction<unknown>('game.updateNpc', payload, signal);
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new Error('Update npc failed');
    const body = res.data !== null && typeof res.data === 'object' ? (res.data as Record<string, unknown>) : {};
    const problems = Array.isArray(body.problems) ? body.problems : [];
    if (body.kind === 'conflicts') {
      const messages = problems
        .map((row) => (row !== null && typeof row === 'object' ? (row as Record<string, unknown>).message : null))
        .filter((message): message is string => typeof message === 'string' && message !== '');

      throw new GameApiError('CHARACTER_INVALID', messages.join('; ') || 'Update npc failed', problems);
    }

    return this.npcOfPhp(res.data);
  }

  async translateNpc(npcId: number, data: UpdateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const unlimited: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null };
    const choices = data.choices
      ? characterHttpSaveService.documentOf(data.choices, data.creationConfig ?? unlimited)
      : data.version
        ? characterHttpSaveService.documentFromVersion(data.version, unlimited)
        : null;
    const payload: Record<string, unknown> = {
      gameId: data.gameId,
      npcId,
      expectedNpcActualVersion: data.expectedNpcActualVersion,
      name: data.name,
    };
    if (choices) payload.choices = choices;
    const res = await this.engine.runAction<unknown>('game.translateNpc', payload, signal);
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new Error('Translate npc failed');
    const body = res.data !== null && typeof res.data === 'object' ? (res.data as Record<string, unknown>) : {};
    const problems = Array.isArray(body.problems) ? body.problems : [];
    if (body.kind === 'conflicts') {
      const messages = problems
        .map((row) => (row !== null && typeof row === 'object' ? (row as Record<string, unknown>).message : null))
        .filter((message): message is string => typeof message === 'string' && message !== '');

      throw new GameApiError('CHARACTER_INVALID', messages.join('; ') || 'Translate npc failed', problems);
    }

    return this.npcOfPhp(res.data);
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

  async saveInitiative(gameId: number, data: GameInitiative, signal?: AbortSignal): Promise<GameInitiative> {
    const battleId = gamePhpBattleContext.battleId;
    if (battleId === null || !data.active) {
      this.throwPhpUnsupported();
    }
    const participants = data.participants.map((participant) => ({
      type: participant.kind,
      id: participant.entityId ?? 0,
    }));
    const res = await this.engine.runAction<{
      battleId: number;
      version: number;
      ended: boolean;
      order?: GameInitiativeOrderEntry[];
    }>(
      'game.setBattleRoster',
      {
        gameId,
        battleId,
        idempotencyKey: createRandomId(),
        participants,
        expectedVersion: gamePhpBattleContext.battleVersion,
      },
      signal,
    );
    if (res.error) throw GameApiError.fromActionError(res.error);
    if (!res.data) throw new GameApiError('GAME_EMPTY_RESPONSE', 'Game battle roster failed');
    gamePhpBattleContext.rememberBattle(res.data.battleId, res.data.version);
    if (res.data.order !== undefined) {
      gamePhpBattleContext.rememberOrder(res.data.order);
      const scale = this.scaleOf(gameId, res.data.order, 0, data.round);
      gamePhpBattleContext.rememberScale(scale);

      return scale;
    }

    return data;
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
    return gamePhpBattleContext.pendingActionEffects as Record<CombatEntityKey, PendingActionEffect[]>;
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
    const result = await this.requireData<GameBattleSheets>('game.getBattleSheets', query, signal);
    gamePhpBattleContext.rememberOpenWideStrike(result.openWideStrike ?? null);

    return result;
  }

  async runAction(body: GamePhpRunActionRequest, signal?: AbortSignal): Promise<GamePhpRunActionResult> {
    const result = await this.requireData<GamePhpRunActionResult>('game.runAction', body, signal);
    if (body.battleId > 0) gamePhpBattleContext.rememberBattle(body.battleId, result.version);
    if (body.action.kind === 'strike' && body.action.phase === 'declare') {
      gamePhpBattleContext.rememberDebt(result.debt === true, result.version);
    }

    if (body.action.kind === 'action') {
      const actor = body.action.actor;
      gamePhpBattleContext.rememberPending(result.pendingActionEffects ?? {});
      if (actor) gamePhpBattleContext.rememberSheet(`${actor.type}:${actor.id}`, result.sheetVersion ?? 1);
    }

    if (body.action.kind === 'strike' && body.action.phase === 'respond') {
      for (const row of result.strikes ?? []) {
        if (typeof row.sheetVersion === 'number') {
          gamePhpBattleContext.rememberSheet(`${row.target.type}:${row.target.id}`, row.sheetVersion);
        }
      }
    }

    return result;
  }

  async manualSheet(
    body: {
      gameId: number;
      idempotencyKey: string;
      operation: 'setResource' | 'addState' | 'patchState' | 'removeState';
      target: GamePhpWoundParty;
      expectedSheetVersion: number;
      details: Record<string, unknown>;
    },
    signal?: AbortSignal,
  ): Promise<{ actualVersion: number }> {
    return this.requireData<{ actualVersion: number }>('game.manualSheet', body, signal);
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
    if (result.pendingActionEffects) {
      gamePhpBattleContext.rememberPending(result.pendingActionEffects);
    }

    return result;
  }

  private phpSessionTransition(
    command: GameLifecycleCommand,
    status: 'created' | 'already_active',
    type: 'session_created' | 'session_already_active',
  ): GameLifecycleResult {
    return {
      kind: 'transition',
      commandId: command.commandId,
      status,
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
        type,
        sessionId: 'php',
        battleId: null,
        endedBattleId: null,
        cleanup: null,
      },
    };
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

  /**
   * Карточка NPC: оболочка игры + тот же PHP-лист, что у персонажа (`choices`/`sheet`).
   */
  private npcOfPhp(row: unknown): GameNpc {
    const record = row && typeof row === 'object' ? (row as Record<string, unknown>) : {};
    const version = characterHttpViewService.versionFromStored(record.version);
    const status = record.status === 'proposed' ? 'proposed' : 'active';

    return {
      id: typeof record.npcId === 'number' ? record.npcId : Number(record.id),
      gameId: Number(record.gameId),
      name: typeof record.name === 'string' ? record.name : (version?.name ?? ''),
      shortDescription: version?.shortDescription ?? null,
      fullDescription: version?.fullDescription ?? null,
      tags: Array.isArray(record.tags) ? record.tags.filter((tag): tag is string => typeof tag === 'string') : [],
      version,
      actualVersion: typeof record.actualVersion === 'number' ? record.actualVersion : 1,
      status,
      proposedBy: this.npcProposedByOf(record.proposedBy),
      visibility: this.npcVisibilityOf(record.visibility),
      updatedAt: typeof record.updatedAt === 'string' ? record.updatedAt : '',
    };
  }

  private npcProposedByOf(raw: unknown): GameNpc['proposedBy'] {
    if (raw === null || typeof raw !== 'object') return null;
    const record = raw as Record<string, unknown>;
    const userId = typeof record.userId === 'number' ? record.userId : 0;
    if (userId <= 0) return null;

    return { userId, userName: typeof record.userName === 'string' ? record.userName : '' };
  }

  private npcVisibilityOf(raw: unknown): SheetVisibility {
    if (Array.isArray(raw)) return raw as SheetVisibility;
    const record = raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {};
    const allowed = new Set<string>(SHEET_VISIBLE_SECTIONS);
    const sections = Array.isArray(record.sections)
      ? record.sections.filter(
          (section): section is SheetSection => typeof section === 'string' && allowed.has(section),
        )
      : [...SHEET_VISIBLE_SECTIONS];
    if (record.scope === 'gm') return [{ audience: 'gm', sections }];
    if (record.scope === 'users') {
      const userIds = Array.isArray(record.userIds)
        ? record.userIds.filter((id): id is number => typeof id === 'number')
        : [];

      return [{ audience: userIds, sections }];
    }

    return [{ audience: 'all', sections }];
  }

  private phpNpcVisibilityOf(visibility: SheetVisibility): {
    scope: string;
    userIds: number[];
    sections: string[];
  } {
    const allowed = new Set(['characteristics', 'abilities', 'inventory', 'resources']);
    const rule = visibility[0];
    const sections = (rule?.sections ?? []).filter((section) => allowed.has(section));
    if (!rule || rule.audience === 'all') return { scope: 'all', userIds: [], sections };
    if (rule.audience === 'gm') return { scope: 'gm', userIds: [], sections };

    return { scope: 'users', userIds: rule.audience, sections };
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
      canStartSession: row.canStartSession === true,
      isActiveSessionParticipant: row.isActiveSessionParticipant === true,
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

  private rememberPhpBattle(gameId: number, row: unknown): void {
    if (row === null || typeof row !== 'object') return;
    const battle = (row as Record<string, unknown>).battle;
    if (battle === null || typeof battle !== 'object') {
      gamePhpBattleContext.clear();

      return;
    }
    const record = battle as Record<string, unknown>;
    const battleId = record.battleId;
    const version = record.version;
    if (typeof battleId !== 'number' || typeof version !== 'number') return;
    gamePhpBattleContext.rememberBattle(battleId, version);
    const order = Array.isArray(record.order) ? (record.order as GameInitiativeOrderEntry[]) : [];
    if (order.length > 0) {
      gamePhpBattleContext.rememberOrder(order);
      const activeIndex = typeof record.activeIndex === 'number' ? record.activeIndex : 0;
      const round = typeof record.round === 'number' && record.round > 0 ? record.round : 1;
      gamePhpBattleContext.rememberScale(this.scaleOf(gameId, order, activeIndex, round));
    }
    gamePhpBattleContext.rememberOpenStrike(this.openStrikeOf(record.openStrike));
    gamePhpBattleContext.rememberPending(this.pendingOf(record.pendingActionEffects));
  }

  private pendingOf(value: unknown): Record<string, PendingActionEffect[]> {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return {};
    const pending: Record<string, PendingActionEffect[]> = {};
    for (const [key, rows] of Object.entries(value)) {
      if (!Array.isArray(rows)) continue;
      const effects: PendingActionEffect[] = [];
      for (const row of rows) {
        if (row === null || typeof row !== 'object') continue;
        const entry = row as Record<string, unknown>;
        const effect = entry.effect;
        if (typeof entry.sourceRuleCode !== 'string' || effect === null || typeof effect !== 'object') continue;
        effects.push({ sourceRuleCode: entry.sourceRuleCode, effect: effect as PendingActionEffect['effect'] });
      }
      if (effects.length > 0) pending[key] = effects;
    }

    return pending;
  }

  private openStrikeOf(value: unknown): GamePhpOpenStrike | null {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return null;
    const row = value as Record<string, unknown>;
    const attackerId = this.intField(row.attackerId);
    const defenderId = this.intField(row.defenderId);
    const itemInventoryId = this.intField(row.itemInventoryId);
    const profileIndex = this.intField(row.profileIndex);
    if (typeof row.attackerKind !== 'string' || attackerId === null) return null;
    if (typeof row.defenderKind !== 'string' || defenderId === null) return null;
    if (typeof row.actionRuleCode !== 'string' || typeof row.itemRuleCode !== 'string') return null;
    if (itemInventoryId === null || typeof row.profileType !== 'string' || profileIndex === null) return null;

    return {
      attackerKind: row.attackerKind,
      attackerId,
      defenderKind: row.defenderKind,
      defenderId,
      actionRuleCode: row.actionRuleCode,
      itemInventoryId,
      itemRuleCode: row.itemRuleCode,
      profileType: row.profileType,
      profileIndex,
    };
  }

  private intField(value: unknown): number | null {
    if (typeof value === 'number' && Number.isInteger(value) && value >= 0) return value;
    if (typeof value === 'string' && value.trim() !== '') {
      const parsed = Number(value);

      return Number.isInteger(parsed) && parsed >= 0 ? parsed : null;
    }

    return null;
  }

  private scaleOf(
    gameId: number,
    order: GameInitiativeOrderEntry[],
    activeIndex: number,
    round: number,
  ): GameInitiative {
    return {
      gameId,
      active: order.length > 0,
      participants: order.map((entry) => ({
        id: `${entry.type}:${entry.id}`,
        name: `${entry.type}:${entry.id}`,
        kind: entry.type,
        entityId: entry.id,
      })),
      activeIndex,
      round,
      updatedAt: '',
    };
  }

  private async applyPhpBattleNames(gameId: number, signal?: AbortSignal): Promise<void> {
    const scale = gamePhpBattleContext.getScale();
    if (scale === null) return;
    try {
      const roster = await this.getRoster({ gameId }, signal);
      const names = new Map<string, string>();
      for (const character of roster.characters) {
        names.set(`character:${character.id}`, character.name);
      }
      for (const npc of roster.npcs) {
        names.set(`npc:${npc.id}`, npc.name);
      }
      gamePhpBattleContext.rememberScale({
        ...scale,
        participants: scale.participants.map((participant) => ({
          ...participant,
          name: names.get(participant.id) || participant.name,
        })),
      });
    } catch {
      // Имена состава недоступны — в шкале остаётся ключ type:id.
    }
  }
}
