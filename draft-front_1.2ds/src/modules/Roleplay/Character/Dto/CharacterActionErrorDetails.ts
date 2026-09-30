import type { CharacterValidationProblem } from '@/modules/Roleplay/Character/Dto/CharacterValidationProblem';

/** Typed details, которые backend/mock может вернуть вместе с ActionError. */
export type CharacterActionErrorDetails =
  | { kind: 'validation'; problems: CharacterValidationProblem[] }
  | {
      kind: 'conflict';
      expectedActualVersion: number;
      actualVersion: number;
      commandId?: string;
    };
