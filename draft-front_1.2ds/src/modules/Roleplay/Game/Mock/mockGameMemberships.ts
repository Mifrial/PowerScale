import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameCharacterModerationAction } from '@/modules/Roleplay/Game/Enum/GameCharacterModerationAction';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterGameContext } from '@/modules/Roleplay/Game/Dto/CharacterGameContext';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import {
  characters,
  versions,
  createCharacter as createMockCharacter,
  syncCharacterVersion,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import {
  clearCombatOverlay,
  combatKey,
  combatOverlayHasChanges,
  combatOverlaySnapshot,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { membershipMatchesGameRevision } from '@/modules/Roleplay/Game/Utils/membershipRevision';
import { SHEET_VISIBILITY_DEFAULT } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_VISIBILITY_PRESETS';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';
import { gameMembershipReviewService } from '@/modules/Roleplay/Game/Service/Instance/gameMembershipReviewService';
import { mockSendSystemMessage } from '@/modules/Messages/Chat/Mock/mockChat';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { endInitiative } from '@/modules/Roleplay/Game/Mock/mockGameInitiative';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

function snapshotVersion(version: CharacterVersion): CharacterVersion {
  return cloneData(version);
}

export function isSessionActive(gameId: number): boolean {
  return gameDetails.find((detail) => detail.game.id === gameId)?.game.status === 'playing';
}

type StoredMembership = Omit<GameCharacterMembership, 'visibility' | 'overlay' | 'reviewState'>;

export const gameCharacterMemberships: StoredMembership[] = [
  {
    gameId: 1,
    characterId: 3,
    characterName: 'Гаррик из Тени',
    characterOwnerId: 2,
    characterOwnerName: 'Администратор',
    role: 'player',
    membershipStatus: 'active',
    approvedCharacterVersion: snapshotVersion(versions[3]),
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '2026-08-12T18:00:00',
  },
  {
    gameId: 1,
    characterId: 4,
    characterName: 'Морган Мёртвый Глаз',
    characterOwnerId: 3,
    characterOwnerName: 'Пётр Козлов',
    role: 'player',
    membershipStatus: 'submitted',
    approvedCharacterVersion: null,
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '2026-08-10T09:30:00',
  },
  {
    gameId: 2,
    characterId: 1,
    characterName: 'Торвин Стальной Кулак',
    characterOwnerId: 1,
    characterOwnerName: 'Иван Петров',
    role: 'player',
    membershipStatus: 'active',
    approvedCharacterVersion: snapshotVersion(versions[1]),
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '2026-07-25T14:00:00',
  },
];

function gameNameOf(gameId: number): string {
  return gameDetails.find((d) => d.game.id === gameId)?.game.name ?? 'Игра';
}

function actualOf(characterId: number): CharacterVersion | null {
  return versions[characterId] ?? null;
}

function withVisibility(membership: StoredMembership): GameCharacterMembership {
  const character = characters.find((c) => c.id === membership.characterId);
  const visibility = character ? character.visibility : SHEET_VISIBILITY_DEFAULT;
  const overlay = combatOverlaySnapshot(
    getStoredCombatOverlay(membership.gameId, combatKey('character', membership.characterId)),
  );
  const actual = actualOf(membership.characterId);
  const reviewState = gameMembershipReviewService.reviewState({
    returned: membership.returnedAt !== null,
    approved: membership.approvedCharacterVersion,
    actual,
  });

  return {
    ...membership,
    reviewState,
    overlay,
    visibility: visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] })),
  };
}

function gameRulesRevision(gameId: number): number | null {
  return gameDetails.find((detail) => detail.game.id === gameId)?.game.rulesRevision ?? null;
}

function otherLiveMembership(characterId: number, exceptGameId?: number): StoredMembership | undefined {
  return gameCharacterMemberships.find(
    (membership) =>
      membership.characterId === characterId &&
      membership.membershipStatus !== 'left' &&
      membership.gameId !== exceptGameId,
  );
}

function bindCharacterToGame(characterId: number, gameId: number): void {
  const character = characters.find((entry) => entry.id === characterId);
  if (character) {
    character.gameId = gameId;
    character.gameName = gameNameOf(gameId);
  }
}

function unbindCharacter(characterId: number, gameId: number): void {
  const character = characters.find((entry) => entry.id === characterId);
  if (character && character.gameId === gameId) {
    character.gameId = null;
    character.gameName = null;
  }
}

function clearReturn(membership: StoredMembership): void {
  membership.returnedAt = null;
  membership.returnReason = null;
  membership.returnMessageId = null;
}

export function isMembershipEligibleForSession(membership: StoredMembership, gameId: number): boolean {
  const actual = actualOf(membership.characterId);
  const revision = gameRulesRevision(gameId);
  if (revision === null) return false;

  return sessionCharacterService.isEligibleForSession({
    membershipStatus: membership.membershipStatus,
    approved: membership.approvedCharacterVersion,
    actual,
    gameRulesRevision: revision,
    needsFix: false,
  });
}

export async function fetchGameCharacters(gameId: number, _signal?: AbortSignal): Promise<GameCharacterMembership[]> {
  await delay(150);

  return gameCharacterMemberships.filter((membership) => membership.gameId === gameId).map(withVisibility);
}

export async function submitCharacter(
  gameId: number,
  characterId: number,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const character = characters.find((c) => c.id === characterId);
  if (!character) throw new Error('Персонаж не найден');
  if (character.status !== 'ready') throw new Error('В игру можно подать только готового персонажа');
  if (!character.active) throw new Error('Персонаж деактивирован');
  if (!versions[characterId]) throw new Error('Нет версии персонажа');
  const elsewhere = otherLiveMembership(characterId, gameId);
  if (elsewhere) throw new Error('Персонаж уже связан с этой игрой');
  const existing = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (existing && existing.membershipStatus !== 'left') {
    throw new Error('Персонаж уже связан с этой игрой');
  }
  if (existing && existing.membershipStatus === 'left') {
    existing.membershipStatus = 'submitted';
    existing.approvedCharacterVersion = null;
    existing.characterName = character.name;
    existing.characterOwnerId = character.ownerId;
    existing.characterOwnerName = character.ownerName;
    clearReturn(existing);
    existing.updatedAt = new Date().toISOString();

    return withVisibility(existing);
  }
  const membership: StoredMembership = {
    gameId,
    characterId,
    characterName: character.name,
    characterOwnerId: character.ownerId,
    characterOwnerName: character.ownerName,
    role: 'player',
    membershipStatus: 'submitted',
    approvedCharacterVersion: null,
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: new Date().toISOString(),
  };
  gameCharacterMemberships.push(membership);

  return withVisibility(membership);
}

export async function createGameCharacter(
  gameId: number,
  data: CreateCharacterData,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const detail = await createMockCharacter(data);
  const character = detail.character;
  const membership: StoredMembership = {
    gameId,
    characterId: character.id,
    characterName: character.name,
    characterOwnerId: character.ownerId,
    characterOwnerName: character.ownerName,
    role: 'player',
    membershipStatus: 'submitted',
    approvedCharacterVersion: null,
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: new Date().toISOString(),
  };
  gameCharacterMemberships.push(membership);

  return withVisibility(membership);
}

export function syncCharacterVersionToMemberships(_characterId: number): void {
  // actual живёт в versions[id]; reviewState считается при отдаче membership.
}

export async function moderateCharacter(
  gameId: number,
  characterId: number,
  action: GameCharacterModerationAction,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  const entityKey = combatKey('character', characterId);
  const overlay = getStoredCombatOverlay(gameId, entityKey);
  const liveOverlay = isSessionActive(gameId) && combatOverlayHasChanges(membership.approvedCharacterVersion, overlay);
  if (liveOverlay) {
    throw new Error('Сессия активна: изменения персонажа уйдут на модерацию после остановки сессии');
  }
  const actual = actualOf(characterId);
  if (action === 'approve') {
    if (actual === null) throw new Error('Нет версии персонажа');
    const revision = gameRulesRevision(gameId);
    if (revision === null || !membershipMatchesGameRevision(actual, revision)) {
      throw new Error('Ревизия персонажа не совпадает с ревизией игры');
    }
    if (membership.membershipStatus === 'submitted') {
      membership.membershipStatus = 'active';
    } else if (membership.membershipStatus !== 'active') {
      throw new Error('Нельзя одобрить это членство');
    }
    membership.approvedCharacterVersion = snapshotVersion(actual);
    clearReturn(membership);
    clearCombatOverlay(gameId, entityKey);
    bindCharacterToGame(characterId, gameId);
  } else if (action === 'returnForRework') {
    membership.returnedAt = new Date().toISOString();
    membership.returnReason = 'Требуется доработка';
    const character = characters.find((entry) => entry.id === characterId);
    if (character?.discussionChatId != null) {
      const message = await mockSendSystemMessage(
        character.discussionChatId,
        'Вернуть на доработку: требуется доработка листа.',
      );
      membership.returnMessageId = message.id;
    }
  } else {
    if (membership.membershipStatus !== 'submitted') {
      throw new Error('Отклонить можно только заявку');
    }
    const index = gameCharacterMemberships.indexOf(membership);
    if (index >= 0) gameCharacterMemberships.splice(index, 1);
    membership.updatedAt = new Date().toISOString();

    return withVisibility(membership);
  }
  membership.updatedAt = new Date().toISOString();

  return withVisibility(membership);
}

export async function leaveGame(
  gameId: number,
  characterId: number,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  if (isSessionActive(gameId)) throw new Error('Нельзя покинуть игру во время сессии');
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  if (membership.membershipStatus === 'left') return withVisibility(membership);
  clearCombatOverlay(gameId, combatKey('character', characterId));
  membership.membershipStatus = 'left';
  membership.updatedAt = new Date().toISOString();
  unbindCharacter(characterId, gameId);

  return withVisibility(membership);
}

export async function submitCombatChanges(gameId: number, _signal?: AbortSignal): Promise<void> {
  await delay(150);
  if (isSessionActive(gameId)) return;
  endInitiative(gameId);
  const participants = gameCharacterMemberships.filter(
    (membership) => membership.gameId === gameId && membership.membershipStatus === 'active',
  );
  const tokens = participants.map((membership) => ({
    characterId: membership.characterId,
    token: JSON.stringify(actualOf(membership.characterId)),
  }));
  const planned: { membership: StoredMembership; next: CharacterVersion }[] = [];
  for (const membership of participants) {
    const overlay = getStoredCombatOverlay(gameId, combatKey('character', membership.characterId));
    if (!combatOverlayHasChanges(membership.approvedCharacterVersion, overlay)) continue;
    const resolved = sessionCharacterService.resolve(membership.approvedCharacterVersion, overlay);
    if (resolved === null) throw new Error('Не удалось собрать лист после сессии');
    planned.push({ membership, next: resolved });
  }
  for (const { characterId, token } of tokens) {
    if (JSON.stringify(actualOf(characterId)) !== token) {
      throw new Error('Персонаж изменился, повторите завершение сессии');
    }
  }
  try {
    for (const { membership, next } of planned) {
      versions[membership.characterId] = snapshotVersion(next);
      syncCharacterVersion(membership.characterId);
      clearCombatOverlay(gameId, combatKey('character', membership.characterId));
      membership.updatedAt = new Date().toISOString();
    }
  } catch (error) {
    for (const { characterId, token } of tokens) {
      if (token === 'null') delete versions[characterId];
      else versions[characterId] = JSON.parse(token) as CharacterVersion;
      if (versions[characterId]) syncCharacterVersion(characterId);
    }
    throw error;
  }
}

export async function updateMembershipVisibility(
  gameId: number,
  characterId: number,
  visibility: SheetVisibility,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  const character = characters.find((c) => c.id === characterId);
  if (!character) throw new Error('Персонаж не найден');
  character.visibility = visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] }));

  return withVisibility(membership);
}

export async function updateCharacterGrants(
  gameId: number,
  characterId: number,
  data: { osBonus: number; orBonus: number; olBonus: number },
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  membership.osBonus = data.osBonus;
  membership.orBonus = data.orBonus;
  membership.olBonus = data.olBonus;
  membership.updatedAt = new Date().toISOString();

  return withVisibility(membership);
}

export async function submitCharacterMigration(
  gameId: number,
  characterId: number,
  version: CharacterVersion,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  if (isSessionActive(gameId)) throw new Error('Нельзя менять лист во время сессии');
  versions[characterId] = snapshotVersion(version);
  syncCharacterVersion(characterId);
  membership.updatedAt = new Date().toISOString();

  return withVisibility(membership);
}

export async function fetchCharacterGameContexts(
  characterId: number,
  _signal?: AbortSignal,
): Promise<CharacterGameContext[]> {
  await delay(150);

  return gameCharacterMemberships
    .filter((membership) => membership.characterId === characterId && membership.membershipStatus !== 'left')
    .map((membership) => {
      const detail = gameDetails.find((entry) => entry.game.id === membership.gameId);

      return {
        gameId: membership.gameId,
        gameName: detail?.game.name ?? 'Игра',
        members: detail?.members ?? [],
      };
    });
}
