import { describe, expect, it } from 'vitest';
import {
  chronicles,
  chronicleEntries,
  fetchChronicle,
  fetchChronicleEntries,
  createChronicleEntry,
  updateChronicleEntry,
  deleteChronicleEntry,
} from '@/modules/Roleplay/Game/Mock/mockGameChronicle';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameTimeToSeconds, normalizeGameTime } from '@/modules/Roleplay/Game/Utils/gameTime';
import { chronicleRefsFromContent } from '@/modules/Roleplay/Game/Utils/chronicleInline';
import type { GameTime } from '@/modules/Roleplay/Game/Dto/GameTime';

const gameIds = new Set(gameDetails.map((detail) => detail.game.id));

function time(partial: Partial<GameTime>): GameTime {
  return { years: 0, months: 0, decades: 0, days: 0, hours: 0, minutes: 0, ...partial };
}

function entryData(overrides: Partial<{ title: string; content: string; offset: GameTime }> = {}) {
  return {
    title: 'Событие',
    content: 'Описание события. Старый Бородач [[npc:1]] наблюдает.',
    offset: time({ days: 2 }),
    ...overrides,
  };
}

describe('mockGameChronicle: согласованность фикстур', () => {
  it('хроники и записи привязаны к существующим играм', () => {
    for (const chronicle of chronicles.values()) {
      expect(gameIds.has(chronicle.gameId), `gameId ${chronicle.gameId}`).toBe(true);
      expect(chronicle.epoch).toBe('adventure_start');
    }
    for (const entry of chronicleEntries) {
      expect(chronicles.has(entry.chronicleId), `chronicleId ${entry.chronicleId}`).toBe(true);
    }
  });

  it('related записей — производное от инлайн-токенов content', () => {
    for (const entry of chronicleEntries) {
      expect(entry.related).toEqual(chronicleRefsFromContent(entry.content));
    }
  });

  it('связанные сущности — персонажи игры и активные НПС игры', () => {
    for (const entry of chronicleEntries) {
      const chronicle = chronicles.get(entry.chronicleId);
      if (!chronicle) continue;
      for (const ref of entry.related) {
        if (ref.kind === 'character') {
          const membership = gameCharacterMemberships.find(
            (m) => m.gameId === chronicle.gameId && m.characterId === ref.id,
          );
          expect(membership, `character ${ref.id}`).toBeDefined();
        } else if (ref.kind === 'npc') {
          const npc = gameNpcs.find((n) => n.gameId === chronicle.gameId && n.id === ref.id);
          expect(npc?.status, `npc ${ref.id}`).toBe('active');
        }
      }
    }
  });

  it('сдвиги записей канонические', () => {
    for (const entry of chronicleEntries) {
      expect(entry.offset).toEqual(normalizeGameTime(entry.offset));
    }
  });
});

describe('mockGameChronicle: ленивое создание хроники', () => {
  it('getChronicle создаёт хронику игры при первом обращении', async () => {
    const chronicle = await fetchChronicle(5);
    expect(chronicle.gameId).toBe(5);
    expect(chronicle.epoch).toBe('adventure_start');
    expect(chronicle.name).toBeNull();

    const again = await fetchChronicle(5);
    expect(again.id).toBe(chronicle.id);
  });

  it('у новой хроники нет записей', async () => {
    const entries = await fetchChronicleEntries(5);
    expect(entries).toEqual([]);
  });
});

describe('mockGameChronicle: создание/правка/удаление', () => {
  it('create → fetch round-trip сохраняет токены и related', async () => {
    const created = await createChronicleEntry(1, entryData({ title: 'Свидание с Вороном' }));
    expect(created.id).toBeGreaterThan(0);
    expect(created.chronicleId).toBe(1);
    expect(created.createdBy).toBe(1);
    expect(created.content).toContain('[[npc:1]]');
    expect(created.related).toEqual([{ kind: 'npc', id: 1 }]);

    const all = await fetchChronicleEntries(1);
    expect(all.some((entry) => entry.id === created.id)).toBe(true);
  });

  it('сдвиг нормализуется при сохранении', async () => {
    const created = await createChronicleEntry(1, entryData({ offset: time({ months: 13 }) }));
    expect(created.offset).toEqual(time({ years: 1, months: 3 }));
  });

  it('дубли ссылок в content схлопываются', async () => {
    const created = await createChronicleEntry(
      1,
      entryData({ content: 'Трактир [[npc:1]], снова [[npc:1]] и Ворон [[npc:2]].' }),
    );
    expect(created.related).toEqual([
      { kind: 'npc', id: 1 },
      { kind: 'npc', id: 2 },
    ]);
  });

  it('update правой записи меняет поля, нормализует сдвиг и пересобирает related', async () => {
    const created = await createChronicleEntry(1, entryData());
    const updated = await updateChronicleEntry(
      created.id,
      entryData({ title: 'Изменено', content: 'Гаррик [[character:3]] в деле.', offset: time({ minutes: 61 }) }),
    );
    expect(updated.title).toBe('Изменено');
    expect(updated.offset).toEqual(time({ hours: 1, minutes: 1 }));
    expect(updated.related).toEqual([{ kind: 'character', id: 3 }]);
  });

  it('delete удаляет запись', async () => {
    const created = await createChronicleEntry(1, entryData());
    await deleteChronicleEntry(created.id);
    const all = await fetchChronicleEntries(1);
    expect(all.some((entry) => entry.id === created.id)).toBe(false);
  });

  it('возвращает копии, а не живые ссылки', async () => {
    const created = await createChronicleEntry(1, entryData());
    created.title = 'Испорчено';
    created.offset.days = 99;

    const fetched = await fetchChronicleEntries(1);
    const entry = fetched.find((e) => e.id === created.id);
    expect(entry?.title).toBe('Событие');
    expect(entry?.offset.days).toBe(2);
  });

  it('update/delete несуществующей записи — ошибка', async () => {
    await expect(updateChronicleEntry(99999, entryData())).rejects.toThrow('не найдена');
    await expect(deleteChronicleEntry(99999)).rejects.toThrow('не найдена');
  });
});

describe('mockGameChronicle: валидация', () => {
  it('пустой заголовок или содержимое — ошибка', async () => {
    await expect(createChronicleEntry(1, entryData({ title: '  ' }))).rejects.toThrow('Заголовок');
    await expect(createChronicleEntry(1, entryData({ content: '  ' }))).rejects.toThrow('Содержимое');
  });

  it('отрицательный сдвиг — ошибка', async () => {
    await expect(createChronicleEntry(1, entryData({ offset: time({ days: -1 }) }))).rejects.toThrow('неотрицательным');
  });

  it('ссылка на персонажа не из игры — ошибка', async () => {
    await expect(
      createChronicleEntry(1, entryData({ content: 'Торвин [[character:1]] на перекрёстке.' })),
    ).rejects.toThrow('не состоит в игре');
  });

  it('ссылка на НПС не из игры или неактивного — ошибка', async () => {
    await expect(createChronicleEntry(1, entryData({ content: 'Профессор [[npc:5]] в гостях.' }))).rejects.toThrow(
      'НПС',
    );
    await expect(createChronicleEntry(1, entryData({ content: 'Призрак [[npc:3]] объявился.' }))).rejects.toThrow(
      'активен',
    );
  });

  it('токены неизвестных типов игнорируются (не ошибка)', async () => {
    const created = await createChronicleEntry(1, entryData({ content: '[[monster:1]] подкрался.' }));
    expect(created.related).toEqual([]);
  });
});

describe('mockGameChronicle: сортировка по сдвигу', () => {
  it('записи возвращаются по возрастанию сдвига (стабильно по созданию)', async () => {
    const later = await createChronicleEntry(1, entryData({ title: 'Позже', offset: time({ days: 9 }) }));
    const earlier = await createChronicleEntry(1, entryData({ title: 'Раньше', offset: time({ hours: 1 }) }));

    const all = await fetchChronicleEntries(1);
    const offsets = all.map((entry) => gameTimeToSeconds(entry.offset));
    const sorted = [...offsets].sort((a, b) => a - b);
    expect(offsets).toEqual(sorted);

    expect(all.findIndex((entry) => entry.id === earlier.id)).toBeLessThan(
      all.findIndex((entry) => entry.id === later.id),
    );
  });

  it('изменение сдвига перемещает запись в хронике', async () => {
    const created = await createChronicleEntry(1, entryData({ title: 'Путешественник', offset: time({ days: 5 }) }));
    await updateChronicleEntry(created.id, entryData({ title: 'Путешественник', offset: time({ years: 10 }) }));

    const all = await fetchChronicleEntries(1);
    const index = all.findIndex((entry) => entry.id === created.id);
    expect(index).toBe(all.length - 1);

    await updateChronicleEntry(created.id, entryData({ title: 'Путешественник', offset: time({}) }));
    const allAfter = await fetchChronicleEntries(1);
    // Нулевой сдвиг — в начало хроники, сразу после фиксированной записи «Начало приключения»
    // (та же точка отсчёта, но та запись создана раньше по времени — стабильный тай-брейк).
    expect(allAfter.findIndex((entry) => entry.id === created.id)).toBe(1);
  });
});
