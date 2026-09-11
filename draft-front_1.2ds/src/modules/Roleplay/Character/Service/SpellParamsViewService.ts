import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { SpellParamsView } from '@/modules/Roleplay/Character/Dto/Editor/SpellParamsView';
import { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';
import { resourceShortName, spellDurationLabelService } from '@/modules/Roleplay/Rule/init';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';

type SpellAbilitySpec = Extract<AbilitySpec, { type: 'spell' }>;

/**
 * Подписи мощи, контроля, сотворения, длительности и невременных компонентов
 * для развёрнутой строки заклинания в редакторе персонажа.
 */
export class SpellParamsViewService {
  view(spec: SpellAbilitySpec): SpellParamsView {
    return {
      powerLabel: this.spellValueLabel(spec.spell.power),
      controlLabel: this.spellValueLabel(spec.spell.control),
      creationLabel: this.creationLabel(spec.action_components),
      durationLabel: spellDurationLabelService.action(spec.spell.duration),
      componentsLabel: this.componentsLabel(spec.action_components),
    };
  }

  private spellValueLabel(value: SpellValue): string {
    if ('parameter_code' in value) return `${value.parameter_code}↑`;

    return new DimensionalNumber(value).toString();
  }

  private creationLabel(components: ActionComponent[]): string | null {
    const costs = components.filter(
      (component): component is Extract<ActionComponent, { type: 'resource' }> =>
        component.type === 'resource' && component.resource_code === ACTION_POINTS_RESOURCE_CODE,
    );
    if (costs.length === 0) return null;
    const amount = costs[0].amount;
    if (typeof amount === 'object' && 'type' in amount) return 'макс. доступное ОД';
    const formatted = typeof amount === 'number' ? String(amount) : new DimensionalNumber(amount).toString();
    const short = resourceShortName(ACTION_POINTS_RESOURCE_CODE) ?? 'ОД';

    return `${formatted} ${short}`;
  }

  private componentsLabel(components: ActionComponent[]): string | null {
    const extra = components.filter((component) => component.type !== 'resource');
    if (extra.length === 0) return null;

    return extra.map((component) => this.nonResourceLabel(component)).join(', ');
  }

  private nonResourceLabel(component: ActionComponent): string {
    if (component.type === 'verbal') return component.note?.trim() || 'Вербальный';
    if (component.type === 'somatic') {
      const note = component.note?.trim() || 'Соматический';
      if (!component.occupy_hands) return note;

      return `${note} (${component.occupy_hands === 1 ? '1 рука' : `${component.occupy_hands} руки`})`;
    }
    if (component.type !== 'material') return '';
    if (component.description?.trim()) return component.description.trim();
    if (component.item_code) return component.item_code;

    return 'Материальный';
  }
}
