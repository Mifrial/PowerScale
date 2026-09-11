import { describe, expect, it } from 'vitest';
import { SpellParamsViewService } from '@/modules/Roleplay/Character/Service/SpellParamsViewService';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';

const service = new SpellParamsViewService();

const discharge: Extract<AbilitySpec, { type: 'spell' }> = {
  type: 'spell',
  zones: { or: { kind: 'array', levels_cost: [1] } },
  requirements: [],
  grants: [],
  parent_ability_code: null,
  action_components: [
    { type: 'resource', resource_code: 'action-points', amount: 4, label: 'Сотворение' },
    { type: 'somatic', note: 'Касание цели' },
  ],
  hit_resolution: { type: 'attack' },
  spell: {
    power: { type: 'parameter', parameter_code: 'x' },
    control: { base: 3, size: -1 },
    duration: { type: 'instant' },
  },
};

describe('SpellParamsViewService', () => {
  it('собирает две строки параметров и невременные компоненты', () => {
    const view = service.view(discharge);
    expect(view.powerLabel).toBe('x↑');
    expect(view.controlLabel).toBe('3↓');
    expect(view.creationLabel).toBe('4 ОД');
    expect(view.durationLabel).toBe('мгновенно');
    expect(view.componentsLabel).toBe('Касание цели');
  });

  it('поддерживаемое действие показывает мощь', () => {
    const view = service.view({
      ...discharge,
      action_components: [{ type: 'resource', resource_code: 'action-points', amount: 4, label: 'Сотворение' }],
      hit_resolution: { type: 'none' },
      spell: {
        power: { base: 4, size: -1 },
        control: { base: 5, size: -1 },
        duration: { type: 'sustained', power: { type: 'parameter', parameter_code: 'x' } },
      },
    });
    expect(view.durationLabel).toBe('Поддержание(Мощь: x)');
    expect(view.powerLabel).toBe('4↓');
  });

  it('без соматики не показывает строку компонентов', () => {
    const view = service.view({
      ...discharge,
      action_components: [{ type: 'resource', resource_code: 'action-points', amount: 4, label: 'Сотворение' }],
    });
    expect(view.componentsLabel).toBeNull();
  });
});
