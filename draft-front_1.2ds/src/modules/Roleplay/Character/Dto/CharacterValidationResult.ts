import type { CharacterValidationProblem } from '@/modules/Roleplay/Character/Dto/CharacterValidationProblem';

/** Результат validate-only без изменения actual. */
export interface CharacterValidationResult {
  valid: boolean;
  problems: CharacterValidationProblem[];
}
