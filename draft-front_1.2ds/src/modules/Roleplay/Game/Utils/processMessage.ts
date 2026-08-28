import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { actionEffectLabelService } from '@/modules/Roleplay/Rule/Service/Instance/actionEffectLabelService';

export function formatProcessEffect(effect: ActionEffect, rules: Rule[]): string {
  if (effect.type !== 'after_action_until_resource_spent_check_modifier') {
    return actionEffectLabelService.describe(effect);
  }

  const checks = effect.check_codes
    .map((code) => {
      const rule = rules.find((entry) => entry.code === code);

      return rule ? `[[rule:${rule.code}]]` : code;
    })
    .join(', ');
  const resource = rules.find((rule) => rule.code === effect.resource_code);
  const resourceLabel = resource ? `[[rule:${resource.code}]]` : effect.resource_code;

  return `${effect.delta > 0 ? '+' : ''}${effect.delta} к проверке ${checks} до траты ${effect.amount} ${resourceLabel}`;
}
