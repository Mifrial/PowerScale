import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Карточка инициативы и запасная характеристика в срезе правил клиента.
 */
export class GameInitiativeSpecService {
  /**
   * Код характеристики по умолчанию: поле карточки, иначе единственная isInitiative-характеристика.
   */
  defaultCharacteristicCode(rules: Rule[]): string {
    const check = this.initiativeCheck(rules);
    if (check === null) {
      return '';
    }

    const code = check.characteristic_code;
    if (typeof code === 'string' && code !== '') {
      return code;
    }

    const characteristics = rules.filter((rule) => {
      if (rule.type !== 'characteristic') {
        return false;
      }
      const spec = rule.spec as CharacteristicSpec | undefined;

      return spec?.initiative === true;
    });

    return characteristics.length === 1 ? characteristics[0].code : '';
  }

  /** Карточка разрешает сменить характеристику. */
  isCharacteristicOverrideAllowed(rules: Rule[]): boolean {
    const check = this.initiativeCheck(rules);

    return check?.allow_characteristic_override === true;
  }

  private initiativeCheck(rules: Rule[]): CheckSpec | null {
    const checks = rules.filter((rule) => rule.type === 'check');
    const specs = checks
      .map((rule) => rule.spec as CheckSpec | undefined)
      .filter((spec): spec is CheckSpec => spec !== undefined && spec.initiative === true);

    return specs.length === 1 ? specs[0] : null;
  }
}
