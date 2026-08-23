import { describe, expect, it } from 'vitest';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { fetchMechanics } from '@/modules/Roleplay/Rule/Mock/mockMechanics';
import { injuryHooksOf, resolveDamageTypeHooks } from '@/modules/Roleplay/Game/Utils/resolveDamageTypeHooks';
import { injuryPoolSize, rollInjury } from '@/modules/Roleplay/Game/Utils/injuryRoll';
import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { formatInjuryOutcome } from '@/modules/Roleplay/Game/Utils/injuryCheckMessage';
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
  it('колющий даёт хук доп. кубов, рубящий — дельту эффективности', async () => {
    const mechanics = await fetchMechanics();
    const piercing = injuryHooksOf(resolveDamageTypeHooks('piercing', ruleCatalog, mechanics));
    const slashing = injuryHooksOf(resolveDamageTypeHooks('slashing', ruleCatalog, mechanics));
    expect(piercing.some((hook) => hook.extraDiceFromSrDivisor === 2)).toBe(true);
    expect(slashing.some((hook) => hook.efficiencyDelta === -1)).toBe(true);
    expect(resolveDamageTypeHooks(null, ruleCatalog, mechanics)).toEqual([]);
  });

  it('пул — max источников, не сумма', () => {
    const procedure = resolveInjuryProcedure([], []);
    expect(
      injuryPoolSize({ damage: 10, woundStrength: 4, endurance: 3, exhaustion: 0, attackSr: 0 }, procedure, 0),
    ).toBe(3);
    expect(
      injuryPoolSize({ damage: 2, woundStrength: 0, endurance: 3, exhaustion: 8, attackSr: 0 }, procedure, 0),
    ).toBe(3);
  });

  it('взрыв шестёрок рекурсивный, 1–2 сбрасываются', async () => {
    const mechanics = await fetchMechanics();
    const result = rollInjury(
      { damage: 3, woundStrength: 0, endurance: 3, exhaustion: 0, attackSr: 0 },
      rngFromFaces([6, 6, 6, 2]),
      ruleCatalog,
      mechanics,
    );
    expect(result.injury?.strength).toBe(3);
    expect(result.injury?.lethal).toBe(true);
    expect(result.droppedRolls).toContain(2);
  });

  it('ровно одна 4 — обезображивание; две 5 — постоянное', async () => {
    const mechanics = await fetchMechanics();
    const scar = rollInjury(
      { damage: 3, woundStrength: 0, endurance: 3, exhaustion: 0, attackSr: 0 },
      rngFromFaces([4]),
      ruleCatalog,
      mechanics,
    );
    expect(scar.injury?.disfiguring).toBe(true);
    expect(scar.injury?.temporary).toBe(true);
    expect(scar.injury?.heal?.unit).toBe('days');

    const permanent = rollInjury(
      { damage: 6, woundStrength: 0, endurance: 3, exhaustion: 0, attackSr: 0 },
      rngFromFaces([5, 5]),
      ruleCatalog,
      mechanics,
    );
    expect(permanent.injury?.permanent).toBe(true);
    expect(permanent.injury?.temporary).toBe(false);
    expect(permanent.injury?.heal).toBeUndefined();
  });

  it('колющий добавляет кубы от РУ; рубящий оставляет двойки', async () => {
    const mechanics = await fetchMechanics();
    const piercing = rollInjury(
      { damage: 3, woundStrength: 0, endurance: 3, exhaustion: 0, attackSr: 4, damageTypeCode: 'piercing' },
      rngFromFaces([3, 3, 3]),
      ruleCatalog,
      mechanics,
    );
    expect(piercing.spec.diceCount).toBe(3);

    const slashing = rollInjury(
      { damage: 3, woundStrength: 0, endurance: 3, exhaustion: 0, attackSr: 0, damageTypeCode: 'slashing' },
      rngFromFaces([2]),
      ruleCatalog,
      mechanics,
    );
    expect(slashing.injury?.strength).toBe(1);
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
