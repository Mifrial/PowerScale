import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import * as mock from '@/modules/Roleplay/Game/Mock/mockGames';
import * as mockMemberships from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import * as mockInvitations from '@/modules/Roleplay/Game/Mock/mockGameInvitations';
import * as mockJoinRequests from '@/modules/Roleplay/Game/Mock/mockGameJoinRequests';
import * as mockNpcs from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import * as mockLoot from '@/modules/Roleplay/Game/Mock/mockGameLoot';
import * as mockInitiative from '@/modules/Roleplay/Game/Mock/mockGameInitiative';
import * as mockCombatOverlays from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import * as mockPendingActionEffects from '@/modules/Roleplay/Game/Mock/mockGamePendingActionEffects';
import * as mockQuickRolls from '@/modules/Roleplay/Game/Mock/mockGameQuickRolls';
import * as mockCheckOffers from '@/modules/Roleplay/Game/Mock/mockCheckOffers';
import * as mockChronicle from '@/modules/Roleplay/Game/Mock/mockGameChronicle';
import '@/modules/Roleplay/Game/Mock/mockCharacterSessionOverlay';

export const mockGameApi: IGameApi = {
  getGames: mock.fetchGames,
  getGame: mock.fetchGame,
  createGame: mock.createGame,
  updateGame: mock.updateGame,
  updateGameMember: mock.updateGameMember,
  addGameMember: mock.addGameMember,
  removeGameMember: mock.removeGameMember,
  getGameCharacters: mockMemberships.fetchGameCharacters,
  createGameCharacter: mockMemberships.createGameCharacter,
  submitCharacterToGame: mockMemberships.submitCharacter,
  moderateCharacter: mockMemberships.moderateCharacter,
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
  getNpcs: mockNpcs.fetchNpcs,
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
  getInitiative: mockInitiative.fetchInitiative,
  saveInitiative: mockInitiative.saveInitiative,
  getCombatOverlays: mockCombatOverlays.fetchCombatOverlays,
  getPendingActionEffects: mockPendingActionEffects.fetchPendingActionEffects,
  setCombatActionEffects: mockPendingActionEffects.setPendingActionEffects,
  setCombatResource: mockCombatOverlays.setCombatResource,
  addCombatState: mockCombatOverlays.addCombatState,
  replaceCombatState: mockCombatOverlays.replaceCombatState,
  setCombatStateValue: mockCombatOverlays.setCombatStateValue,
  removeCombatState: mockCombatOverlays.removeCombatState,
  setCombatItemEquipped: mockCombatOverlays.setCombatItemEquipped,
  submitCombatChanges: mockMemberships.submitCombatChanges,
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
