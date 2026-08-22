import { describe, expect, it } from 'vitest';
import {
  gameNpcs,
  fetchNpcs,
  createNpc,
  proposeNpc,
  updateNpc,
  moderateNpc,
  deleteNpc,
} from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import type { CreateNpcData } from '@/modules/Roleplay/Game/Dto/CreateNpcData';

const gameIds = new Set(gameDetails.map((detail) => detail.game.id));
const userIds = new Set(realUsers.map((user) => user.id));

function makeData(name: string): CreateNpcData {
  return {
    name,
    shortDescription: 'Краткое описание',
    fullDescription: null,
    tags: ['торговец'],
    visibility: [{ audience: 'all', sections: ['shortDescription'] }],
  };
}

describe('mockGameNpcs: согласованность фикстур', () => {
  it('gameId и proposedBy существуют в моках', () => {
    for (const npc of gameNpcs) {
      expect(gameIds.has(npc.gameId), `gameId ${npc.gameId}`).toBe(true);
      if (npc.proposedBy) expect(userIds.has(npc.proposedBy.userId)).toBe(true);
    }
  });

  it('fetchNpcs возвращает НПС только нужной игры', async () => {
    const game1 = await fetchNpcs(1);
    expect(game1.every((npc) => npc.gameId === 1)).toBe(true);
    expect(game1.length).toBeGreaterThan(0);
  });
});

describe('mockGameNpcs: создание, предложение, модерация', () => {
  it('createNpc создаёт активного НПС', async () => {
    const npc = await createNpc(1, makeData('Трактирщик'));
    expect(npc.status).toBe('active');
    expect(npc.proposedBy).toBeNull();
    expect(npc.visibility).toEqual([{ audience: 'all', sections: ['shortDescription'] }]);
    expect(npc.tags).toEqual(['торговец']);
  });

  it('updateNpc меняет теги', async () => {
    const created = await createNpc(1, makeData('С тегами'));
    const updated = await updateNpc(created.id, {
      name: 'С новыми тегами',
      shortDescription: null,
      fullDescription: null,
      tags: ['наёмник', 'антагонист'],
      visibility: [{ audience: 'all', sections: ['shortDescription'] }],
      version: null,
    });
    expect(updated.tags).toEqual(['наёмник', 'антагонист']);
  });

  it('proposeNpc создаёт предложение от текущего пользователя', async () => {
    const npc = await proposeNpc(1, makeData('Загадочный незнакомец'));
    expect(npc.status).toBe('proposed');
    expect(npc.proposedBy?.userId).toBe(1);
  });

  it('updateNpc меняет поля и видимость', async () => {
    const created = await createNpc(1, makeData('До правки'));
    const updated = await updateNpc(created.id, {
      name: 'После правки',
      shortDescription: 'Новое описание',
      fullDescription: null,
      tags: [],
      visibility: [],
      version: null,
    });
    expect(updated.name).toBe('После правки');

    expect(updated.visibility).toEqual([]);
  });

  it('updateNpc сохраняет полный лист (version) round-trip', async () => {
    const created = await createNpc(1, makeData('С листом'));
    const version = {
      name: 'Трактирщик Бородач',
      shortDescription: 'Кратко',
      fullDescription: 'Полно',
      spaceCode: 'actual',
      rulesRevision: 12,
      raceRuleId: null,
      characteristics: [],
      resources: [],
      abilities: [],
      points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
      money: 0,
      ageYears: null,
      inventory: [],
      states: [],
      senses: [],
    };
    const updated = await updateNpc(created.id, {
      name: version.name,
      shortDescription: version.shortDescription,
      fullDescription: version.fullDescription,
      tags: [],
      visibility: [{ audience: 'all', sections: ['shortDescription'] }],
      version,
    });
    expect(updated.version).toEqual(version);
    expect(updated.name).toBe('Трактирщик Бородач');
  });

  it('approve предложения делает НПС активным', async () => {
    const proposed = await proposeNpc(1, makeData('На модерацию'));
    const moderated = await moderateNpc(proposed.id, 'approve');
    expect(moderated.status).toBe('active');
    expect(moderated.proposedBy).toBeNull();
  });

  it('reject предложения удаляет НПС', async () => {
    const proposed = await proposeNpc(1, makeData('Отклонённый'));
    await moderateNpc(proposed.id, 'reject');
    const list = await fetchNpcs(1);
    expect(list.some((npc) => npc.id === proposed.id)).toBe(false);
  });

  it('deleteNpc удаляет активного НПС', async () => {
    const created = await createNpc(1, makeData('На удаление'));
    await deleteNpc(created.id);
    const list = await fetchNpcs(1);
    expect(list.some((npc) => npc.id === created.id)).toBe(false);
  });
});
