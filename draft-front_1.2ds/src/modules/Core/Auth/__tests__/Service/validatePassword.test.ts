import { describe, it, expect } from 'vitest';
import { passwordValidatorService } from '@/modules/Core/Auth/Service/PasswordValidatorService';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';

const policy: PasswordPolicy = {
  minLength: 4,
  requireMixedCase: true,
  requireDigit: true,
  requireSpecialChar: true,
};

describe('validatePassword', () => {
  it('короткий пароль → ошибка minLength', () => {
    expect(passwordValidatorService.validate('ab', policy)).toContain('Минимальная длина — 4 символа');
  });

  it('нет строчной буквы → ошибка', () => {
    expect(passwordValidatorService.validate('ABC1!', policy)).toContain('Требуется строчная буква');
  });

  it('нет заглавной буквы → ошибка', () => {
    expect(passwordValidatorService.validate('abc1!', policy)).toContain('Требуется заглавная буква');
  });

  it('нет цифры → ошибка', () => {
    expect(passwordValidatorService.validate('Abc!', policy)).toContain('Требуется цифра');
  });

  it('нет спецсимвола → ошибка', () => {
    expect(passwordValidatorService.validate('Abc1', policy)).toContain('Требуется спецсимвол');
  });

  it('валидный пароль → пустой список', () => {
    expect(passwordValidatorService.validate('Abc1!')).toEqual([]);
  });

  it('дефолтная политика: только minLength', () => {
    expect(passwordValidatorService.validate('ab')).toContain('Минимальная длина — 4 символа');
    expect(passwordValidatorService.validate('abcd')).toEqual([]);
    expect(passwordValidatorService.validate('абвг')).toEqual([]);
  });

  it('может собрать несколько ошибок сразу', () => {
    const errors = passwordValidatorService.validate('a', policy);
    expect(errors.length).toBeGreaterThanOrEqual(3);
  });
});
