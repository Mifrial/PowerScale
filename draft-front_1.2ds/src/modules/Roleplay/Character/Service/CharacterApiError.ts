import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';
import type { CharacterActionErrorDetails } from '@/modules/Roleplay/Character/Dto/CharacterActionErrorDetails';
import type { CharacterValidationProblem } from '@/modules/Roleplay/Character/Dto/CharacterValidationProblem';

/** Ошибка Character API с сохранённым machine code и typed validation/conflict details. */
export class CharacterApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details: CharacterActionErrorDetails | null = null,
  ) {
    super(message);
    this.name = 'CharacterApiError';
  }

  static fromActionError(error: ActionError): CharacterApiError {
    return new CharacterApiError(error.code, error.message, CharacterApiError.parseDetails(error.details));
  }

  private static parseDetails(details: unknown): CharacterActionErrorDetails | null {
    if (details === null || typeof details !== 'object') return null;
    if (!('kind' in details) || typeof details.kind !== 'string') return null;

    if (
      details.kind === 'validation' &&
      'problems' in details &&
      Array.isArray(details.problems) &&
      details.problems.every(CharacterApiError.isValidationProblem)
    ) {
      return { kind: 'validation', problems: details.problems };
    }
    if (
      details.kind === 'conflict' &&
      'expectedActualVersion' in details &&
      typeof details.expectedActualVersion === 'number' &&
      'actualVersion' in details &&
      typeof details.actualVersion === 'number'
    ) {
      return {
        kind: 'conflict',
        expectedActualVersion: details.expectedActualVersion,
        actualVersion: details.actualVersion,
        commandId: 'commandId' in details && typeof details.commandId === 'string' ? details.commandId : undefined,
      };
    }

    return null;
  }

  private static isValidationProblem(value: unknown): value is CharacterValidationProblem {
    if (value === null || typeof value !== 'object') return false;
    if (!('code' in value) || typeof value.code !== 'string') return false;
    if (!('message' in value) || typeof value.message !== 'string') return false;
    if ('path' in value && value.path !== undefined && typeof value.path !== 'string') return false;
    if ('stage' in value && value.stage !== undefined && typeof value.stage !== 'string') return false;

    return true;
  }
}
