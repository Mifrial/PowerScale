import { describe, expect, it } from 'vitest';
import { buildEditorStatViews } from '@/modules/Roleplay/Character/Utils/editorStatViews';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';

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
  ruleId: entry.code,
  code: entry.code,
  name: entry.name,
  base: { base: 3, size: 0 },
  delta: 0,
  value: { base: 3, size: 0 },
  modifiers: [],
}));

describe('buildEditorStatViews', () => {
  it('показывает только основные характеристики (group primary)', () => {
    const views = buildEditorStatViews(editorCharacteristics, ruleCatalog);
    expect(views.map((view) => view.characteristic.code)).toEqual([
      'strength',
      'dexterity',
      'endurance',
      'perception',
      'intellect',
    ]);
  });

  it('Восприятие — производная от Внимательность и Реакция', () => {
    const views = buildEditorStatViews(editorCharacteristics, ruleCatalog);
    const perception = views.find((view) => view.characteristic.code === 'perception');
    expect(perception?.derived).toBe(true);
    expect(perception?.bases.map((base) => base.code)).toEqual(['attention', 'reaction']);
  });

  it('Интеллект — производная от Память и Мышление', () => {
    const views = buildEditorStatViews(editorCharacteristics, ruleCatalog);
    const intellect = views.find((view) => view.characteristic.code === 'intellect');
    expect(intellect?.derived).toBe(true);
    expect(intellect?.bases.map((base) => base.code)).toEqual(['memory', 'reasoning']);
  });

  it('базовые характеристики не производные', () => {
    const views = buildEditorStatViews(editorCharacteristics, ruleCatalog);
    const strength = views.find((view) => view.characteristic.code === 'strength');
    expect(strength?.derived).toBe(false);
    expect(strength?.bases).toEqual([]);
  });
});
