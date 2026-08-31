import { describe, it, expect } from 'vitest';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

function rollOf(res: ReturnType<typeof rollService.parseRollCommand>): DiceRollSpec | undefined {
  return res?.attachments.find((a) => a.type === ROLL_ATTACHMENT_TYPE)?.payload as DiceRollSpec | undefined;
}

describe('parseRollFormula', () => {
  it('разбирает латинскую формулу', () => {
    expect(rollService.parseRollFormula('3d6')).toEqual({ diceCount: 3, dieFaces: 6 });
  });

  it('разбирает кириллическую формулу', () => {
    expect(rollService.parseRollFormula('5к10')).toEqual({ diceCount: 5, dieFaces: 10 });
  });

  it('отклоняет не-формулу', () => {
    expect(rollService.parseRollFormula('abc')).toBeNull();
    expect(rollService.parseRollFormula('3d')).toBeNull();
  });

  it('отклоняет нулевой и завышенный количество кубов', () => {
    expect(rollService.parseRollFormula('0d6')).toBeNull();
    expect(rollService.parseRollFormula('31d6')).toBeNull();
  });

  it('отклоняет некорректную размерность грани', () => {
    expect(rollService.parseRollFormula('3d1')).toBeNull();
    expect(rollService.parseRollFormula('3d101')).toBeNull();
  });
});

describe('parseRollCommand', () => {
  it('возвращает null для обычного текста', () => {
    expect(rollService.parseRollCommand('Привет всем')).toBeNull();
    expect(rollService.parseRollCommand('/roll')).toBeNull();
    expect(rollService.parseRollCommand('/roll без формулы')).toBeNull();
  });

  it('разбирает минимальную команду', () => {
    const res = rollService.parseRollCommand('/roll 3d6');
    expect(res?.content).toBe('/roll 3d6');
    expect(rollOf(res)).toEqual({
      diceCount: 3,
      dieFaces: 6,
      efficiency: 3,
      advantages: [],
      dieSize: 0,
      label: undefined,
    });
  });

  it('учитывает эффективность и метку', () => {
    const res = rollService.parseRollCommand('/roll 3d6 e:2 Проверка на силу');
    expect(rollOf(res)?.efficiency).toBe(2);
    expect(rollOf(res)?.label).toBe('Проверка на силу');
  });

  it('превращает dis в отрицательный adv', () => {
    expect(
      aggregateSourceDeltasService.netSourceDelta(
        rollOf(rollService.parseRollCommand('/roll 3d6 dis:2'))?.advantages ?? [],
      ),
    ).toBe(-2);
    expect(
      aggregateSourceDeltasService.netSourceDelta(
        rollOf(rollService.parseRollCommand('/roll 3d6 pom:1'))?.advantages ?? [],
      ),
    ).toBe(-1);
  });

  it('разбирает adv и префикс prem', () => {
    expect(
      aggregateSourceDeltasService.netSourceDelta(
        rollOf(rollService.parseRollCommand('/roll 3d6 adv:1'))?.advantages ?? [],
      ),
    ).toBe(1);
    expect(
      aggregateSourceDeltasService.netSourceDelta(
        rollOf(rollService.parseRollCommand('/roll 3d6 prem:2'))?.advantages ?? [],
      ),
    ).toBe(2);
  });

  it('ограничивает adv максимумом', () => {
    expect(
      aggregateSourceDeltasService.netSourceDelta(
        rollOf(rollService.parseRollCommand('/roll 3d6 adv:99'))?.advantages ?? [],
      ),
    ).toBe(10);
    expect(
      aggregateSourceDeltasService.netSourceDelta(
        rollOf(rollService.parseRollCommand('/roll 3d6 dis:99'))?.advantages ?? [],
      ),
    ).toBe(-10);
  });

  it('отбрасывает невалидную эффективность в пользу дефолта', () => {
    expect(rollOf(rollService.parseRollCommand('/roll 3d6 e:0'))?.efficiency).toBe(3);
    expect(rollOf(rollService.parseRollCommand('/roll 3d6 e:21'))?.efficiency).toBe(3);
  });

  it('разбирает размерность', () => {
    expect(rollOf(rollService.parseRollCommand('/roll 3d6 size:2'))?.dieSize).toBe(2);
    expect(rollOf(rollService.parseRollCommand('/roll 3d6 dim:-1'))?.dieSize).toBe(-1);
  });
});

describe('formatPoolNotation / formatEfficiencyLabel', () => {
  it('пул несёт размер мастерства, эффективность — свой размер', () => {
    expect(rollService.formatPoolNotation({ diceCount: 4, dieFaces: 6, dieSize: -1, poolSize: 0 })).toBe('4к6');
    expect(rollService.formatEfficiencyLabel({ efficiency: 4, efficiencySize: -1 })).toBe('4↓');
    expect(rollService.formatPoolNotation({ diceCount: 5, dieFaces: 6, dieSize: -1, poolSize: -1 })).toBe('5↓к6');
    expect(rollService.formatEfficiencyLabel({ efficiency: 5, efficiencySize: 0 })).toBe('5');
  });
});

describe('formatRollSize', () => {
  it('возвращает пустую строку для нуля', () => {
    expect(rollService.formatRollSize(0)).toBe('');
  });

  it('форматирует одиночную размерность', () => {
    expect(rollService.formatRollSize(1)).toBe('↑');
    expect(rollService.formatRollSize(-1)).toBe('↓');
  });

  it('использует суперскрипт для модуля ≥ 2', () => {
    expect(rollService.formatRollSize(2)).toBe('↑²');
    expect(rollService.formatRollSize(-3)).toBe('↓³');
    expect(rollService.formatRollSize(10)).toBe('↑¹⁰');
  });
});
