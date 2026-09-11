import { describe, expect, it } from 'vitest';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import { electrochargeService } from '@/modules/Roleplay/Game/Service/Instance/electrochargeService';
import { spellCastUpgradeService } from '@/modules/Roleplay/Game/Service/Instance/spellCastUpgradeService';
import { keywords } from '@/modules/Roleplay/Keyword/Mock/mockKeywords';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';

const generator = 'lightning-generator';

describe('ElectrochargeService', () => {
  it('кап 1 без улучшения и 2/3/⌊опыт/10⌋ с Накоплением', () => {
    expect(electrochargeService.cap(generator, [], ruleCatalog, keywords)).toBe(1);
    const learned: CharacterAbility[] = [{ ruleCode: 'charge-accumulation', level: 1 }];
    expect(electrochargeService.cap(generator, learned, ruleCatalog, keywords)).toBe(2);
    const withSpells: CharacterAbility[] = [
      { ruleCode: 'charge-accumulation', level: 1 },
      { ruleCode: 'discharge', level: 1 },
      { ruleCode: 'lightning-strike', level: 1 },
      { ruleCode: 'lightning-generator', level: 1 },
    ];
    const xp = 1 + 1 + 1;
    expect(xp).toBeLessThan(20);
    expect(electrochargeService.cap(generator, withSpells, ruleCatalog, keywords)).toBe(2);
  });

  it('грант не поднимает выше капа', () => {
    const spec = electrochargeService.chargeSpec(generator, ruleCatalog);
    expect(spec).toBeTruthy();
    if (!spec) {
      return;
    }
    const first = electrochargeService.grant([], 's1', spec, 1);
    expect(first.value).toBe(1);
    const second = electrochargeService.grant([first], 's1', spec, 1);
    expect(second.value).toBe(1);
    const upgraded = electrochargeService.grant([first], 's1', spec, 2);
    expect(upgraded.value).toBe(2);
  });

  it('фильтр каста отсекает поддерживаемые', () => {
    const spec = electrochargeService.chargeSpec(generator, ruleCatalog);
    expect(spec).toBeTruthy();
    if (!spec) {
      return;
    }
    const owned = [
      { ruleCode: 'discharge', name: 'Разряд', requiredPower: null, requiredControl: null },
      { ruleCode: 'lightning-strike', name: 'Удар', requiredPower: null, requiredControl: null },
      { ruleCode: 'lightning-generator', name: 'Генератор', requiredPower: null, requiredControl: null },
    ];
    const filtered = electrochargeService.filterCastOptions(owned, ruleCatalog, keywords, 6, spec);
    expect(filtered.map((option) => option.ruleCode)).toEqual(['discharge', 'lightning-strike']);
  });

  it('Накопление зарядов не чекбокс каста', () => {
    const listed = spellCastUpgradeService.listApplicable(
      [{ ruleCode: 'charge-accumulation', level: 1 }],
      'arcanist',
      generator,
      ruleCatalog,
    );
    expect(listed).toEqual([]);
  });

  it('трата уменьшает привязанный экземпляр', () => {
    const spec = electrochargeService.chargeSpec(generator, ruleCatalog);
    if (!spec) {
      return;
    }
    const states: CharacterStateValue[] = [{ stateRuleCode: 'electrocharge', value: 2, boundSustainId: 's1' }];
    const spent = electrochargeService.spend(states, 's1', spec);
    expect(spent?.value).toBe(1);
    expect(electrochargeService.spend(states, 's2', spec)).toBeNull();
  });
});
