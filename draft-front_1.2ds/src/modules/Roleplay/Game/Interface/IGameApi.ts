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

export interface IGameApi {
  getGames(signal?: AbortSignal): Promise<Game[]>;
  getGame(id: number, signal?: AbortSignal): Promise<GameDetail>;
  createGame(data: CreateGameData, signal?: AbortSignal): Promise<GameDetail>;
  updateGame(id: number, data: CreateGameData, signal?: AbortSignal): Promise<GameDetail>;
  getGameCharacters(gameId: number, signal?: AbortSignal): Promise<GameCharacterMembership[]>;
  createGameCharacter(
    gameId: number,
    data: CreateCharacterData,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership>;
  submitCharacterToGame(gameId: number, characterId: number, signal?: AbortSignal): Promise<GameCharacterMembership>;
  moderateCharacter(
    gameId: number,
    characterId: number,
    action: GameModerationAction,
    choices?: ConflictChoices,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership>;
  updateMembershipVisibility(
    gameId: number,
    characterId: number,
    visibility: SheetVisibility,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership>;
  updateCharacterGrants(
    gameId: number,
    characterId: number,
    data: { osBonus: number; orBonus: number; olBonus: number },
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership>;
  submitCharacterMigration(
    gameId: number,
    characterId: number,
    version: CharacterVersion,
    signal?: AbortSignal,
  ): Promise<GameCharacterMembership>;
  getCharacterGameContexts(characterId: number, signal?: AbortSignal): Promise<CharacterGameContext[]>;
  updateGameMember(
    gameId: number,
    userId: number,
    data: UpdateGameMemberData,
    signal?: AbortSignal,
  ): Promise<GameMember>;
  addGameMember(gameId: number, userId: number, role: GameMemberRole, signal?: AbortSignal): Promise<GameMember>;
  removeGameMember(gameId: number, userId: number, signal?: AbortSignal): Promise<void>;
  getGameInvitations(gameId: number, signal?: AbortSignal): Promise<GameInvitation[]>;
  createInvitation(gameId: number, inviteeId: number, signal?: AbortSignal): Promise<GameInvitation>;
  respondInvitation(invitationId: number, action: 'accept' | 'decline', signal?: AbortSignal): Promise<GameInvitation>;
  getJoinRequests(gameId: number, signal?: AbortSignal): Promise<GameJoinRequest[]>;
  requestJoinGame(gameId: number, signal?: AbortSignal): Promise<GameJoinRequest>;
  respondJoinRequest(
    gameId: number,
    userId: number,
    action: 'accept' | 'decline',
    signal?: AbortSignal,
  ): Promise<GameJoinRequest>;
  getNpcs(gameId: number, signal?: AbortSignal): Promise<GameNpc[]>;
  createNpc(gameId: number, data: CreateNpcData, signal?: AbortSignal): Promise<GameNpc>;
  proposeNpc(gameId: number, data: CreateNpcData, signal?: AbortSignal): Promise<GameNpc>;
  updateNpc(npcId: number, data: UpdateNpcData, signal?: AbortSignal): Promise<GameNpc>;
  moderateNpc(npcId: number, action: GameModerationAction, signal?: AbortSignal): Promise<GameNpc>;
  deleteNpc(npcId: number, signal?: AbortSignal): Promise<void>;
  getLoot(gameId: number, signal?: AbortSignal): Promise<GameLoot[]>;
  addLoot(gameId: number, data: CreateLootData, signal?: AbortSignal): Promise<GameLoot>;
  updateLoot(lootId: number, data: CreateLootData, signal?: AbortSignal): Promise<GameLoot>;
  handoutLoot(lootIds: number[], signal?: AbortSignal): Promise<GameLoot[]>;
  toggleLootInterest(lootId: number, signal?: AbortSignal): Promise<GameLoot>;
  distributeLoot(lootId: number, data: DistributeLootData, signal?: AbortSignal): Promise<GameLoot>;
  deleteLoot(lootId: number, signal?: AbortSignal): Promise<void>;
  getInitiative(gameId: number, signal?: AbortSignal): Promise<GameInitiative>;
  saveInitiative(gameId: number, data: GameInitiative, signal?: AbortSignal): Promise<GameInitiative>;
  getCombatOverlays(gameId: number, signal?: AbortSignal): Promise<GameCombatOverlay[]>;
  setCombatResource(
    gameId: number,
    entityKey: CombatEntityKey,
    ruleId: string,
    current: DimensionalNumberValue,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay>;
  addCombatState(
    gameId: number,
    entityKey: CombatEntityKey,
    state: CharacterStateValue,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay>;
  setCombatStateValue(
    gameId: number,
    entityKey: CombatEntityKey,
    index: number,
    value?: number,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay>;
  removeCombatState(
    gameId: number,
    entityKey: CombatEntityKey,
    index: number,
    signal?: AbortSignal,
  ): Promise<GameCombatOverlay>;
  submitCombatChanges(gameId: number, signal?: AbortSignal): Promise<void>;
  getQuickRolls(gameId: number, signal?: AbortSignal): Promise<Record<CombatEntityKey, string[]>>;
  addQuickRoll(gameId: number, entityKey: CombatEntityKey, ruleId: string, signal?: AbortSignal): Promise<string[]>;
  removeQuickRoll(gameId: number, entityKey: CombatEntityKey, ruleId: string, signal?: AbortSignal): Promise<string[]>;
  getChronicle(gameId: number, signal?: AbortSignal): Promise<Chronicle>;
  getChronicleEntries(gameId: number, signal?: AbortSignal): Promise<ChronicleEntry[]>;
  createChronicleEntry(gameId: number, data: CreateChronicleEntryData, signal?: AbortSignal): Promise<ChronicleEntry>;
  updateChronicleEntry(entryId: number, data: UpdateChronicleEntryData, signal?: AbortSignal): Promise<ChronicleEntry>;
  deleteChronicleEntry(entryId: number, signal?: AbortSignal): Promise<void>;
}
