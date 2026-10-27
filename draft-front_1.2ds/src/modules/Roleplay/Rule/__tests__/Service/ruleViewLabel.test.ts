import { describe, it, expect } from 'vitest';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

const rules: Rule[] = [
  {
    mechanics: [],
    id: null,
    code: 'strength',
    type: 'characteristic',
    name: 'Сила',
    description: '',
    spaceId: 1,
    createdAt: 1767225600,
  },
  {
    mechanics: [],
    id: null,
    code: 'fire',
    type: 'damage_type',
    name: 'Огонь',
    description: '',
    spaceId: 1,
    createdAt: 1767225600,
  },
  {
    mechanics: [],
    id: null,
    code: 'sense-hearing',
    type: 'sense',
    name: 'Слух',
    description: '',
    spaceId: 1,
    createdAt: 1767225600,
  },
  {
    mechanics: [],
    id: null,
    code: 'innate',
    type: 'source',
    name: 'Врождённый',
    description: '',
    spaceId: 1,
    createdAt: 1767225600,
  },
];

const keywords: Keyword[] = [{ id: 1, code: 'weapon', name: 'Оружие', description: '', active: true }];

describe('RuleViewLabelService', () => {
  it('подписывает сопротивление и чувство', () => {
    expect(
      ruleViewLabelService.grant(
        {
          type: 'resistance',
          damage_type_code: 'fire',
          value: { type: 'parameter', parameter_code: 'x', per_unit: 2 },
          source_code: 'innate',
        },
        rules,
        keywords,
      ),
    ).toContain('x × 2');
    expect(
      ruleViewLabelService.grant(
        {
          type: 'sense_modify',
          sense_code: 'sense-hearing',
          amount: { type: 'fixed', value: 3 },
          source_code: 'innate',
        },
        rules,
        keywords,
      ),
    ).toContain('Слух');
  });

  it('подписывает ops модификатора значениями', () => {
    expect(ruleViewLabelService.modifierOp({ type: 'weight', factor: 0.5 }, rules, keywords)).toBe('Вес: ×0.5');
    expect(ruleViewLabelService.modifierOp({ type: 'keyword', add: ['weapon'] }, rules, keywords)).toBe(
      'Признаки: + Оружие',
    );
  });

  it('размерные числа — канонической нотацией со стрелкой', () => {
    expect(ruleViewLabelService.dimensional({ base: 3, size: -1 })).toBe('3↓');
    expect(ruleViewLabelService.dimensional({ base: 5, size: 2 })).toBe('5↑²');
    expect(ruleViewLabelService.dimensional({ base: 4, size: 0 })).toBe('4');
  });

  it('поля профиля оружия: тип урона в подписи, сила действия — человеческой фразой', () => {
    const profile: WeaponProfile = {
      type: 'strike',
      distance: {
        min: { type: 'ipari', value: { type: 'fixed', value: 0 } },
        max: { type: 'ipari', value: { type: 'dimensional', base: 1, size: -1 } },
      },
      damage: {
        formula: {
          type: 'actionCharacteristic',
          action: 'strike',
          characteristic: 'strength',
          modifier: [{ delta: -3, source_code: null, source_label: null }],
        },
        damage_type_code: 'piercing',
      },
      penetration: {
        type: 'actionCharacteristic',
        action: 'strike',
        characteristic: 'strength',
        modifier: [{ delta: -3, source_code: null, source_label: null }],
      },
      accuracy: { base: 4, size: 0 },
      dodge_benefit: -4,
    };

    const fields = ruleViewLabelService.weaponProfileFields(profile, rules);

    expect(fields.map((field) => field.label)).toEqual([
      'Колющий урон',
      'Пробитие',
      'Точность',
      'Дистанция',
      'Польза уклонения',
    ]);
    expect(fields).toContainEqual({ label: 'Колющий урон', value: 'Сила удара − 3' });
    expect(fields).toContainEqual({ label: 'Пробитие', value: 'Сила удара − 3' });
    expect(fields).toContainEqual({ label: 'Дистанция', value: '0 - 1↓' });
    expect(fields).toContainEqual({ label: 'Точность', value: '4' });
    expect(fields).toContainEqual({ label: 'Польза уклонения', value: '-4' });
  });

  it('дальность стрелкового профиля идёт в ячейке дистанции через дефис', () => {
    const profile: WeaponProfile = {
      type: 'shoot',
      distance: {
        min: { type: 'ipari', value: { type: 'dimensional', base: 1, size: 0 } },
        max: { type: 'ipari', value: { type: 'fixed', value: 10 } },
      },
      damage: { formula: { type: 'fixed', value: 1 }, damage_type_code: 'piercing' },
      penetration: { type: 'fixed', value: 2 },
      accuracy: { base: 3, size: 0 },
      falloff: { type: 'ipari', value: { type: 'literal', value: { base: 5, size: 0 } } },
      dodge_benefit: -6,
    };

    const fields = ruleViewLabelService.weaponProfileFields(profile, rules);

    expect(fields.map((field) => field.label)).toEqual([
      'Колющий урон',
      'Пробитие',
      'Точность',
      'Дистанция',
      'Дальнобойность',
      'Польза уклонения',
    ]);
    expect(fields).toContainEqual({ label: 'Дистанция', value: '1 - 10' });
    expect(fields).toContainEqual({ label: 'Дальнобойность', value: '5' });
  });

  it('подписывает сопротивление в дательном падеже', () => {
    expect(
      ruleViewLabelService.resistanceLabel({
        damage_type_code: 'fire',
        value: { base: 2, size: 0 },
        durability: 3,
        source_code: null,
      }),
    ).toBe('Сопротивление огню: 2, надёжность 3');
  });
});
