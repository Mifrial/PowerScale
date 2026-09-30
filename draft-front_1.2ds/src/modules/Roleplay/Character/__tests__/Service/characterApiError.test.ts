import { describe, expect, it } from 'vitest';
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';

describe('CharacterApiError', () => {
  it('сохраняет typed validation details из ActionError', () => {
    const error = CharacterApiError.fromActionError({
      code: 'CHARACTER_VALIDATION_FAILED',
      message: 'Invalid character',
      details: {
        kind: 'validation',
        problems: [{ code: 'NAME_REQUIRED', message: 'Name is required', path: 'name' }],
      },
    });

    expect(error.code).toBe('CHARACTER_VALIDATION_FAILED');
    expect(error.details).toEqual({
      kind: 'validation',
      problems: [{ code: 'NAME_REQUIRED', message: 'Name is required', path: 'name' }],
    });
  });

  it('сохраняет typed conflict details из ActionError', () => {
    const error = CharacterApiError.fromActionError({
      code: 'CHARACTER_ACTUAL_CONFLICT',
      message: 'Conflict',
      details: {
        kind: 'conflict',
        expectedActualVersion: 2,
        actualVersion: 3,
        commandId: 'command-1',
      },
    });

    expect(error.details).toEqual({
      kind: 'conflict',
      expectedActualVersion: 2,
      actualVersion: 3,
      commandId: 'command-1',
    });
  });

  it('отбрасывает validation details с неправильной формой problem', () => {
    const error = CharacterApiError.fromActionError({
      code: 'CHARACTER_VALIDATION_FAILED',
      message: 'Invalid character',
      details: {
        kind: 'validation',
        problems: [{ code: 'NAME_REQUIRED' }],
      },
    });

    expect(error.details).toBeNull();
  });
});
