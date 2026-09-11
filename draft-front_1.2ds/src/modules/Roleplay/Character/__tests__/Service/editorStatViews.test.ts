import { describe, expect, it } from 'vitest';
import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { EditorStatViewsService } from '@/modules/Roleplay/Character/Service/EditorStatViewsService';
import { editorStatViewsService } from '@/modules/Roleplay/Character/Service/Instance/editorStatViewsService';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';

const service = new EditorStatViewsService();

function characteristicRule(code: string, group: 'primary' | 'secondary' | 'magic', formula?: string): Rule {
  return {
    id: null,
    code,
    type: 'characteristic',
    name: code,
    description: '',
    spaceId: 1,
    spec: {
      type: 'characteristic',
      group,
      formula,
    },
    createdAt: 0,
  };
}

function characteristic(code: string): EditorCharacteristic {
  return {
    ruleCode: code,
    code,
    name: code,
    base: { base: 3, size: 0 },
    delta: 0,
    value: { base: 3, size: 0 },
    modifiers: [],
  };
}

describe('EditorStatViewsService', () => {
  it('включает основные, магические и производные из других групп без дублей', () => {
    const rules = [
      characteristicRule('strength', 'primary'),
      characteristicRule('perception', 'secondary', 'strength'),
      characteristicRule('magic-power', 'magic'),
      characteristicRule('secondary-only', 'secondary'),
    ];
    const characteristics = rules.map((rule) => characteristic(rule.code));

    const stats = service.buildEditorStatViews(characteristics, rules);

    expect(stats.map((stat) => stat.characteristic.code)).toEqual(['strength', 'perception', 'magic-power']);
    expect(stats.find((stat) => stat.characteristic.code === 'perception')).toMatchObject({
      derived: true,
      bases: [{ code: 'strength' }],
    });
  });

  it('сохраняет полный набор для попапа «Все характеристики»', () => {
    const rules = [
      characteristicRule('strength', 'primary'),
      characteristicRule('perception', 'secondary', 'strength'),
      characteristicRule('secondary-only', 'secondary'),
    ];

    const stats = service.buildAllEditorStatViews(
      rules.map((rule) => characteristic(rule.code)),
      rules,
    );

    expect(stats.map((stat) => stat.characteristic.code)).toEqual(['strength', 'perception', 'secondary-only']);
  });
});


/**
 * Набор характеристик человека (как в правилах ревизии): 9 штук — Сила, Ловкость,
 * Стойкость, Восприятие, Интеллект (primary/производные) и Внимательность, Реакция,
 * Память, Мышление (base). Производные: Восприятие = min(Внимательность, Реакция),
 * Интеллект = min(Память, Мышление).
 */
function characteristics(): { code: string; name: string; base: { base: number; size: number } }[] {
  const codes = [
    'strength',
    'dexterity',
    'endurance',
    'perception',
    'intellect',
    'attention',
    'reaction',
    'memory',
    'reasoning',
  ];
  const byName: Record<string, string> = {
    strength: 'Сила',
    dexterity: 'Ловкость',
    endurance: 'Стойкость',
    perception: 'Восприятие',
    intellect: 'Интеллект',
    attention: 'Внимательность',
    reaction: 'Реакция',
    memory: 'Память',
    reasoning: 'Мышление',
  };

  return codes.map((code) => ({
    code,
    name: byName[code],
    base: { base: 3, size: 0 },
  }));
}

const editorCharacteristics = characteristics().map((entry) => ({
  ruleCode: entry.code,
  code: entry.code,
  name: entry.name,
  base: { base: 3, size: 0 },
  delta: 0,
  value: { base: 3, size: 0 },
  modifiers: [],
}));

describe('buildEditorStatViews', () => {
  it('показывает только основные характеристики (group primary)', () => {
    const views = editorStatViewsService.buildEditorStatViews(editorCharacteristics, ruleCatalog);
    expect(views.map((view) => view.characteristic.code)).toEqual([
      'strength',
      'dexterity',
      'endurance',
      'perception',
      'intellect',
    ]);
  });

  it('Восприятие — производная от Внимательность и Реакция', () => {
    const views = editorStatViewsService.buildEditorStatViews(editorCharacteristics, ruleCatalog);
    const perception = views.find((view) => view.characteristic.code === 'perception');
    expect(perception?.derived).toBe(true);
    expect(perception?.bases.map((base) => base.code)).toEqual(['attention', 'reaction']);
  });

  it('Интеллект — производная от Память и Мышление', () => {
    const views = editorStatViewsService.buildEditorStatViews(editorCharacteristics, ruleCatalog);
    const intellect = views.find((view) => view.characteristic.code === 'intellect');
    expect(intellect?.derived).toBe(true);
    expect(intellect?.bases.map((base) => base.code)).toEqual(['memory', 'reasoning']);
  });

  it('базовые характеристики не производные', () => {
    const views = editorStatViewsService.buildEditorStatViews(editorCharacteristics, ruleCatalog);
    const strength = views.find((view) => view.characteristic.code === 'strength');
    expect(strength?.derived).toBe(false);
    expect(strength?.bases).toEqual([]);
  });
});
