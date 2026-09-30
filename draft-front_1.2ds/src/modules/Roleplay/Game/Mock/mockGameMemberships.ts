import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameCharacterModerationAction } from '@/modules/Roleplay/Game/Enum/GameCharacterModerationAction';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterGameContext } from '@/modules/Roleplay/Game/Dto/CharacterGameContext';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterModerationProjection } from '@/modules/Roleplay/Game/Dto/CharacterModerationProjection';
import type { GameCharacterModerationCommand } from '@/modules/Roleplay/Game/Dto/GameCharacterModerationCommand';
import type { GameCharacterModerationResult } from '@/modules/Roleplay/Game/Dto/GameCharacterModerationResult';
import {
  characters,
  getCharacterActualVersion,
  getStoredCharacterVersion,
  versions,
  createCharacter as createMockCharacter,
  syncCharacterVersion,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import {
  clearCombatOverlay,
  combatKey,
  combatOverlaySnapshot,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { membershipMatchesGameRevision } from '@/modules/Roleplay/Game/Utils/membershipRevision';
import {
  characterDiffService,
  characterVersionIntegrityService,
  SHEET_VISIBILITY_DEFAULT,
} from '@/modules/Roleplay/Character/init';
import { gameMembershipEligibilityService } from '@/modules/Roleplay/Game/Service/Instance/gameMembershipEligibilityService';
import { gameMembershipReviewService } from '@/modules/Roleplay/Game/Service/Instance/gameMembershipReviewService';
import { mockSendSystemMessage } from '@/modules/Messages/Chat/Mock/mockChat';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { fetchRevision } from '@/modules/Roleplay/RuleSpace/Mock/mockSpaces';
import { cancelGameParticipantProcesses, isGameSessionParticipant } from '@/modules/Roleplay/Game/Mock/mockGameState';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

interface StoredModerationCommand {
  fingerprint: string;
  result: GameCharacterModerationResult;
}

const moderationCommands = new Map<string, StoredModerationCommand>();
const moderationQueues = new Map<string, Promise<unknown>>();

async function runSerializedModeration<T>(key: string, mutation: () => Promise<T>): Promise<T> {
  const previous = moderationQueues.get(key) ?? Promise.resolve();
  const current = previous.catch(() => undefined).then(mutation);
  moderationQueues.set(key, current);

  try {
    return await current;
  } finally {
    if (moderationQueues.get(key) === current) moderationQueues.delete(key);
  }
}

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
    membershipRevision: 1,
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
    membershipRevision: 1,
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
    membershipRevision: 1,
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
  const game = gameDetails.find((detail) => detail.game.id === gameId)?.game;
  if (revision === null || game === undefined) return false;

  return gameMembershipEligibilityService.canStartSession({
    membershipStatus: membership.membershipStatus,
    returned: membership.returnedAt !== null,
    approved: membership.approvedCharacterVersion,
    actual,
    gameSpaceCode: game.spaceCode,
    gameRulesRevision: revision,
    needsFix: false,
  });
}

export function isActiveSessionParticipant(gameId: number, characterId: number): boolean {
  const membership = gameCharacterMemberships.find(
    (entry) => entry.gameId === gameId && entry.characterId === characterId,
  );

  return (
    membership?.membershipStatus === 'active' &&
    membership.returnedAt === null &&
    isGameSessionParticipant(gameId, `character:${characterId}`)
  );
}

export function captureMembershipRuntimeToken(
  gameId: number,
  characterId: number,
): { membershipRevision: number; updatedAt: string } {
  const membership = gameCharacterMemberships.find(
    (entry) => entry.gameId === gameId && entry.characterId === characterId,
  );
  if (!membership) throw new Error('Членство не найдено');

  return { membershipRevision: membership.membershipRevision, updatedAt: membership.updatedAt };
}

export function markCharacterRuntimeMutation(gameId: number, characterId: number): void {
  const membership = gameCharacterMemberships.find(
    (entry) => entry.gameId === gameId && entry.characterId === characterId,
  );
  if (!membership) throw new Error('Членство не найдено');

  membership.membershipRevision += 1;
  membership.updatedAt = new Date().toISOString();
}

export function restoreMembershipRuntimeToken(
  gameId: number,
  characterId: number,
  token: { membershipRevision: number; updatedAt: string },
): void {
  const membership = gameCharacterMemberships.find(
    (entry) => entry.gameId === gameId && entry.characterId === characterId,
  );
  if (!membership) throw new Error('Членство не найдено');

  membership.membershipRevision = token.membershipRevision;
  membership.updatedAt = token.updatedAt;
}

export async function fetchGameCharacters(
  gameId: number,
  _signal?: AbortSignal,
  characterIds?: readonly number[],
): Promise<GameCharacterMembership[]> {
  await delay(150);
  const requestedIds = characterIds ? new Set(characterIds) : null;

  return gameCharacterMemberships
    .filter(
      (membership) =>
        membership.gameId === gameId && (requestedIds === null || requestedIds.has(membership.characterId)),
    )
    .map(withVisibility);
}

/**
 * Возвращает ограниченную страницу кандидатов-персонажей после применения eligibility-фильтра.
 */
export async function fetchGameCharacterCandidatePage(
  gameId: number,
  query: string | undefined,
  offset: number,
  limit: number,
  includeMembership: (membership: StoredMembership) => boolean,
): Promise<{ items: StoredMembership[]; nextOffset: number | null }> {
  await delay(150);
  const normalizedQuery = query?.trim().toLocaleLowerCase() ?? '';
  const filtered = gameCharacterMemberships
    .filter((membership) => {
      if (membership.gameId !== gameId || !includeMembership(membership)) return false;

      return normalizedQuery.length === 0 || membership.characterName.toLocaleLowerCase().includes(normalizedQuery);
    })
    .sort((left, right) => {
      const nameOrder = left.characterName.localeCompare(right.characterName, 'ru');

      return nameOrder !== 0 ? nameOrder : left.characterId - right.characterId;
    });
  const pageOffset = Number.isInteger(offset) && offset >= 0 ? offset : 0;
  const pageLimit = Math.min(Math.max(limit, 1), 50);
  const items = filtered.slice(pageOffset, pageOffset + pageLimit);
  const nextOffset = pageOffset + items.length < filtered.length ? pageOffset + items.length : null;

  return { items, nextOffset };
}

export async function fetchCharacterModerationProjections(
  gameId: number,
  characterIds: number[],
  _signal?: AbortSignal,
): Promise<CharacterModerationProjection[]> {
  await delay(150);
  const memberships = gameCharacterMemberships.filter(
    (membership) => membership.gameId === gameId && characterIds.includes(membership.characterId),
  );

  return memberships.flatMap((membership) => {
    const actual = getStoredCharacterVersion(membership.characterId);
    const approved = membership.approvedCharacterVersion ? cloneData(membership.approvedCharacterVersion) : null;
    const reviewState = gameMembershipReviewService.reviewState({
      returned: membership.returnedAt !== null,
      approved,
      actual,
    });

    return [
      {
        characterId: membership.characterId,
        approvedCharacterVersion: approved,
        actualCharacterVersion: cloneData(actual),
        actualVersion: getCharacterActualVersion(membership.characterId),
        diff: characterDiffService.getCharacterDiff(approved, actual),
        reviewState,
      },
    ];
  });
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
    membershipRevision: 1,
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
    membershipRevision: 1,
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

export async function moderateCharacterCommand(
  command: GameCharacterModerationCommand,
  _signal?: AbortSignal,
): Promise<GameCharacterModerationResult> {
  return runSerializedModeration(`${command.gameId}:${command.characterId}`, async () => {
    await delay(200);
    const previous = moderationCommands.get(command.commandId);
    const fingerprint = JSON.stringify({
      gameId: command.gameId,
      characterId: command.characterId,
      action: command.action,
      expectedActualVersion: command.expectedActualVersion,
      expectedMembershipRevision: command.expectedMembershipRevision,
    });
    if (previous) {
      if (previous.fingerprint !== fingerprint) {
        const membership = gameCharacterMemberships.find(
          (entry) => entry.gameId === command.gameId && entry.characterId === command.characterId,
        );

        return {
          kind: 'conflict',
          commandId: command.commandId,
          status: 'rejected',
          conflict: {
            code: 'command_fingerprint_conflict',
            currentActualVersion: membership ? getCharacterActualVersion(membership.characterId) : 0,
            currentMembershipRevision: membership?.membershipRevision ?? 0,
            retriable: false,
          },
        };
      }

      return cloneData(previous.result);
    }

    const membership = gameCharacterMemberships.find(
      (entry) => entry.gameId === command.gameId && entry.characterId === command.characterId,
    );
    if (!membership) throw new Error('Членство не найдено');
    const actualVersion = getCharacterActualVersion(command.characterId);
    if (actualVersion !== command.expectedActualVersion) {
      return {
        kind: 'conflict',
        commandId: command.commandId,
        status: 'rejected',
        conflict: {
          code: 'stale_actual_version',
          currentActualVersion: actualVersion,
          currentMembershipRevision: membership.membershipRevision,
          retriable: true,
        },
      };
    }
    if (membership.membershipRevision !== command.expectedMembershipRevision) {
      return {
        kind: 'conflict',
        commandId: command.commandId,
        status: 'rejected',
        conflict: {
          code: 'stale_membership_revision',
          currentActualVersion: actualVersion,
          currentMembershipRevision: membership.membershipRevision,
          retriable: true,
        },
      };
    }

    const entityKey = combatKey('character', command.characterId);
    const actual = actualOf(command.characterId);
    if (command.action === 'approve') {
      if (actual === null) throw new Error('Нет версии персонажа');
      const revision = gameRulesRevision(command.gameId);
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
      if (!isSessionActive(command.gameId)) clearCombatOverlay(command.gameId, entityKey);
      bindCharacterToGame(command.characterId, command.gameId);
    } else if (command.action === 'returnForRework') {
      membership.returnedAt = new Date().toISOString();
      membership.returnReason = 'Требуется доработка';
      await cancelGameParticipantProcesses(command.gameId, entityKey);
      const character = characters.find((entry) => entry.id === command.characterId);
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
    }
    membership.membershipRevision += 1;
    membership.updatedAt = new Date().toISOString();

    const result: GameCharacterModerationResult = {
      kind: 'transition',
      commandId: command.commandId,
      status:
        command.action === 'approve' ? 'approved' : command.action === 'returnForRework' ? 'returned' : 'rejected',
      membership: withVisibility(membership),
    };
    moderationCommands.set(command.commandId, { fingerprint, result: cloneData(result) });

    return result;
  });
}

export async function moderateCharacter(
  gameId: number,
  characterId: number,
  action: GameCharacterModerationAction,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  const result = await moderateCharacterCommand({
    commandId: createRandomId(),
    gameId,
    characterId,
    action,
    expectedActualVersion: getCharacterActualVersion(characterId),
    expectedMembershipRevision:
      gameCharacterMemberships.find(
        (membership) => membership.gameId === gameId && membership.characterId === characterId,
      )?.membershipRevision ?? 0,
  });
  if (result.kind === 'conflict') throw new Error(result.conflict.code);

  return result.membership;
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
  membership.membershipRevision += 1;
  membership.updatedAt = new Date().toISOString();
  unbindCharacter(characterId, gameId);

  return withVisibility(membership);
}

export function snapshotSessionActuals(gameId: number): Record<number, CharacterVersion | null> {
  const snapshot: Record<number, CharacterVersion | null> = {};
  for (const membership of gameCharacterMemberships) {
    if (membership.gameId !== gameId || membership.membershipStatus !== 'active') continue;
    const actual = actualOf(membership.characterId);
    snapshot[membership.characterId] = actual ? cloneData(actual) : null;
  }

  return snapshot;
}

export function restoreSessionActuals(snapshot: Record<number, CharacterVersion | null>): void {
  for (const [rawId, version] of Object.entries(snapshot)) {
    const characterId = Number(rawId);
    if (version === null) delete versions[characterId];
    else versions[characterId] = cloneData(version);
    if (versions[characterId]) syncCharacterVersion(characterId);
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
  expectedActualToken?: string,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const detail = gameDetails.find((entry) => entry.game.id === gameId);
  if (!detail) throw new Error('Игра не найдена');
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  if (membership.membershipStatus !== 'active') throw new Error('Миграция доступна только активному персонажу');
  if (getCurrentUserId() !== membership.characterOwnerId) throw new Error('Миграцию может отправить только владелец');
  if (isSessionActive(gameId)) throw new Error('Нельзя менять лист во время сессии');
  if (version.spaceCode !== detail.game.spaceCode || version.rulesRevision !== detail.game.rulesRevision) {
    throw new Error('Ревизия персонажа не совпадает с ревизией игры');
  }
  const revision = await fetchRevision(detail.game.spaceId, detail.game.rulesRevision);
  characterVersionIntegrityService.assertValid(version, revision.rules);
  if (expectedActualToken !== undefined) {
    const currentToken = JSON.stringify(cloneData(actualOf(characterId)));
    if (currentToken !== expectedActualToken) {
      throw new Error('Персонаж изменился, повторите миграцию');
    }
  }
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
