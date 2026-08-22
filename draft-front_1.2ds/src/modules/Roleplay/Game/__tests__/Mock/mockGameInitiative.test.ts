import { describe, expect, it } from 'vitest';
import { fetchInitiative, saveInitiative } from '@/modules/Roleplay/Game/Mock/mockGameInitiative';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';

function makeData(overrides: Partial<GameInitiative> = {}): GameInitiative {
  return {
    gameId: 1,
    active: true,
    participants: [
      { id: 'character:3', name: 'Гаррик', kind: 'character', entityId: 3 },
      { id: 'npc:1', name: 'Дракон', kind: 'npc', entityId: 1 },
      { id: 'character:5', name: 'Элиандра', kind: 'character', entityId: 5 },
    ],
    activeIndex: 0,
    round: 1,
    updatedAt: '',
    ...overrides,
  };
}

describe('mockGameInitiative', () => {
  it('пустая игра отдаёт неактивную пустую шкалу', async () => {
    const result = await fetchInitiative(999);
    expect(result.active).toBe(false);
    expect(result.participants).toEqual([]);
    expect(result.activeIndex).toBeNull();
    expect(result.round).toBe(1);
  });

  it('save → get round-trip сохраняет порядок участников, active, текущий ход и раунд', async () => {
    const saved = await saveInitiative(1, makeData({ round: 3 }));
    expect(saved.updatedAt).not.toBe('');

    const fetched = await fetchInitiative(1);
    expect(fetched.participants).toEqual(saved.participants);
    expect(fetched.participants.map((p) => p.id)).toEqual(['character:3', 'npc:1', 'character:5']);
    expect(fetched.active).toBe(true);
    expect(fetched.activeIndex).toBe(0);
    expect(fetched.round).toBe(3);
  });

  it('сохраняет участников, добавленных в бой (в конец, без значения)', async () => {
    const data = makeData({
      participants: [
        ...makeData().participants,
        { id: 'character:7', name: 'Новичок', kind: 'character', entityId: 7 },
      ],
      activeIndex: 2,
    });
    await saveInitiative(1, data);
    const fetched = await fetchInitiative(1);
    expect(fetched.participants.at(-1)?.name).toBe('Новичок');
    expect(fetched.activeIndex).toBe(2);
  });

  it('возвращает копии, а не живые ссылки', async () => {
    const stored = await saveInitiative(1, makeData());
    stored.participants[0].name = 'Изменено';
    stored.activeIndex = 2;

    const fetched = await fetchInitiative(1);
    expect(fetched.participants[0].name).toBe('Гаррик');
    expect(fetched.activeIndex).toBe(0);
  });

  it('валидирует activeIndex вне диапазона', async () => {
    await expect(saveInitiative(1, makeData({ activeIndex: 5 }))).rejects.toThrow('вне диапазона');
  });

  it('валидирует участника без имени', async () => {
    await expect(
      saveInitiative(1, makeData({ participants: [{ id: 'x', name: '', kind: 'character', entityId: null }] })),
    ).rejects.toThrow('должен иметь id и имя');
  });

  it('валидирует неизвестный тип участника', async () => {
    await expect(
      saveInitiative(
        1,
        makeData({ participants: [{ id: 'x', name: 'Босс', kind: 'enemy' as never, entityId: null }] }),
      ),
    ).rejects.toThrow('Неизвестный тип участника');
  });

  it('валидирует номер раунда < 1', async () => {
    await expect(saveInitiative(1, makeData({ round: 0 }))).rejects.toThrow('Номер раунда должен быть >= 1');
  });
});
