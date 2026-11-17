import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { GameActiveEffect } from '@/modules/Roleplay/Game/Dto/GameActiveEffect';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Подписи наложенных длительных эффектов для боевой карточки. */
export class ActiveEffectService {
  ofTarget(effects: GameActiveEffect[], targetKey: CombatEntityKey): GameActiveEffect[] {
    return effects.filter((effect) => effect.targetKey === targetKey);
  }

  labelOf(effect: GameActiveEffect, rules: Rule[]): string {
    return `${this.nameOf(effect.sourceRuleCode, rules)} (осталось ходов: ${effect.turnsLeft})`;
  }

  private nameOf(ruleCode: string, rules: Rule[]): string {
    return rules.find((rule) => rule.code === ruleCode)?.name ?? ruleCode;
  }
}
