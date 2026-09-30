import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameNpcSummary } from '@/modules/Roleplay/Game/Dto/GameNpcSummary';
import type { GameNpcListQuery } from '@/modules/Roleplay/Game/Dto/GameNpcListQuery';
import type { GameNpcListResult } from '@/modules/Roleplay/Game/Dto/GameNpcListResult';
import type { CreateNpcData } from '@/modules/Roleplay/Game/Dto/CreateNpcData';
import type { UpdateNpcData } from '@/modules/Roleplay/Game/Dto/UpdateNpcData';
import type { GameModerationAction } from '@/modules/Roleplay/Game/Enum/GameModerationAction';
import type { User } from '@/modules/Core/User/Dto/User';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { sheetAccessService } from '@/modules/Roleplay/Character/init';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

function userName(userId: number): string {
  const user = realUsers.find((u) => u.id === userId);

  return user ? [user.name, user.surname].filter(Boolean).join(' ') || user.login : 'Неизвестно';
}

// НПС игр (ТР §8). Инварианты: gameId — из mockGames, proposedBy — из mockUsers.
// `visibility.sections` — настройка видимости для игроков; version — полный лист (Н2), на Н1 null.
export const gameNpcs: GameNpc[] = [
  {
    id: 1,
    gameId: 1,
    name: 'Старый Бородач',
    shortDescription: 'Трактирщик на перекрёстке дорог, знает все сплетни округи.',
    fullDescription: null,
    tags: ['торговец', 'информатор'],
    version: null,
    actualVersion: 1,
    status: 'active',
    proposedBy: null,
    visibility: [{ audience: 'all', sections: ['shortDescription'] }],
    updatedAt: '2026-07-15T10:00:00',
  },
  {
    id: 2,
    gameId: 1,
    name: 'Капитан Ворон',
    shortDescription: 'Главарь наёмников, ищет отряд для дела в руинах.',
    fullDescription: 'Бывший капитан гвардии, знает вход в старую цитадель.',
    tags: ['наёмник', 'антагонист'],
    // Лист на ревизии 6 (игра 1 — actual v12): «Ночное зрение» выведено ≥ 8 — демо перевода НПС.
    version: {
      name: 'Капитан Ворон',
      shortDescription: 'Главарь наёмников, ищет отряд для дела в руинах.',
      fullDescription: 'Бывший капитан гвардии, знает вход в старую цитадель.',
      spaceCode: 'actual',
      rulesRevision: 6,
      raceRuleCode: null,
      characteristics: [],
      resources: [],
      abilities: [{ ruleCode: 'night-vision', level: 1 }],
      points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
      money: 0,
      ageYears: null,
      inventory: [],
      states: [],
      senses: [],
    },
    actualVersion: 1,
    status: 'active',
    proposedBy: null,
    visibility: [],
    updatedAt: '2026-07-20T14:00:00',
  },
  {
    id: 3,
    gameId: 1,
    name: 'Призрак Цитадели',
    shortDescription: 'Тень в развалинах, которую видели стражи.',
    fullDescription: null,
    tags: ['нежить', 'загадка'],
    version: null,
    actualVersion: 1,
    status: 'proposed',
    proposedBy: { userId: 1, userName: 'Иван Петров' },
    visibility: [{ audience: 'all', sections: ['shortDescription'] }],
    updatedAt: '2026-08-12T18:00:00',
  },
  {
    id: 4,
    gameId: 1,
    name: 'Осведомитель',
    shortDescription: 'Нервный человечек, знающий цену информации.',
    fullDescription: null,
    tags: ['информатор'],
    version: null,
    actualVersion: 1,
    status: 'active',
    proposedBy: null,
    visibility: [{ audience: [1], sections: ['shortDescription', 'fullDescription'] }],
    updatedAt: '2026-07-22T09:00:00',
  },
  {
    id: 5,
    gameId: 2,
    name: 'Профессор Шторм',
    shortDescription: 'Декан факультета стихий, скрытный и язвительный.',
    fullDescription: 'Держит в подвале лабораторию, куда не пускает студентов.',
    tags: ['маг', 'союзник'],
    version: null,
    actualVersion: 1,
    status: 'active',
    proposedBy: null,
    visibility: [{ audience: 'all', sections: ['shortDescription', 'fullDescription'] }],
    updatedAt: '2026-07-10T11:00:00',
  },
];

let nextNpcId = Math.max(0, ...gameNpcs.map((npc) => npc.id)) + 1;

export async function fetchNpcs(
  gameId: number,
  _signal?: AbortSignal,
  npcIds?: readonly number[],
): Promise<GameNpc[]> {
  await delay(150);
  const requestedIds = npcIds ? new Set(npcIds) : null;

  return gameNpcs.filter((npc) => npc.gameId === gameId && (requestedIds === null || requestedIds.has(npc.id)));
}

/**
 * Возвращает ограниченную страницу кандидатов-НПС после применения eligibility-фильтра.
 */
export async function fetchNpcCandidatePage(
  gameId: number,
  query: string | undefined,
  offset: number,
  limit: number,
  includeNpc: (npc: GameNpc) => boolean,
): Promise<{ items: GameNpc[]; nextOffset: number | null }> {
  await delay(150);
  const normalizedQuery = query?.trim().toLocaleLowerCase() ?? '';
  const filtered = gameNpcs
    .filter((npc) => {
      if (npc.gameId !== gameId || !includeNpc(npc)) return false;

      return (
        normalizedQuery.length === 0 ||
        [npc.name, npc.shortDescription ?? '', ...npc.tags].some((value) =>
          value.toLocaleLowerCase().includes(normalizedQuery),
        )
      );
    })
    .sort((left, right) => {
      const nameOrder = left.name.localeCompare(right.name, 'ru');

      return nameOrder !== 0 ? nameOrder : left.id - right.id;
    });
  const pageOffset = Number.isInteger(offset) && offset >= 0 ? offset : 0;
  const pageLimit = Math.min(Math.max(limit, 1), 50);
  const items = filtered.slice(pageOffset, pageOffset + pageLimit);
  const nextOffset = pageOffset + items.length < filtered.length ? pageOffset + items.length : null;

  return { items, nextOffset };
}

export async function fetchNpcSummaries(query: GameNpcListQuery, _signal?: AbortSignal): Promise<GameNpcListResult> {
  await delay(150);
  const normalizedQuery = query.query?.trim().toLocaleLowerCase() ?? '';
  const filtered = gameNpcs.filter((npc) => {
    if (npc.gameId !== query.gameId) return false;
    if (!isVisibleToCurrentUser(npc)) return false;
    if (query.status && npc.status !== query.status) return false;
    if (
      normalizedQuery &&
      ![npc.name, npc.shortDescription ?? '', ...npc.tags].some((value) =>
        value.toLocaleLowerCase().includes(normalizedQuery),
      )
    ) {
      return false;
    }

    return true;
  });
  const offset = query.cursor ? Number(query.cursor) : 0;
  const start = Number.isInteger(offset) && offset >= 0 ? offset : 0;
  const limit = Math.min(Math.max(query.limit ?? 50, 1), 100);
  const user = currentUser();
  const items = filtered.slice(start, start + limit).map((npc) => toNpcSummary(npc, user));
  const nextOffset = start + items.length;

  return {
    items,
    nextCursor: nextOffset < filtered.length ? String(nextOffset) : null,
  };
}

export async function fetchNpc(gameId: number, npcId: number, _signal?: AbortSignal): Promise<GameNpc> {
  await delay(100);
  const npc = gameNpcs.find((entry) => entry.id === npcId && entry.gameId === gameId);
  if (!npc) throw new Error('НПС не найден');

  return visibleNpcProjection(npc);
}

function toNpcSummary(npc: GameNpc, user: User | null): GameNpcSummary {
  const { version: _version, ...summary } = cloneData(npc);

  return {
    ...summary,
    visibility: safeVisibility(npc, user),
    actualSpaceCode: npc.version?.spaceCode ?? null,
    actualRulesRevision: npc.version?.rulesRevision ?? null,
  };
}

function currentUser(): User | null {
  return realUsers.find((user) => user.id === getCurrentUserId()) ?? null;
}

function isVisibleToCurrentUser(npc: GameNpc): boolean {
  const user = currentUser();
  if (!user) return false;

  return sheetAccessService.canSeeSheet(user, npc.visibility, npcContext(user, npc));
}

function visibleNpcProjection(npc: GameNpc): GameNpc {
  const user = currentUser();
  if (!user || !isVisibleToCurrentUser(npc)) throw new Error('НПС недоступен');

  const visibleSections = sheetAccessService.visibleSheetSections(user, npc.visibility, npcContext(user, npc));
  const projection = cloneData(npc);
  if (!visibleSections.includes('shortDescription')) projection.shortDescription = null;
  if (!visibleSections.includes('fullDescription')) projection.fullDescription = null;
  if (!SHEET_VISIBLE_SECTIONS.every((section) => visibleSections.includes(section))) projection.version = null;
  projection.visibility = safeVisibility(npc, user);

  return projection;
}

function safeVisibility(npc: GameNpc, user: User | null): GameNpc['visibility'] {
  if (!user) return [];
  const visibleSections = sheetAccessService.visibleSheetSections(user, npc.visibility, npcContext(user, npc));
  if (SHEET_VISIBLE_SECTIONS.every((section) => visibleSections.includes(section))) {
    return cloneData(npc.visibility);
  }

  return [{ audience: 'all', sections: visibleSections }];
}

function npcContext(user: User, npc: GameNpc): SheetAccessContext {
  return { user, ownerId: null, characterId: npc.id, gameId: npc.gameId };
}

export function getStoredNpcVersion(npcId: number): GameNpc['version'] {
  const npc = gameNpcs.find((entry) => entry.id === npcId);
  if (!npc) throw new Error('НПС не найден');

  return cloneData(npc.version);
}

export function initializeNpcRuntimeVersion(npcId: number, version: NonNullable<GameNpc['version']>): GameNpc {
  const index = gameNpcs.findIndex((entry) => entry.id === npcId);
  if (index === -1) throw new Error('НПС не найден');
  if (gameNpcs[index].version !== null) return cloneData(gameNpcs[index]);

  gameNpcs[index].version = cloneData(version);
  gameNpcs[index].updatedAt = new Date().toISOString();

  return cloneData(gameNpcs[index]);
}

export function captureNpcRuntimeState(npcId: number): GameNpc {
  const npc = gameNpcs.find((entry) => entry.id === npcId);
  if (!npc) throw new Error('НПС не найден');

  return cloneData(npc);
}

export function restoreNpcRuntimeState(npcId: number, snapshot: GameNpc): void {
  const index = gameNpcs.findIndex((entry) => entry.id === npcId);
  if (index === -1) throw new Error('НПС не найден');

  gameNpcs[index] = cloneData(snapshot);
}

export function replaceNpcRuntimeVersion(
  npcId: number,
  version: NonNullable<GameNpc['version']>,
  expectedActualVersion: number,
): GameNpc {
  const index = gameNpcs.findIndex((entry) => entry.id === npcId);
  if (index === -1) throw new Error('НПС не найден');
  if (gameNpcs[index].actualVersion !== expectedActualVersion) {
    throw new Error('Актуальное состояние НПС уже изменилось');
  }

  Object.assign(gameNpcs[index], {
    version: cloneData(version),
    actualVersion: expectedActualVersion + 1,
    updatedAt: new Date().toISOString(),
  });

  return cloneData(gameNpcs[index]);
}

function buildNpc(gameId: number, data: CreateNpcData, status: GameNpc['status']): GameNpc {
  return {
    id: nextNpcId++,
    gameId,
    name: data.name,
    shortDescription: data.shortDescription,
    fullDescription: data.fullDescription,
    tags: [...data.tags],
    version: null,
    actualVersion: 1,
    status,
    proposedBy: null,
    visibility: data.visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] })),
    updatedAt: new Date().toISOString(),
  };
}

/** Создание НПС ведущим (status 'active'). */
export async function createNpc(gameId: number, data: CreateNpcData, _signal?: AbortSignal): Promise<GameNpc> {
  await delay(200);
  const npc = buildNpc(gameId, data, 'active');
  gameNpcs.push(npc);

  return { ...npc };
}

/** Предложение НПС игроком (status 'proposed', proposedBy — текущий пользователь). */
export async function proposeNpc(gameId: number, data: CreateNpcData, _signal?: AbortSignal): Promise<GameNpc> {
  await delay(200);
  const userId = getCurrentUserId();
  const npc: GameNpc = {
    ...buildNpc(gameId, data, 'proposed'),
    proposedBy: { userId, userName: userName(userId) },
  };
  gameNpcs.push(npc);

  return { ...npc };
}

/** Редактирование НПС ведущим: имя, описания, теги, видимость, полный лист (version). */
export async function updateNpc(npcId: number, data: UpdateNpcData, _signal?: AbortSignal): Promise<GameNpc> {
  await delay(200);
  const idx = gameNpcs.findIndex((npc) => npc.id === npcId);
  if (idx === -1) throw new Error('НПС не найден');
  if (gameNpcs[idx].actualVersion !== data.expectedNpcActualVersion) {
    throw new Error('НПС изменился, повторите сохранение');
  }
  gameNpcs[idx] = {
    ...gameNpcs[idx],
    name: data.name,
    shortDescription: data.shortDescription,
    fullDescription: data.fullDescription,
    tags: [...data.tags],
    visibility: data.visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] })),
    version: data.version,
    actualVersion: gameNpcs[idx].actualVersion + 1,
    updatedAt: new Date().toISOString(),
  };

  return { ...gameNpcs[idx] };
}

/** Модерация предложенного НПС: approve → active; reject → предложение удаляется. */
export async function moderateNpc(
  npcId: number,
  action: GameModerationAction,
  _signal?: AbortSignal,
): Promise<GameNpc> {
  await delay(200);
  const idx = gameNpcs.findIndex((npc) => npc.id === npcId);
  if (idx === -1) throw new Error('НПС не найден');
  if (action === 'approve') {
    gameNpcs[idx] = {
      ...gameNpcs[idx],
      status: 'active',
      proposedBy: null,
      actualVersion: gameNpcs[idx].actualVersion + 1,
      updatedAt: new Date().toISOString(),
    };

    return { ...gameNpcs[idx] };
  }
  const removed = gameNpcs[idx];
  gameNpcs.splice(idx, 1);

  return { ...removed };
}

export async function deleteNpc(npcId: number, _signal?: AbortSignal): Promise<void> {
  await delay(200);
  const idx = gameNpcs.findIndex((npc) => npc.id === npcId);
  if (idx === -1) throw new Error('НПС не найден');
  gameNpcs.splice(idx, 1);
}
