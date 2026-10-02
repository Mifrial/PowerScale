import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { asActionAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';

/** Переключение лежачего положения и снятие неустойчивости. */
export class PostureStateService {
  hasLying(version: CharacterVersion, rules: Rule[]): boolean {
    const code = attackDamageService.lyingRule(rules)?.code;
    if (!code) return false;

    return version.states.some((state) => state.stateRuleCode === code);
  }

  shouldToggleLying(operations: { type: string }[] | undefined): boolean {
    return operations?.some((operation) => operation.type === 'posture') ?? false;
  }

  isRecoverStability(rule: Rule): boolean {
    return asActionAbilitySpec(rule)?.combat_action === 'recover-stability';
  }

  nextAfterStandUp(version: CharacterVersion, rules: Rule[]): { addCode: string | null; removeCodes: string[] } {
    const lyingCode = attackDamageService.lyingRule(rules)?.code ?? null;
    const unstableCode = attackDamageService.unstableRule(rules)?.code ?? null;
    if (this.hasLying(version, rules)) {
      return { addCode: null, removeCodes: lyingCode ? [lyingCode] : [] };
    }

    return { addCode: lyingCode, removeCodes: unstableCode ? [unstableCode] : [] };
  }
}
