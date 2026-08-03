import { describe, it, expect } from 'vitest';
import {
  mockCreateMacro,
  mockDeleteMacro,
  mockGetMyMacros,
  mockUpdateMacro,
} from '@/modules/Roleplay/Game/Mock/mockMacros';

describe('mock macros', () => {
  it('возвращает сид текущего пользователя', async () => {
    const macros = await mockGetMyMacros();
    expect(macros.length).toBeGreaterThan(0);
    const attack = macros.find((m) => m.name === 'Атака мечом');
    expect(attack?.rolls[0]).toMatchObject({
      rollFormula: '5d6',
      adv: 1,
      dieSize: 0,
      rollLabel: 'Удар 1',
      variableAdvantages: false,
    });
    expect(macros.find((m) => m.name === 'Проверка силы')?.rolls[0].variableAdvantages).toBe(true);
    expect(macros.find((m) => m.name === 'Полная атака')?.rolls).toHaveLength(2);
    expect(macros.find((m) => m.name === 'Отдохнуть')?.rolls).toHaveLength(0);
  });

  it('создаёт макрос с несколькими бросками', async () => {
    const created = await mockCreateMacro({
      name: 'Комбо',
      textTemplate: 'Атакую',
      rolls: [
        { rollFormula: '5d6', efficiency: 3, adv: 1, dieSize: 0, rollLabel: 'Удар 1', variableAdvantages: false },
        { rollFormula: '4d6', efficiency: 2, adv: -1, dieSize: 1, rollLabel: 'Уклонение', variableAdvantages: true },
      ],
    });
    const macros = await mockGetMyMacros();
    expect(macros.find((m) => m.id === created.id)?.rolls).toHaveLength(2);
    expect(macros.find((m) => m.id === created.id)?.rolls[1]).toMatchObject({
      rollFormula: '4d6',
      adv: -1,
      dieSize: 1,
      variableAdvantages: true,
    });
  });

  it('созданный макрос без бросков получает пустой список', async () => {
    const created = await mockCreateMacro({ name: 'Фраза', textTemplate: 'Отдыхаю', rolls: [] });
    expect(created.rolls).toEqual([]);
  });

  it('обновляет броски макроса', async () => {
    const created = await mockCreateMacro({
      name: 'Старое',
      textTemplate: 'Текст',
      rolls: [{ rollFormula: '4d6', efficiency: 3, adv: 0, dieSize: 0, variableAdvantages: false }],
    });
    const updated = await mockUpdateMacro(created.id, {
      rolls: [
        { rollFormula: '5d6', efficiency: 3, adv: 2, dieSize: 0, variableAdvantages: false },
        { rollFormula: '3d6', efficiency: 3, adv: 0, dieSize: 0, variableAdvantages: false },
      ],
    });
    expect(updated.rolls).toHaveLength(2);
    expect(updated.rolls[0].adv).toBe(2);
  });

  it('удаляет макрос', async () => {
    const created = await mockCreateMacro({ name: 'На удаление', textTemplate: 'Текст', rolls: [] });
    await mockDeleteMacro(created.id);
    const macros = await mockGetMyMacros();
    expect(macros.find((m) => m.id === created.id)).toBeUndefined();
  });

  it('обновление несуществующего макроса бросает ошибку', async () => {
    await expect(mockUpdateMacro(9999, { rolls: [] })).rejects.toThrow('Макрос не найден');
  });
});
