import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import * as mock from '@/modules/Roleplay/Game/Mock/mockGames';
import * as mockMemberships from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import * as mockInvitations from '@/modules/Roleplay/Game/Mock/mockGameInvitations';
import * as mockJoinRequests from '@/modules/Roleplay/Game/Mock/mockGameJoinRequests';
import * as mockNpcs from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import * as mockLoot from '@/modules/Roleplay/Game/Mock/mockGameLoot';
import * as mockCombatOverlays from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import * as mockPendingActionEffects from '@/modules/Roleplay/Game/Mock/mockGamePendingActionEffects';
import * as mockProcessSessions from '@/modules/Roleplay/Game/Mock/mockGameProcessSessions';
import * as mockCommittedActions from '@/modules/Roleplay/Game/Mock/mockGameCommittedActions';
import * as mockActiveSpells from '@/modules/Roleplay/Game/Mock/mockGameActiveSpells';
import * as mockQuickRolls from '@/modules/Roleplay/Game/Mock/mockGameQuickRolls';
import * as mockCheckOffers from '@/modules/Roleplay/Game/Mock/mockCheckOffers';
import * as mockChronicle from '@/modules/Roleplay/Game/Mock/mockGameChronicle';
import '@/modules/Roleplay/Game/Mock/mockCharacterSessionRuntimePort';
import * as mockMovementState from '@/modules/Roleplay/Game/Mock/mockGameMovementState';
import * as mockGameState from '@/modules/Roleplay/Game/Mock/mockGameState';
import { mockGameCombatCommandService } from '@/modules/Roleplay/Game/Service/Instance/mockGameCombatCommandService';
import { MockGameRuntimeProjectionSource } from '@/modules/Roleplay/Game/Mock/MockGameRuntimeProjectionSource';
import { MockGameParticipantCandidateSource } from '@/modules/Roleplay/Game/Mock/MockGameParticipantCandidateSource';

const runtimeProjectionSource = new MockGameRuntimeProjectionSource();
const participantCandidateSource = new MockGameParticipantCandidateSource();

mockGameState.configureMockGameState({
  getGame: (gameId) => mock.gameDetails.find((detail) => detail.game.id === gameId)?.game ?? null,
  validateParticipants: async (gameId, participantEntityKeys) => {
    const [memberships, npcs] = await Promise.all([
      mockMemberships.fetchGameCharacters(gameId),
      mockNpcs.fetchNpcs(gameId),
    ]);

    return participantEntityKeys.every((entityKey) => {
      const [kind, rawId] = entityKey.split(':');
      const entityId = Number(rawId);
      if (kind === 'character') {
        const membership = memberships.find((item) => item.characterId === entityId);

        return membership !== undefined && mockMemberships.isMembershipEligibleForSession(membership, gameId);
      }

      if (kind === 'npc') {
        return npcs.some((npc) => npc.id === entityId && npc.status === 'active');
      }

      return false;
    });
  },
  resolveParticipants: async (gameId) => {
    const [memberships, npcs] = await Promise.all([
      mockMemberships.fetchGameCharacters(gameId),
      mockNpcs.fetchNpcs(gameId),
    ]);

    return [
      ...memberships
        .filter((membership) => mockMemberships.isMembershipEligibleForSession(membership, gameId))
        .map((membership) => `character:${membership.characterId}` as const),
      ...npcs.filter((npc) => npc.status === 'active').map((npc) => `npc:${npc.id}` as const),
    ];
  },
});

export const mockGameApi: IGameApi = {
  getGames: mock.fetchGames,
  getGame: mock.fetchGame,
  createGame: mock.createGame,
  updateGame: mock.updateGame,
  startGameSession: mockGameState.startGameSession,
  startGameBattle: mockGameState.startGameBattle,
  endGameBattle: mockGameState.endGameBattle,
  stopGameStateSession: mockGameState.stopGameStateSession,
  stopGameSession: mock.stopGameSession,
  getGameStateSnapshot: mockGameState.getGameStateSnapshot,
  submitCombatCommand: (command) => mockGameCombatCommandService.submit(command),
  mutateRuntimeEntity: mockCombatOverlays.mutateRuntimeEntity,
  getRuntimeEntity: runtimeProjectionSource.getRuntimeEntity.bind(runtimeProjectionSource),
  getRuntimeEntities: (gameId, request, signal) => runtimeProjectionSource.getRuntimeEntities(gameId, request, signal),
  getCharacterModerationProjections: mockMemberships.fetchCharacterModerationProjections,
  updateGameMember: mock.updateGameMember,
  addGameMember: mock.addGameMember,
  removeGameMember: mock.removeGameMember,
  getGameCharacters: mockMemberships.fetchGameCharacters,
  createGameCharacter: mockMemberships.createGameCharacter,
  submitCharacterToGame: mockMemberships.submitCharacter,
  moderateCharacter: mockMemberships.moderateCharacter,
  moderateCharacterCommand: mockMemberships.moderateCharacterCommand,
  leaveGame: mockMemberships.leaveGame,
  updateMembershipVisibility: mockMemberships.updateMembershipVisibility,
  updateCharacterGrants: mockMemberships.updateCharacterGrants,
  submitCharacterMigration: mockMemberships.submitCharacterMigration,
  getCharacterGameContexts: mockMemberships.fetchCharacterGameContexts,
  getGameInvitations: mockInvitations.fetchGameInvitations,
  createInvitation: mockInvitations.createInvitation,
  respondInvitation: mockInvitations.respondInvitation,
  getJoinRequests: mockJoinRequests.fetchJoinRequests,
  requestJoinGame: mockJoinRequests.requestJoinGame,
  respondJoinRequest: mockJoinRequests.respondJoinRequest,
  getNpcSummaries: mockNpcs.fetchNpcSummaries,
  getParticipantCandidates: participantCandidateSource.search.bind(participantCandidateSource),
  getNpc: mockNpcs.fetchNpc,
  createNpc: mockNpcs.createNpc,
  proposeNpc: mockNpcs.proposeNpc,
  updateNpc: mockNpcs.updateNpc,
  moderateNpc: mockNpcs.moderateNpc,
  deleteNpc: mockNpcs.deleteNpc,
  getLoot: mockLoot.fetchLoot,
  addLoot: mockLoot.addLoot,
  updateLoot: mockLoot.updateLoot,
  handoutLoot: mockLoot.handoutLoot,
  toggleLootInterest: mockLoot.toggleLootInterest,
  distributeLoot: mockLoot.distributeLoot,
  deleteLoot: mockLoot.deleteLoot,
  getInitiative: mockGameState.getGameInitiative,
  saveInitiative: mockGameState.saveGameInitiative,
  getCombatOverlays: mockCombatOverlays.fetchCombatOverlays,
  getPendingActionEffects: mockPendingActionEffects.fetchPendingActionEffects,
  setCombatActionEffects: mockPendingActionEffects.setPendingActionEffects,
  getProcessSessions: mockProcessSessions.fetchProcessSessions,
  setProcessSession: mockProcessSessions.setProcessSession,
  getCommittedActionSessions: mockCommittedActions.fetchCommittedActionSessions,
  setCommittedActionSession: mockCommittedActions.setCommittedActionSession,
  getActiveSpells: mockActiveSpells.fetchActiveSpells,
  upsertActiveSpell: mockActiveSpells.upsertActiveSpell,
  dropActiveSpell: mockActiveSpells.dropActiveSpell,
  getCurrentSpeed: mockMovementState.getCurrentSpeed,
  setCurrentSpeed: mockMovementState.setCurrentSpeed,
  setCombatResource: mockCombatOverlays.setCombatResource,
  setCombatConcentrationUsedInCycle: mockCombatOverlays.setCombatConcentrationUsedInCycle,
  setCombatWoundBandagedOnce: mockCombatOverlays.setCombatWoundBandagedOnce,
  addCombatState: mockCombatOverlays.addCombatState,
  replaceCombatState: mockCombatOverlays.replaceCombatState,
  setCombatStateValue: mockCombatOverlays.setCombatStateValue,
  removeCombatState: mockCombatOverlays.removeCombatState,
  setCombatItemEquipped: mockCombatOverlays.setCombatItemEquipped,
  setCombatItemOccupyHands: mockCombatOverlays.setCombatItemOccupyHands,
  getQuickRolls: mockQuickRolls.fetchQuickRolls,
  addQuickRoll: mockQuickRolls.addQuickRoll,
  removeQuickRoll: mockQuickRolls.removeQuickRoll,
  getChronicle: mockChronicle.fetchChronicle,
  getChronicleEntries: mockChronicle.fetchChronicleEntries,
  createChronicleEntry: mockChronicle.createChronicleEntry,
  updateChronicleEntry: mockChronicle.updateChronicleEntry,
  deleteChronicleEntry: mockChronicle.deleteChronicleEntry,
  updatePersonalNotes: mock.updatePersonalNotes,
  createCheckOffer: mockCheckOffers.createCheckOffer,
  reviseCheckOffer: mockCheckOffers.reviseCheckOffer,
  acceptCheckOffer: mockCheckOffers.acceptCheckOffer,
  cancelCheckOffer: mockCheckOffers.cancelCheckOffer,
  getPendingCheckOffers: mockCheckOffers.getPendingCheckOffers,
  getCheckOffersForEntity: mockCheckOffers.getCheckOffersForEntity,
  getCheckOffersForGame: mockCheckOffers.getCheckOffersForGame,
};
