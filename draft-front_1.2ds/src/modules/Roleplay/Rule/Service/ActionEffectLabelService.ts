import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';

export class ActionEffectLabelService {
  describe(effect: ActionEffect): string {
    if (effect.type === 'current_action_attack_accuracy') {
      return `${effect.delta > 0 ? '+' : ''}${effect.delta} к точности текущего удара`;
    }
    if (effect.type === 'current_action_attack_characteristic_modifier') {
      const hitCount =
        effect.scope.hit_count === 'all'
          ? 'всех ударов'
          : effect.scope.hit_count === 1
            ? 'удара'
            : `первых ${effect.scope.hit_count} ударов`;

      return `${effect.delta > 0 ? '+' : ''}${effect.delta} к силе текущего ${hitCount}`;
    }
    if (effect.type === 'current_action_check_modifier') {
      return `${this.deltaLabel(effect.delta)} к текущим ${effect.check_codes.map((code) => this.checkLabel(code)).join(', ')}`;
    }
    if (effect.type === 'next_action_attack_cost') {
      return `${effect.delta > 0 ? '+' : ''}${effect.delta} ОД к следующей атаке, если она будет следующим действием`;
    }
    if (effect.type === 'next_action_attack_target_characteristic_modifier') {
      const hitCount =
        effect.scope.hit_count === 'all'
          ? 'всех ударов'
          : effect.scope.hit_count === 1
            ? 'первого удара'
            : `первых ${effect.scope.hit_count} ударов`;
      const limit =
        effect.max_total_action_cost === undefined
          ? ''
          : `, если итоговая стоимость атаки не более ${effect.max_total_action_cost} ОД`;
      const characteristic = this.characteristicLabel(effect.characteristic_code);
      const floor = effect.min === undefined ? '' : `(вплоть до ${effect.min} от ${characteristic})`;

      return `${effect.delta > 0 ? '+' : ''}${effect.delta} к ${this.characteristicLabel(effect.check_code)} от ${characteristic}${floor} у цели для ${hitCount} следующей атаки${limit}`;
    }

    return `${this.deltaLabel(effect.delta)} к ${effect.check_codes.map((code) => this.checkLabel(code)).join(', ')} до траты ${effect.amount} ${this.resourceLabel(effect.resource_code)}`;
  }

  private deltaLabel(delta: number): string {
    return delta < 0 ? `${Math.abs(delta)} помехи` : `${delta} преимущества`;
  }

  private checkLabel(code: string): string {
    return { hit: 'проверкам на попадание', 'check-hit': 'проверкам на попадание' }[code] ?? `проверкам ${code}`;
  }

  private resourceLabel(code: string): string {
    return { 'action-points': 'ОД' }[code] ?? code;
  }

  private characteristicLabel(code: string): string {
    return { 'melee-combat': 'Ближнему бою', dexterity: 'Ловкости' }[code] ?? code;
  }
}
