import type { AddCustomRuleData } from '@/modules/Roleplay/Character/Dto/AddCustomRuleData';

/** Команда выдачи custom rule с общим optimistic/idempotency guard. */
export interface CharacterCustomRuleCreateRequest extends AddCustomRuleData {
  commandId: string;
  expectedActualVersion: number;
}
