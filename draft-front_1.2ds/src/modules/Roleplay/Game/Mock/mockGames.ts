import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { GameVisibility } from '@/modules/Roleplay/Game/Enum/GameVisibility';
import type { GameJoinPolicy } from '@/modules/Roleplay/Game/Enum/GameJoinPolicy';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';
import type { User } from '@/modules/Core/User/Dto/User';
import type { UpdateGameMemberData } from '@/modules/Roleplay/Game/Dto/UpdateGameMemberData';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import { resolvePermissions } from '@/modules/Core/User/Mock/resolvePermissions';
import {
  mockCreateGameDiscussion,
  mockCreateGameChat,
  mockSetChatMembers,
} from '@/modules/Messages/Chat/Mock/mockChat';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';
import { endInitiative } from '@/modules/Roleplay/Game/Mock/mockGameInitiative';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

function userName(userId: number): string {
  const user = realUsers.find((u) => u.id === userId);

  return user ? [user.name, user.surname].filter(Boolean).join(' ') || user.login : 'Неизвестно';
}

// Текущий пользователь с мёрженными правами — без window (getCurrentUserId резолвит id в тестах и браузере).
function currentUser(): User | null {
  const id = getCurrentUserId();
  const real = realUsers.find((u) => u.id === id);
  if (!real) return null;

  return { ...real, permissions: resolvePermissions(real.groups) };
}

function member(userId: number, role: GameMemberRole, permissions: string[] = []): GameMember {
  return { userId, userName: userName(userId), role, permissions };
}

interface GameSeed {
  id: number;
  name: string;
  shortDescription: string | null;
  description: string | null;
  status: GameStatus;
  visibility: GameVisibility;
  joinPolicy: GameJoinPolicy;
  ownerId: number;
  spaceId: number;
  spaceCode: string;
  rulesRevision: number;
  osPointsLimit: number | null;
  olPointsLimit: number | null;
  orPointsLimit: number | null;
  moneyLimit: number | null;
  tags: string[];
  forbiddenTags: string[];
  members: GameMember[];
}

function seedToDetail(seed: GameSeed): GameDetail {
  const game: Game = {
    id: seed.id,
    name: seed.name,
    shortDescription: seed.shortDescription,
    status: seed.status,
    visibility: seed.visibility,
    joinPolicy: seed.joinPolicy,
    ownerId: seed.ownerId,
    ownerName: userName(seed.ownerId),
    spaceId: seed.spaceId,
    spaceCode: seed.spaceCode,
    rulesRevision: seed.rulesRevision,
    memberCount: seed.members.length,
    tags: seed.tags,
    gameChatId: null,
    discussionChatId: null,
  };

  return {
    game,
    description: seed.description,
    osPointsLimit: seed.osPointsLimit,
    olPointsLimit: seed.olPointsLimit,
    orPointsLimit: seed.orPointsLimit,
    moneyLimit: seed.moneyLimit,
    forbiddenTags: seed.forbiddenTags,
    members: seed.members,
    discussionChatId: null,
    gameChatId: null,
  };
}

// Инварианты фикстур (как у персонажей): spaceId 1 = razrabotka (revision ≤ 5), spaceId 2 = actual (revision ≤ 12);
// владельцы/участники — из mockUsers; 'Забытые земли'/'Школа волшебства'/'Подземелье дракона' согласованы
// с game-чатами mockChat и gameId персонажей mockCharacters. Согласованность проверяется тестом mockGames.test.ts.
const seeds: GameSeed[] = [
  {
    id: 1,
    name: 'Забытые земли',
    shortDescription: 'Открытый мир на границе забытых королевств',
    description:
      'Экспедиция в земли, что некогда были сердцем империи. Древние руины, конкурирующие фракции и магия, ' +
      'которую лучше не будить.',
    status: 'recruiting',
    visibility: 'all',
    joinPolicy: 'anyone',
    ownerId: 3,
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 12,
    osPointsLimit: 12,
    olPointsLimit: 4,
    orPointsLimit: 25,
    moneyLimit: 10000,
    tags: ['фэнтези', 'открытый мир'],
    forbiddenTags: ['техно'],
    members: [member(3, 'owner'), member(5, 'gm'), member(4, 'player', ['game.edit']), member(1, 'player')],
  },
  {
    id: 2,
    name: 'Школа волшебства',
    shortDescription: 'Академия магии и первые испытания студентов',
    description: 'Первый курс академии: соперничество факультетов, тайные подвалы и выпускной экзамен.',
    status: 'playing',
    visibility: 'all',
    joinPolicy: 'invite_only',
    ownerId: 3,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    osPointsLimit: 10,
    olPointsLimit: 3,
    orPointsLimit: null,
    moneyLimit: 8000,
    tags: ['магия', 'школа'],
    forbiddenTags: [],
    members: [member(3, 'owner'), member(4, 'player'), member(6, 'player')],
  },
  {
    id: 3,
    name: 'Подземелье дракона',
    shortDescription: 'Опасное подземелье с драконом в глубине',
    description: 'Отряд спускается в логово древнего дракона. Каждый зал — проверка на прочность.',
    status: 'paused',
    visibility: 'friends',
    joinPolicy: 'friends',
    ownerId: 5,
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 12,
    osPointsLimit: 12,
    olPointsLimit: null,
    orPointsLimit: 30,
    moneyLimit: 12000,
    tags: ['данжен', 'дракон'],
    forbiddenTags: ['киберпанк'],
    members: [member(5, 'owner'), member(3, 'gm'), member(4, 'player'), member(6, 'player')],
  },
  {
    id: 4,
    name: 'Сага о северных землях',
    shortDescription: 'Черновик: эпос о морозных землях и древних клятвах',
    description: 'Северные ярлы спорят за трон, а за стеной льда просыпается нечто старое.',
    status: 'draft',
    visibility: 'all',
    joinPolicy: 'anyone',
    ownerId: 1,
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 12,
    osPointsLimit: 12,
    olPointsLimit: 4,
    orPointsLimit: 25,
    moneyLimit: 10000,
    tags: ['эпос', 'север'],
    forbiddenTags: [],
    members: [member(1, 'owner')],
  },
  {
    id: 5,
    name: 'Драконы и пророчества',
    shortDescription: 'Завершённая кампания о великом пророчестве',
    description: 'Архивная кампания, пройденная до конца. Данные доступны только на чтение.',
    status: 'completed',
    visibility: 'all',
    joinPolicy: 'invite_only',
    ownerId: 3,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    osPointsLimit: null,
    olPointsLimit: null,
    orPointsLimit: null,
    moneyLimit: null,
    tags: ['эпик'],
    forbiddenTags: [],
    members: [member(3, 'owner'), member(4, 'player'), member(5, 'player'), member(7, 'player')],
  },
  {
    id: 6,
    name: 'Городские тени',
    shortDescription: 'Интриги столицы и ночная жизнь',
    description: 'Игра для приглашённых: дворцовые заговоры и шёпот наёмников.',
    status: 'in_process',
    visibility: 'invited',
    joinPolicy: 'invite_only',
    ownerId: 7,
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 12,
    osPointsLimit: 10,
    olPointsLimit: 3,
    orPointsLimit: 20,
    moneyLimit: 6000,
    tags: ['интриги'],
    forbiddenTags: [],
    members: [member(7, 'owner'), member(3, 'gm'), member(10, 'player')],
  },
];

export const gameDetails: GameDetail[] = seeds.map(seedToDetail);

function assignChatIds(detail: GameDetail, discussionChatId: number | null, gameChatId: number | null): void {
  detail.discussionChatId = discussionChatId;
  detail.gameChatId = gameChatId;
  detail.game.discussionChatId = discussionChatId;
  detail.game.gameChatId = gameChatId;
}

// Обсуждение и игровой чат: совпадающие по имени в mockChat переиспользуем, иначе создаём.
// Заполняется до использования (nextChatId в mockChat растёт). Дубль на карточке списка и деталке.
const existingDiscussionChatId: Record<number, number> = { 2: 12, 3: 3 };
const existingGameChatId: Record<number, number> = { 2: 2, 3: 1 };
for (const detail of gameDetails) {
  assignChatIds(
    detail,
    existingDiscussionChatId[detail.game.id] ?? mockCreateGameDiscussion(detail.game.name),
    existingGameChatId[detail.game.id] ?? mockCreateGameChat(detail.game.name),
  );
}

/** Роль игрового чата из роли участника игры: владелец/ведущий → 'gm', игрок → 'player'. */
function chatRoleOf(role: GameMemberRole): string {
  return role === 'player' ? 'player' : 'gm';
}

/**
 * Синк ролей игровых чатов из членов игры (Game пишет роли в чат; Chat их не интерпретирует).
 * Нужно для видимости сообщений (forRole: 'gm' → chat.see_all) и корректности в мессенджере.
 * Вызывается при создании/изменении членов игры (включая заявки/приглашения).
 */
export function syncGameChatRoles(detail: GameDetail): void {
  const members = detail.members.map((memberItem) => ({
    userId: memberItem.userId,
    role: chatRoleOf(memberItem.role),
  }));
  if (detail.gameChatId !== null) mockSetChatMembers(detail.gameChatId, members);
  if (detail.discussionChatId !== null) mockSetChatMembers(detail.discussionChatId, members);
}

for (const detail of gameDetails) {
  syncGameChatRoles(detail);
}

let nextGameId = Math.max(0, ...gameDetails.map((d) => d.game.id)) + 1;

/** Личные заметки зрителя: ключ gameId:userId. Не хранятся на GameDetail в gameDetails. */
const personalNotesByKey: Record<string, string> = {
  '1:1': 'Спросить Анну про руины на севере.',
};

function personalNotesKey(gameId: number, userId: number): string {
  return `${gameId}:${userId}`;
}

function toViewerGameDetail(detail: GameDetail): GameDetail {
  const copy = JSON.parse(JSON.stringify(detail)) as GameDetail;
  copy.personalNotes = personalNotesByKey[personalNotesKey(copy.game.id, getCurrentUserId())] ?? null;

  return copy;
}

/** Список игр — как бэк: только видимые текущему пользователю (статус/видимость/участие). */
export async function fetchGames(_signal?: AbortSignal): Promise<Game[]> {
  await delay(150);
  const user = currentUser();

  return gameDetails
    .filter((detail) =>
      gameAccessService.canViewGame(
        user,
        detail.game,
        detail.members.map((m) => m.userId),
      ),
    )
    .map((detail) => detail.game);
}

export async function fetchGame(id: number, _signal?: AbortSignal): Promise<GameDetail> {
  await delay(150);
  const detail = gameDetails.find((d) => d.game.id === id);
  if (!detail) throw new Error('Игра не найдена');

  // JSON round-trip как у настоящего API: mock хранит только plain-данные. Без копии UI
  // (reactive-объекты из Pinia, например permissions в members) попадали бы в gameDetails,
  // и structuredClone в Store/games.fetchGame падал бы («[object Array] could not be cloned»).
  return toViewerGameDetail(detail);
}

export async function createGame(data: CreateGameData, _signal?: AbortSignal): Promise<GameDetail> {
  await delay(200);
  const ownerId = getCurrentUserId();
  const detail: GameDetail = {
    game: {
      id: nextGameId++,
      name: data.name,
      shortDescription: data.shortDescription,
      status: data.status,
      visibility: data.visibility,
      joinPolicy: data.joinPolicy,
      ownerId,
      ownerName: userName(ownerId),
      spaceId: data.spaceId,
      spaceCode: data.spaceCode,
      rulesRevision: data.rulesRevision,
      memberCount: 1,
      tags: data.tags,
      gameChatId: null,
      discussionChatId: null,
    },
    description: data.description,
    osPointsLimit: data.osPointsLimit,
    olPointsLimit: data.olPointsLimit,
    orPointsLimit: data.orPointsLimit,
    moneyLimit: data.moneyLimit,
    forbiddenTags: data.forbiddenTags,
    members: [member(ownerId, 'owner')],
    discussionChatId: null,
    gameChatId: null,
  };
  assignChatIds(detail, mockCreateGameDiscussion(data.name), mockCreateGameChat(data.name));
  gameDetails.push(detail);
  syncGameChatRoles(detail);

  return toViewerGameDetail(detail);
}

export async function updateGame(id: number, data: CreateGameData, _signal?: AbortSignal): Promise<GameDetail> {
  await delay(200);
  const idx = gameDetails.findIndex((d) => d.game.id === id);
  if (idx === -1) throw new Error('Игра не найдена');
  const current = gameDetails[idx];
  const leavingSession = current.game.status === 'playing' && data.status !== 'playing';
  const updated: GameDetail = {
    ...current,
    game: {
      ...current.game,
      name: data.name,
      shortDescription: data.shortDescription,
      status: data.status,
      visibility: data.visibility,
      joinPolicy: data.joinPolicy,
      spaceId: data.spaceId,
      spaceCode: data.spaceCode,
      rulesRevision: data.rulesRevision,
      tags: [...(data.tags ?? [])],
    },
    description: data.description,
    osPointsLimit: data.osPointsLimit,
    olPointsLimit: data.olPointsLimit,
    orPointsLimit: data.orPointsLimit,
    moneyLimit: data.moneyLimit,
    forbiddenTags: [...(data.forbiddenTags ?? [])],
    // Копия members: current мог накопить reactive-объекты (permissions из UI) — сохраняем plain.
    members: JSON.parse(JSON.stringify(current.members)) as GameDetail['members'],
  };
  gameDetails[idx] = updated;
  if (leavingSession) endInitiative(id);

  return toViewerGameDetail(updated);
}

export async function updatePersonalNotes(gameId: number, notes: string, _signal?: AbortSignal): Promise<GameDetail> {
  await delay(150);
  const detail = gameDetails.find((entry) => entry.game.id === gameId);
  if (!detail) throw new Error('Игра не найдена');
  const user = currentUser();
  if (
    !gameAccessService.canViewGame(
      user,
      detail.game,
      detail.members.map((member) => member.userId),
    )
  ) {
    throw new Error('Forbidden');
  }
  const key = personalNotesKey(gameId, getCurrentUserId());
  const trimmed = notes.trim();
  if (trimmed) personalNotesByKey[key] = trimmed;
  else delete personalNotesByKey[key];

  return toViewerGameDetail(detail);
}

/** Обновление участника: роль + индивидуальные per-game права (ТР §3 `game_member_permissions`). */
export async function updateGameMember(
  gameId: number,
  userId: number,
  data: UpdateGameMemberData,
  _signal?: AbortSignal,
): Promise<GameMember> {
  await delay(200);
  const detail = gameDetails.find((d) => d.game.id === gameId);
  if (!detail) throw new Error('Игра не найдена');
  const idx = detail.members.findIndex((m) => m.userId === userId);
  if (idx === -1) throw new Error('Участник не найден');
  detail.members[idx] = { ...detail.members[idx], role: data.role, permissions: [...(data.permissions ?? [])] };
  syncGameChatRoles(detail);

  return detail.members[idx];
}

export async function addGameMember(
  gameId: number,
  userId: number,
  role: GameMemberRole,
  _signal?: AbortSignal,
): Promise<GameMember> {
  await delay(200);
  const detail = gameDetails.find((d) => d.game.id === gameId);
  if (!detail) throw new Error('Игра не найдена');
  if (detail.members.some((m) => m.userId === userId)) throw new Error('Участник уже в игре');
  const member: GameMember = { userId, userName: userName(userId), role, permissions: [] };
  detail.members.push(member);
  detail.game.memberCount = detail.members.length;
  syncGameChatRoles(detail);

  return member;
}

export async function removeGameMember(gameId: number, userId: number, _signal?: AbortSignal): Promise<void> {
  await delay(200);
  const detail = gameDetails.find((d) => d.game.id === gameId);
  if (!detail) throw new Error('Игра не найдена');
  detail.members = detail.members.filter((m) => m.userId !== userId);
  detail.game.memberCount = detail.members.length;
  syncGameChatRoles(detail);
}
