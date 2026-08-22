import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CreateNpcData } from '@/modules/Roleplay/Game/Dto/CreateNpcData';
import type { UpdateNpcData } from '@/modules/Roleplay/Game/Dto/UpdateNpcData';
import type { GameModerationAction } from '@/modules/Roleplay/Game/Enum/GameModerationAction';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';

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
    version: null,
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
    status: 'active',
    proposedBy: null,
    visibility: [{ audience: 'all', sections: ['shortDescription', 'fullDescription'] }],
    updatedAt: '2026-07-10T11:00:00',
  },
];

let nextNpcId = Math.max(0, ...gameNpcs.map((npc) => npc.id)) + 1;

export async function fetchNpcs(gameId: number, _signal?: AbortSignal): Promise<GameNpc[]> {
  await delay(150);

  return gameNpcs.filter((npc) => npc.gameId === gameId);
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
  gameNpcs[idx] = {
    ...gameNpcs[idx],
    name: data.name,
    shortDescription: data.shortDescription,
    fullDescription: data.fullDescription,
    tags: [...data.tags],
    visibility: data.visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] })),
    version: data.version,
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
    gameNpcs[idx] = { ...gameNpcs[idx], status: 'active', proposedBy: null, updatedAt: new Date().toISOString() };

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
