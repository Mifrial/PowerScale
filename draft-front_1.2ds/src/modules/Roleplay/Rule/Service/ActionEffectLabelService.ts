import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import { DAMAGE_TYPE_FORMS } from '@/modules/Roleplay/Rule/Constant/DAMAGE_TYPE_FORMS';

export class ActionEffectLabelService {
  describe(effect: ActionEffect, sourceLabel?: string): string {
    const body = this.body(effect);
    const source = sourceLabel ?? this.defaultSource(effect);

    return source ? `${body} (${source})` : body;
  }

  private body(effect: ActionEffect): string {
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
      const hands = effect.min_occupy_hands === undefined ? '' : `, если оружие в ${effect.min_occupy_hands}+ руках`;
      const types =
        effect.damage_type_codes && effect.damage_type_codes.length > 0
          ? ` (${effect.damage_type_codes.map((code) => this.damageTypeLabel(code)).join(', ')})`
          : '';

      return `${effect.delta > 0 ? '+' : ''}${effect.delta} к силе текущего ${hitCount}${hands}${types}`;
    }
    if (effect.type === 'current_action_attack_characteristic_from_success_rating') {
      return `+⌊РУ/${effect.floor_div}⌋ к силе удара от действия, вплоть до +${effect.cap}`;
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
    if (effect.type === 'apply_state') {
      return typeof effect.amount === 'number'
        ? `накладывает состояние «${effect.state_code}» (${effect.amount})`
        : `накладывает состояние «${effect.state_code}»`;
    }
    if (effect.type === 'optional_after_strike_check') {
      const skip = effect.skip_parent_pending ? ', без помехи действия' : '';
      const internal = effect.self_damage.internal ? 'внутреннего ' : '';

      return `после удара проверка ${this.checkName(effect.check_code)} против ${effect.difficulty}: успех — ${internal}${this.damageTypeLabel(effect.self_damage.damage_type_code)} [сила удара]${effect.self_damage.size_delta < 0 ? '↓' : ''}${skip}`;
    }

    return `${this.deltaLabel(effect.delta)} к ${effect.check_codes.map((code) => this.checkLabel(code)).join(', ')} до траты ${effect.amount} ${this.resourceLabel(effect.resource_code)}`;
  }

  private defaultSource(effect: ActionEffect): string | null {
    if (effect.type === 'apply_state') return null;
    if (effect.type === 'optional_after_strike_check') return 'опция';
    if (
      effect.type === 'current_action_check_modifier' ||
      effect.type === 'after_action_until_resource_spent_check_modifier'
    ) {
      return 'обстоятельства';
    }

    return 'действие';
  }

  private damageTypeLabel(code: string): string {
    return DAMAGE_TYPE_FORMS[code]?.genitive ?? code;
  }

  private deltaLabel(delta: number): string {
    return delta < 0 ? `${Math.abs(delta)} помехи` : `${delta} преимущества`;
  }

  private checkName(code: string): string {
    return { 'check-willpower': 'Волю', 'check-hit': 'попадание' }[code] ?? code;
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
