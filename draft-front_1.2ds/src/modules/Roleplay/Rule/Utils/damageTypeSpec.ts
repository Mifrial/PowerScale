import type { DamageTypeSpec } from '@/modules/Roleplay/Rule/Dto/Damage/DamageTypeSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { DAMAGE_TYPE_FORMS, type DamageTypeForms } from '@/modules/Roleplay/Character/Constant/DAMAGE_TYPE_FORMS';

export function asDamageTypeSpec(rule: Rule | undefined): DamageTypeSpec | null {
  if (!rule || rule.type !== 'damage_type') return null;
  const spec = rule.spec;
  if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'damage_type') return null;

  return spec;
}

/** Карточка с механикой, не сам тип урона и не проверка. */
export function isDamageTypeAttachableRule(rule: Rule): boolean {
  if (rule.type === 'damage_type' || rule.type === 'check') return false;

  return rule.mechanicId != null;
}

export function damageTypeForms(code: string, rules: Rule[] = []): DamageTypeForms | undefined {
  const rule = rules.find((entry) => entry.code === code && entry.type === 'damage_type');
  const spec = asDamageTypeSpec(rule);
  if (spec?.forms.genitive || spec?.forms.dative) return spec.forms;

  return DAMAGE_TYPE_FORMS[code];
}
