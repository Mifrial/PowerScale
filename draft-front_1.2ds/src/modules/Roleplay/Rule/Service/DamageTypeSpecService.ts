import type { DamageTypeSpec } from '@/modules/Roleplay/Rule/Dto/Damage/DamageTypeSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import { DAMAGE_TYPE_FORMS } from '@/modules/Roleplay/Rule/Constant/DAMAGE_TYPE_FORMS';
import type { DamageTypeForms } from '@/modules/Roleplay/Rule/Dto/DamageTypeForms';

export class DamageTypeSpecService {
  createEmpty(code = '', attached: string[] = []): DamageTypeSpec {
    const forms = DAMAGE_TYPE_FORMS[code] ?? { genitive: '', dative: '' };

    return {
      type: 'damage_type',
      forms: { genitive: forms.genitive, dative: forms.dative },
      attached_rule_codes: [...attached],
      defense_ignored: false,
    };
  }

  fromRuleSpec(spec: RuleSpec | null | undefined, code: string): DamageTypeSpec {
    if (spec && typeof spec === 'object' && 'type' in spec && spec.type === 'damage_type') {
      return spec;
    }

    return this.createEmpty(code);
  }

  asDamageTypeSpec(rule: Rule | undefined): DamageTypeSpec | null {
    if (!rule || rule.type !== 'damage_type') return null;
    const spec = rule.spec;
    if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'damage_type') return null;

    return spec;
  }

  /** Карточка с механикой, не сам тип урона и не проверка. */
  isDamageTypeAttachableRule(rule: Rule): boolean {
    if (rule.type === 'damage_type' || rule.type === 'check') return false;

    return rule.mechanicId != null;
  }

  damageTypeForms(code: string, rules: Rule[] = []): DamageTypeForms | undefined {
    const rule = rules.find((entry) => entry.code === code && entry.type === 'damage_type');
    const spec = this.asDamageTypeSpec(rule);
    if (spec?.forms.genitive || spec?.forms.dative) return spec.forms;

    return DAMAGE_TYPE_FORMS[code];
  }
}
