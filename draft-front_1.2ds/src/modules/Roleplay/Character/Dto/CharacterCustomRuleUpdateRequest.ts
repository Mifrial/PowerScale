import type { UpdateCustomRuleData } from '@/modules/Roleplay/Character/Dto/UpdateCustomRuleData';

/** Команда изменения custom rule с общим optimistic/idempotency guard. */
export interface CharacterCustomRuleUpdateRequest extends UpdateCustomRuleData {
  commandId: string;
  expectedActualVersion: number;
}
