import { describe, expect, it } from 'vitest';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { fetchMechanics } from '@/modules/Roleplay/Rule/Mock/mockMechanics';
import { injuryHooksOf, resolveDamageTypeHooks } from '@/modules/Roleplay/Game/Utils/resolveDamageTypeHooks';
import {
  injuryDifficulty,
  injuryDifficultyBreakdown,
  manualInjuryAdvantages,
  rollInjury,
} from '@/modules/Roleplay/Game/Utils/injuryRoll';
import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { formatInjuryOutcome, formatInjuryReceivedMessage } from '@/modules/Roleplay/Game/Utils/injuryCheckMessage';
import { ruleValidationService } from '@/modules/Roleplay/Rule/Service/Instance/ruleValidationService';

function rngFromFaces(faces: number[]): DiceRng {
  let index = 0;

  return () => {
    const face = faces[index] ?? 1;
    index += 1;

    return (face - 1) / 6;
  };
}

describe('типы урона и увечье', () => {
  it('колющий даёт хук доп. сложности от РУ, рубящий — дельту эффективности (помеха)', async () => {
    const mechanics = await fetchMechanics();
    const piercing = injuryHooksOf(resolveDamageTypeHooks('piercing', ruleCatalog, mechanics));
    const slashing = injuryHooksOf(resolveDamageTypeHooks('slashing', ruleCatalog, mechanics));
    expect(piercing.some((hook) => hook.extraDiceFromSrDivisor === 2)).toBe(true);
    expect(slashing.some((hook) => hook.efficiencyDelta === -1)).toBe(true);
    expect(resolveDamageTypeHooks(null, ruleCatalog, mechanics)).toEqual([]);
  });

  it('сложность — max источников; истощение с 7, offset 6', () => {
    const procedure = resolveInjuryProcedure([], []);
    expect(
      injuryDifficulty({ leftoverDamage: 9, woundStrength: 4, endurance: 3, exhaustion: 0, attackSr: 0 }, procedure, 0),
    ).toBe(3);
    expect(
      injuryDifficulty({ leftoverDamage: 2, woundStrength: 0, endurance: 3, exhaustion: 6, attackSr: 0 }, procedure, 0),
    ).toBe(0);
    expect(
      injuryDifficulty({ leftoverDamage: 2, woundStrength: 0, endurance: 3, exhaustion: 7, attackSr: 0 }, procedure, 0),
    ).toBe(1);
    expect(
      injuryDifficulty({ leftoverDamage: 2, woundStrength: 0, endurance: 3, exhaustion: 8, attackSr: 0 }, procedure, 0),
    ).toBe(2);
    expect(
      injuryDifficulty(
        { leftoverDamage: 99, woundStrength: 99, endurance: 3, exhaustion: 0, attackSr: 0, difficulty: 2 },
        procedure,
        1,
      ),
    ).toBe(3);
  });

  it('истощение 15 → 9; рана 20 → 10 — берём max, не сырое 15', () => {
    const procedure = resolveInjuryProcedure([], []);
    const breakdown = injuryDifficultyBreakdown(
      { leftoverDamage: 6, woundStrength: 20, endurance: 1, exhaustion: 15, attackSr: 6 },
      procedure,
      0,
    );
    expect(breakdown.fromDamage).toBe(6);
    expect(breakdown.fromWound).toBe(10);
    expect(breakdown.fromExhaustion).toBe(9);
    expect(breakdown.total).toBe(10);
    expect(breakdown.source).toBe('wound');
  });

  it('сложность 0 — без броска, сила 0', async () => {
    const mechanics = await fetchMechanics();
    const result = rollInjury(
      { leftoverDamage: 0, woundStrength: 0, endurance: 3, exhaustion: 0, attackSr: 0 },
      rngFromFaces([6, 6, 6, 6]),
      ruleCatalog,
      mechanics,
    );
    expect(result.rolls).toEqual([]);
    expect(result.injury?.strength).toBe(0);
    expect(result.check?.passed).toBe(true);
  });

  it('всегда 4к6; успех — сила 0 без флагов', async () => {
    const mechanics = await fetchMechanics();
    const result = rollInjury(
      { leftoverDamage: 0, woundStrength: 0, endurance: 1, exhaustion: 0, attackSr: 0, difficulty: 1 },
      rngFromFaces([1, 1, 1, 1]),
      ruleCatalog,
      mechanics,
    );
    expect(result.spec.diceCount).toBe(4);
    expect(result.injury?.strength).toBe(0);
    expect(result.injury?.lethal).toBe(false);
    expect(result.injury?.permanent).toBe(false);
    expect(result.injury?.disfiguring).toBe(false);
    expect(result.check?.passed).toBe(true);
  });

  it('провал: сила = −РУ; ≥3 шестёрок — смертельное', async () => {
    const mechanics = await fetchMechanics();
    const result = rollInjury(
      { leftoverDamage: 0, woundStrength: 0, endurance: 1, exhaustion: 0, attackSr: 0, difficulty: 8 },
      rngFromFaces([6, 6, 6, 6, 1]),
      ruleCatalog,
      mechanics,
    );
    expect(result.check?.passed).toBe(false);
    expect(result.injury?.strength).toBeGreaterThan(0);
    expect(result.injury?.strength).toBe(-(result.injury?.rating ?? 0));
    expect(result.injury?.lethal).toBe(true);
  });

  it('≥2×6 и 5 — постоянное; ≥2×6 и 4 — обезображивающее', async () => {
    const mechanics = await fetchMechanics();
    const result = rollInjury(
      { leftoverDamage: 0, woundStrength: 0, endurance: 1, exhaustion: 0, attackSr: 0, difficulty: 20 },
      rngFromFaces([6, 6, 5, 4, 3, 3, 3, 3]),
      ruleCatalog,
      mechanics,
    );
    expect(result.injury?.permanent).toBe(true);
    expect(result.injury?.disfiguring).toBe(true);
    expect(result.injury?.lethal).toBe(false);
    expect(result.injury?.heal).toBeUndefined();
  });

  it('колющий добавляет floor(РУ/2) к сложности, не к пулу; рубящий — помеха', async () => {
    const mechanics = await fetchMechanics();
    const piercing = rollInjury(
      {
        leftoverDamage: 0,
        woundStrength: 0,
        endurance: 1,
        exhaustion: 0,
        attackSr: 4,
        difficulty: 1,
        damageTypeCode: 'piercing',
      },
      rngFromFaces([1, 1, 1, 1]),
      ruleCatalog,
      mechanics,
    );
    expect(piercing.spec.diceCount).toBe(4);
    expect(piercing.injury?.difficulty).toBe(3);

    const slashing = rollInjury(
      {
        leftoverDamage: 0,
        woundStrength: 0,
        endurance: 1,
        exhaustion: 0,
        attackSr: 0,
        difficulty: 1,
        damageTypeCode: 'slashing',
      },
      rngFromFaces([1, 2, 3, 4, 5]),
      ruleCatalog,
      mechanics,
    );
    expect(slashing.droppedRolls).toContain(1);
    expect(slashing.spec.diceCount).toBe(4);
  });

  it('преимущество убирает наибольшие грани, как у обычного броска', async () => {
    const mechanics = await fetchMechanics();
    const result = rollInjury(
      {
        leftoverDamage: 0,
        woundStrength: 0,
        endurance: 1,
        exhaustion: 0,
        attackSr: 0,
        difficulty: 1,
        advantages: manualInjuryAdvantages(1),
      },
      rngFromFaces([6, 5, 4, 3, 2]),
      ruleCatalog,
      mechanics,
    );
    expect(result.droppedRolls).toContain(6);
    expect(result.adjustedRolls).not.toContain(6);
  });

  it('строка исхода', () => {
    expect(
      formatInjuryOutcome({
        strength: 3,
        permanent: false,
        temporary: true,
        lethal: false,
        disfiguring: false,
        heal: { diceCount: 4, dieFaces: 6, unit: 'days', rolls: [1, 2, 3, 4], total: 10 },
      }),
    ).toContain('Увечье: 3');
    expect(
      formatInjuryReceivedMessage('Гаррик', {
        strength: 3,
        permanent: false,
        temporary: true,
        lethal: false,
        disfiguring: true,
        heal: { diceCount: 4, dieFaces: 6, unit: 'days', rolls: [1, 2, 3, 4], total: 10 },
      }),
    ).toBe(
      'Гаррик получает увечье с силой 3. Оно будет уменьшаться на 1 каждые 10 дней, пока полностью не пройдёт за 30 дней.\nУвечье обезображивает.',
    );
    expect(
      formatInjuryReceivedMessage('Гаррик', {
        strength: 2,
        permanent: false,
        temporary: true,
        lethal: false,
        disfiguring: false,
        heal: { diceCount: 2, dieFaces: 6, unit: 'days', rolls: [2, 2], total: 4 },
      }),
    ).toBe(
      'Гаррик получает увечье с силой 2. Оно будет уменьшаться на 1 каждые 4 дня, пока полностью не пройдёт за 8 дней.',
    );
    expect(
      formatInjuryReceivedMessage('Гаррик', {
        strength: 4,
        permanent: true,
        temporary: false,
        lethal: true,
        disfiguring: false,
      }),
    ).toBe('Гаррик получает постоянное увечье с силой 4.\nУвечье смертельно.');
  });

  it('валидация хуков типа урона', () => {
    const rules: Rule[] = [
      {
        id: 'dt',
        code: 'blunt',
        type: 'damage_type',
        name: 'Дробящий',
        description: '',
        spaceId: 1,
        spec: { type: 'damage_type', forms: { genitive: 'а', dative: 'б' }, attached_rule_codes: ['missing'] },
        createdAt: '2026-01-01T00:00:00Z',
      },
    ];
    const errors = ruleValidationService.validateDamageTypeStructure(rules);
    expect(errors.some((error) => error.message.includes('не найден'))).toBe(true);
  });

  it('каталог четырёх физических типов со спекой', () => {
    const piercing = ruleCatalog.find((rule) => rule.code === 'piercing');
    expect(piercing?.spec && 'attached_rule_codes' in piercing.spec).toBe(true);
  });
});
