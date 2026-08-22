import type { Chronicle } from '@/modules/Roleplay/Game/Dto/Chronicle';
import type { ChronicleEntry } from '@/modules/Roleplay/Game/Dto/ChronicleEntry';
import type { CreateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/CreateChronicleEntryData';
import type { UpdateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/UpdateChronicleEntryData';
import type { ChronicleRef } from '@/modules/Roleplay/Game/Dto/ChronicleRef';
import { gameTimeToSeconds, normalizeGameTime } from '@/modules/Roleplay/Game/Utils/gameTime';
import { chronicleRefsFromContent } from '@/modules/Roleplay/Game/Utils/chronicleInline';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

function epochOf(gameId: number): Chronicle {
  return { id: gameId, gameId, name: null, epoch: 'adventure_start' };
}

// Летописи игр (ТР §3 `chronicles`). Первая игра — с фикстурами, остальные создаются лениво
// при первом обращении (`getChronicle`), как и у настоящего бэка. id хроники = gameId.
export const chronicles = new Map<number, Chronicle>([[1, { ...epochOf(1), name: 'Летопись Забытых земель' }]]);

// Записи фиксированной летописи: сдвиги — канонические, отсортированные по возрастанию.
// Ссылки на персонажей/НПС — инлайн-токенами `[[character:id]]`/`[[npc:id]]` в content
// (как в чате); `related` — производное от них (инвариант проверяется тестом).
export const chronicleEntries: ChronicleEntry[] = [
  {
    id: 1,
    chronicleId: 1,
    title: 'Начало приключения',
    content: 'Отряд собирается в трактире на перекрёстке дорог. Старый Бородач [[npc:1]] передаёт просьбу о помощи.',
    offset: { years: 0, months: 0, decades: 0, days: 0, hours: 0, minutes: 0 },
    related: [{ kind: 'npc', id: 1 }],
    createdBy: 1,
    createdAt: '2026-07-15T10:00:00',
    updatedAt: '2026-07-15T10:00:00',
  },
  {
    id: 2,
    chronicleId: 1,
    title: 'Бой у логова вождей',
    content:
      'Тролльи вожди разбиты, в логове найден боевой посох и казна набега. Гаррик [[character:3]] забирает посох.',
    offset: { years: 0, months: 0, decades: 0, days: 1, hours: 4, minutes: 0 },
    related: [{ kind: 'character', id: 3 }],
    createdBy: 1,
    createdAt: '2026-08-01T10:00:00',
    updatedAt: '2026-08-01T10:00:00',
  },
  {
    id: 3,
    chronicleId: 1,
    title: 'Новая угроза в руинах',
    content:
      'Капитан Ворон [[npc:2]] предлагает отряду сделку, связанную со старой цитаделью. Морган [[character:4]] настороже.',
    offset: { years: 0, months: 1, decades: 0, days: 2, hours: 0, minutes: 0 },
    related: [
      { kind: 'npc', id: 2 },
      { kind: 'character', id: 4 },
    ],
    createdBy: 1,
    createdAt: '2026-08-12T18:00:00',
    updatedAt: '2026-08-12T18:00:00',
  },
];

let nextEntryId = Math.max(0, ...chronicleEntries.map((entry) => entry.id)) + 1;

export async function fetchChronicle(gameId: number, _signal?: AbortSignal): Promise<Chronicle> {
  await delay(100);
  let chronicle = chronicles.get(gameId);
  if (!chronicle) {
    chronicle = epochOf(gameId);
    chronicles.set(gameId, chronicle);
  }

  return { ...chronicle };
}

function compareEntries(a: ChronicleEntry, b: ChronicleEntry): number {
  const byOffset = gameTimeToSeconds(a.offset) - gameTimeToSeconds(b.offset);
  if (byOffset !== 0) return byOffset;
  const byCreatedAt = a.createdAt.localeCompare(b.createdAt);
  if (byCreatedAt !== 0) return byCreatedAt;

  return a.id - b.id;
}

/** Записи хроники игры, отсортированные по сдвигу от точки отсчёта (стабильно по созданию). */
export async function fetchChronicleEntries(gameId: number, _signal?: AbortSignal): Promise<ChronicleEntry[]> {
  await delay(150);

  return chronicleEntries
    .filter((entry) => entry.chronicleId === chronicleOf(gameId).id)
    .sort(compareEntries)
    .map((entry) => ({ ...entry, offset: { ...entry.offset }, related: entry.related.map((ref) => ({ ...ref })) }));
}

function chronicleOf(gameId: number): Chronicle {
  let chronicle = chronicles.get(gameId);
  if (!chronicle) {
    chronicle = epochOf(gameId);
    chronicles.set(gameId, chronicle);
  }

  return chronicle;
}

function assertEntryData(data: CreateChronicleEntryData): void {
  if (!data.title.trim()) throw new Error('Заголовок записи не может быть пустым');
  if (!data.content.trim()) throw new Error('Содержимое записи не может быть пустым');
  for (const unit of Object.values(data.offset)) {
    if (!Number.isFinite(unit) || unit < 0) throw new Error('Сдвиг времени должен быть неотрицательным');
  }
}

/**
 * Связанные сущности: персонажи игры (по членствам) и активные НПС игры. Форма предлагает
 * только approved-персонажей, мок допускает любого участника игры — запись может «пережить»
 * смену статуса члена (прототип).
 */
function assertRelated(gameId: number, related: ChronicleRef[]): void {
  const memberships = gameCharacterMemberships.filter((membership) => membership.gameId === gameId);
  const npcs = gameNpcs.filter((npc) => npc.gameId === gameId);
  for (const ref of related) {
    if (ref.kind === 'character') {
      const membership = memberships.find((candidate) => candidate.characterId === ref.id);
      if (!membership) throw new Error('Ссылка на персонажа: персонаж не состоит в игре');
    } else if (ref.kind === 'npc') {
      const npc = npcs.find((candidate) => candidate.id === ref.id);
      if (!npc || npc.status !== 'active') throw new Error('Ссылка на НПС: НПС должен быть активен в игре');
    } else {
      throw new Error('Неизвестный тип связанной сущности');
    }
  }
}

function uniqueRelated(related: ChronicleRef[]): ChronicleRef[] {
  const seen = new Set<string>();
  const result: ChronicleRef[] = [];
  for (const ref of related) {
    const key = `${ref.kind}:${ref.id}`;
    if (seen.has(key)) continue;
    seen.add(key);
    result.push({ ...ref });
  }

  return result;
}

function buildEntry(gameId: number, data: CreateChronicleEntryData, id: number): ChronicleEntry {
  const now = new Date().toISOString();
  const related = uniqueRelated(chronicleRefsFromContent(data.content));

  return {
    id,
    chronicleId: chronicleOf(gameId).id,
    title: data.title.trim(),
    content: data.content.trim(),
    offset: normalizeGameTime(data.offset),
    related,
    createdBy: getCurrentUserId(),
    createdAt: now,
    updatedAt: now,
  };
}

/** Создание записи летописи ведущим (нормализует сдвиг; позиция — по сдвигу). */
export async function createChronicleEntry(
  gameId: number,
  data: CreateChronicleEntryData,
  _signal?: AbortSignal,
): Promise<ChronicleEntry> {
  await delay(200);
  assertEntryData(data);
  assertRelated(gameId, chronicleRefsFromContent(data.content));
  const entry = buildEntry(gameId, data, nextEntryId++);
  chronicleEntries.push(entry);

  return { ...entry, offset: { ...entry.offset }, related: entry.related.map((ref) => ({ ...ref })) };
}

/** Редактирование записи летописи ведущим (изменение сдвига двигает запись в хронике). */
export async function updateChronicleEntry(
  entryId: number,
  data: UpdateChronicleEntryData,
  _signal?: AbortSignal,
): Promise<ChronicleEntry> {
  await delay(200);
  const idx = chronicleEntries.findIndex((entry) => entry.id === entryId);
  if (idx === -1) throw new Error('Запись летописи не найдена');
  const gameId = chronicles.get(chronicleEntries[idx].chronicleId)?.gameId;
  if (gameId === undefined) throw new Error('Летопись не найдена');
  assertEntryData(data);
  const related = uniqueRelated(chronicleRefsFromContent(data.content));
  assertRelated(gameId, related);
  const updated: ChronicleEntry = {
    ...chronicleEntries[idx],
    title: data.title.trim(),
    content: data.content.trim(),
    offset: normalizeGameTime(data.offset),
    related,
    updatedAt: new Date().toISOString(),
  };
  chronicleEntries[idx] = updated;

  return { ...updated, offset: { ...updated.offset }, related: updated.related.map((ref) => ({ ...ref })) };
}

export async function deleteChronicleEntry(entryId: number, _signal?: AbortSignal): Promise<void> {
  await delay(200);
  const idx = chronicleEntries.findIndex((entry) => entry.id === entryId);
  if (idx === -1) throw new Error('Запись летописи не найдена');
  chronicleEntries.splice(idx, 1);
}
