import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Карточка инициативы в срезе правил клиента: единственная проверка с spec.initiative.
 */
export class GameInitiativeSpecService {
  /** Код характеристики с единственной карточки инициативы. */
  defaultCharacteristicCode(rules: Rule[]): string {
    const check = this.initiativeCheck(rules);
    const code = check?.characteristic_code;

    return typeof code === 'string' ? code : '';
  }

  /** Код единственной проверки с признаком инициативы. */
  initiativeCheckCode(rules: Rule[]): string {
    return this.initiativeCheckRule(rules)?.code ?? '';
  }

  /** Карточка разрешает сменить характеристику. */
  isCharacteristicOverrideAllowed(rules: Rule[]): boolean {
    return this.initiativeCheck(rules)?.allow_characteristic_override === true;
  }

  private initiativeCheck(rules: Rule[]): CheckSpec | null {
    const spec = this.initiativeCheckRule(rules)?.spec;

    return spec && spec.type === 'check' ? spec : null;
  }

  private initiativeCheckRule(rules: Rule[]): Rule | null {
    const matched = rules.filter((rule) => {
      if (rule.type !== 'check') {
        return false;
      }
      const spec = rule.spec as CheckSpec | undefined;

      return spec?.initiative === true;
    });

    return matched.length === 1 ? matched[0] : null;
  }
}
