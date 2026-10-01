import type { MechanicBinding } from '@/modules/Roleplay/Mechanic/Dto/MechanicBinding';
import type { RuleMechanicRef } from '@/modules/Roleplay/Rule/Dto/RuleMechanicRef';

/** Разворачивает список механик правил в привязки движка. */
export class MechanicBindingList {
  static fromRules(rules: { code: string; mechanics: RuleMechanicRef[] }[]): MechanicBinding[] {
    return rules.flatMap((rule) =>
      rule.mechanics.map((row) => ({
        ruleCode: rule.code,
        mechanicId: row.mechanicId,
        mechanicPayload: row.mechanicPayload,
      })),
    );
  }
}
