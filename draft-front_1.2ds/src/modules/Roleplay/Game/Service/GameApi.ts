import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import type { UpdateGameMemberData } from '@/modules/Roleplay/Game/Dto/UpdateGameMemberData';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameInvitation } from '@/modules/Roleplay/Game/Dto/GameInvitation';
import type { GameModerationAction } from '@/modules/Roleplay/Game/Enum/GameModerationAction';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CreateNpcData } from '@/modules/Roleplay/Game/Dto/CreateNpcData';
import type { UpdateNpcData } from '@/modules/Roleplay/Game/Dto/UpdateNpcData';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterGameContext } from '@/modules/Roleplay/Game/Dto/CharacterGameContext';
import type { GameLoot } from '@/modules/Roleplay/Game/Dto/GameLoot';
import type { CreateLootData } from '@/modules/Roleplay/Game/Dto/CreateLootData';
import type { DistributeLootData } from '@/modules/Roleplay/Game/Dto/DistributeLootData';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Chronicle } from '@/modules/Roleplay/Game/Dto/Chronicle';
import type { ChronicleEntry } from '@/modules/Roleplay/Game/Dto/ChronicleEntry';
import type { CreateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/CreateChronicleEntryData';
import type { UpdateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/UpdateChronicleEntryData';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ConflictChoices } from '@/modules/Roleplay/Game/Utils/reconcileVersion';

export class GameApi implements IGameApi {
  constructor(private readonly engine: Engine) {}

  async getGames(signal?: AbortSignal): Promise<Game[]> {
    const res = await this.engine.runAction<Game[]>('game.getList', undefined, signal);

    return res.data ?? [];
  }

  async getGame(id: number, signal?: AbortSignal): Promise<GameDetail> {
    const res = await this.engine.runAction<GameDetail>('game.get', { id }, signal);
    if (!res.data) throw new Error('Game not found');

    return res.data;
  }

  async createGame(data: CreateGameData, signal?: AbortSignal): Promise<GameDetail> {
    const res = await this.engine.runAction<GameDetail>('game.create', data, signal);
    if (!res.data) throw new Error('Game create failed');

    return res.data;
  }

  async updateGame(id: number, data: CreateGameData, signal?: AbortSignal): Promise<GameDetail> {
    const res = await this.engine.runAction<GameDetail>('game.update', { id, ...data }, signal);
    if (!res.data) throw new Error('Game update failed');

    return res.data;
  }

  async getGameCharacters(gameId: number, signal?: AbortSignal): Promise<GameCharacterMembership[]> {
    const res = await this.engine.runAction<GameCharacterMembership[]>('game.getCharacters', { gameId }, signal);

    return res.data ?? [];
  }

  async createGameCharacter(
    gameId: number,
    data: CreateCharacterData,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    const res = await this.engine.runAction<GameCharacterMembership>(
      'game.createCharacter',
      { gameId, ...data },
      signal,
    );
    if (!res.data) throw new Error('Create game character failed');

    return res.data;
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
    gameId: number,
    characterId: number,
    action: GameModerationAction,
    choices?: ConflictChoices,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    const res = await this.engine.runAction<GameCharacterMembership>(
      'game.moderateCharacter',
      { gameId, characterId, action, choices },
      signal,
    );
    if (!res.data) throw new Error('Moderate character failed');

    return res.data;
  }

  async updateMembershipVisibility(
    gameId: number,
    characterId: number,
    visibility: SheetVisibility,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    const res = await this.engine.runAction<GameCharacterMembership>(
      'game.updateMembershipVisibility',
      { gameId, characterId, visibility },
      signal,
    );
    if (!res.data) throw new Error('Update membership visibility failed');

    return res.data;
  }

  async updateCharacterGrants(
    gameId: number,
    characterId: number,
    data: { osBonus: number; orBonus: number; olBonus: number },
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    const res = await this.engine.runAction<GameCharacterMembership>(
      'game.updateCharacterGrants',
      { gameId, characterId, ...data },
      signal,
    );
    if (!res.data) throw new Error('Update character grants failed');

    return res.data;
  }

  async submitCharacterMigration(
    gameId: number,
    characterId: number,
    version: CharacterVersion,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership> {
    const res = await this.engine.runAction<GameCharacterMembership>(
      'game.submitCharacterMigration',
      { gameId, characterId, version },
      signal,
    );
    if (!res.data) throw new Error('Submit character migration failed');

    return res.data;
  }

  async getCharacterGameContexts(characterId: number, signal?: AbortSignal): Promise<CharacterGameContext[]> {
    const res = await this.engine.runAction<CharacterGameContext[]>(
      'game.getCharacterGameContexts',
      { characterId },
      signal,
    );

    return res.data ?? [];
  }

  async updateGameMember(
    gameId: number,
    userId: number,
    data: UpdateGameMemberData,
    signal?: AbortSignal,
  ): Promise<GameMember> {
    const res = await this.engine.runAction<GameMember>('game.updateMember', { gameId, userId, ...data }, signal);
    if (!res.data) throw new Error('Update member failed');

    return res.data;
  }

  async addGameMember(gameId: number, userId: number, role: GameMemberRole, signal?: AbortSignal): Promise<GameMember> {
    const res = await this.engine.runAction<GameMember>('game.addMember', { gameId, userId, role }, signal);
    if (!res.data) throw new Error('Add member failed');

    return res.data;
  }

  async removeGameMember(gameId: number, userId: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction<void>('game.removeMember', { gameId, userId }, signal);
  }

  async getGameInvitations(gameId: number, signal?: AbortSignal): Promise<GameInvitation[]> {
    const res = await this.engine.runAction<GameInvitation[]>('game.getInvitations', { gameId }, signal);

    return res.data ?? [];
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

  async getNpcs(gameId: number, signal?: AbortSignal): Promise<GameNpc[]> {
    const res = await this.engine.runAction<GameNpc[]>('game.getNpcs', { gameId }, signal);

    return res.data ?? [];
  }

  async createNpc(gameId: number, data: CreateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>('game.createNpc', { gameId, ...data }, signal);
    if (!res.data) throw new Error('Create npc failed');

    return res.data;
  }

  async proposeNpc(gameId: number, data: CreateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>('game.proposeNpc', { gameId, ...data }, signal);
    if (!res.data) throw new Error('Propose npc failed');

    return res.data;
  }

  async updateNpc(npcId: number, data: UpdateNpcData, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>('game.updateNpc', { npcId, ...data }, signal);
    if (!res.data) throw new Error('Update npc failed');

    return res.data;
  }

  async moderateNpc(npcId: number, action: GameModerationAction, signal?: AbortSignal): Promise<GameNpc> {
    const res = await this.engine.runAction<GameNpc>('game.moderateNpc', { npcId, action }, signal);
    if (!res.data) throw new Error('Moderate npc failed');

    return res.data;
  }

  async deleteNpc(npcId: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction<void>('game.deleteNpc', { npcId }, signal);
  }

  async getLoot(gameId: number, signal?: AbortSignal): Promise<GameLoot[]> {
    const res = await this.engine.runAction<GameLoot[]>('game.getLoot', { gameId }, signal);

    return res.data ?? [];
  }

  async addLoot(gameId: number, data: CreateLootData, signal?: AbortSignal): Promise<GameLoot> {
    const res = await this.engine.runAction<GameLoot>('game.addLoot', { gameId, ...data }, signal);
    if (!res.data) throw new Error('Add loot failed');

    return res.data;
  }

  async updateLoot(lootId: number, data: CreateLootData, signal?: AbortSignal): Promise<GameLoot> {
    const res = await this.engine.runAction<GameLoot>('game.updateLoot', { lootId, ...data }, signal);
    if (!res.data) throw new Error('Update loot failed');

    return res.data;
  }

  async handoutLoot(lootIds: number[], signal?: AbortSignal): Promise<GameLoot[]> {
    const res = await this.engine.runAction<GameLoot[]>('game.handoutLoot', { lootIds }, signal);

    return res.data ?? [];
  }

  async toggleLootInterest(lootId: number, signal?: AbortSignal): Promise<GameLoot> {
    const res = await this.engine.runAction<GameLoot>('game.toggleLootInterest', { lootId }, signal);
    if (!res.data) throw new Error('Toggle loot interest failed');

    return res.data;
  }

  async distributeLoot(lootId: number, data: DistributeLootData, signal?: AbortSignal): Promise<GameLoot> {
    const res = await this.engine.runAction<GameLoot>('game.distributeLoot', { lootId, ...data }, signal);
    if (!res.data) throw new Error('Distribute loot failed');

    return res.data;
  }

  async deleteLoot(lootId: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction<void>('game.deleteLoot', { lootId }, signal);
  }

  async getInitiative(gameId: number, signal?: AbortSignal): Promise<GameInitiative> {
    const res = await this.engine.runAction<GameInitiative>('game.getInitiative', { gameId }, signal);
    if (!res.data) throw new Error('Get initiative failed');

    return res.data;
  }

  async saveInitiative(gameId: number, data: GameInitiative, signal?: AbortSignal): Promise<GameInitiative> {
    const res = await this.engine.runAction<GameInitiative>('game.saveInitiative', { ...data, gameId }, signal);
    if (!res.data) throw new Error('Save initiative failed');

    return res.data;
  }

  async getCombatOverlays(gameId: number, signal?: AbortSignal): Promise<GameCombatOverlay[]> {
    const res = await this.engine.runAction<GameCombatOverlay[]>('game.getCombatOverlays', { gameId }, signal);

    return res.data ?? [];
  }

  async setCombatResource(
    gameId: number,
    entityKey: CombatEntityKey,
    ruleId: string,
    current: DimensionalNumberValue,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay> {
    const res = await this.engine.runAction<GameCombatOverlay>(
      'game.setCombatResource',
      { gameId, entityKey, ruleId, current },
      signal,
    );
    if (!res.data) throw new Error('Set combat resource failed');

    return res.data;
  }

  async addCombatState(
    gameId: number,
    entityKey: CombatEntityKey,
    state: CharacterStateValue,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay> {
    const res = await this.engine.runAction<GameCombatOverlay>(
      'game.addCombatState',
      { gameId, entityKey, state },
      signal,
    );
    if (!res.data) throw new Error('Add combat state failed');

    return res.data;
  }

  async setCombatStateValue(
    gameId: number,
    entityKey: CombatEntityKey,
    index: number,
    value?: number,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay> {
    const res = await this.engine.runAction<GameCombatOverlay>(
      'game.setCombatStateValue',
      { gameId, entityKey, index, value },
      signal,
    );
    if (!res.data) throw new Error('Set combat state value failed');

    return res.data;
  }

  async removeCombatState(
    gameId: number,
    entityKey: CombatEntityKey,
    index: number,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay> {
    const res = await this.engine.runAction<GameCombatOverlay>(
      'game.removeCombatState',
      { gameId, entityKey, index },
      signal,
    );
    if (!res.data) throw new Error('Remove combat state failed');

    return res.data;
  }

  async submitCombatChanges(gameId: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction<void>('game.submitCombatChanges', { gameId }, signal);
  }

  async getQuickRolls(gameId: number, signal?: AbortSignal): Promise<Record<CombatEntityKey, string[]>> {
    const res = await this.engine.runAction<Record<CombatEntityKey, string[]>>(
      'game.getQuickRolls',
      { gameId },
      signal,
    );

    return res.data ?? {};
  }

  async addQuickRoll(
    gameId: number,
    entityKey: CombatEntityKey,
    ruleId: string,
    signal?: AbortSignal,
  ): Promise<string[]> {
    const res = await this.engine.runAction<string[]>('game.addQuickRoll', { gameId, entityKey, ruleId }, signal);
    if (!res.data) throw new Error('Add quick roll failed');

    return res.data;
  }

  async removeQuickRoll(
    gameId: number,
    entityKey: CombatEntityKey,
    ruleId: string,
    signal?: AbortSignal,
  ): Promise<string[]> {
    const res = await this.engine.runAction<string[]>('game.removeQuickRoll', { gameId, entityKey, ruleId }, signal);
    if (!res.data) throw new Error('Remove quick roll failed');

    return res.data;
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
}
