import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';
import { DEFAULT_PASSWORD_POLICY } from '@/modules/Core/Auth/Constant/defaultPasswordPolicy';

export class PasswordValidatorService {
  constructor(private readonly defaultPolicy: PasswordPolicy = DEFAULT_PASSWORD_POLICY) {}

  validate(password: string, policy: PasswordPolicy = this.defaultPolicy): string[] {
    const errors: string[] = [];
    if (password.length < policy.minLength) {
      errors.push(`Минимальная длина — ${policy.minLength} символа`);
    }
    if (policy.requireMixedCase && !/[a-z]/.test(password)) {
      errors.push('Требуется строчная буква');
    }
    if (policy.requireMixedCase && !/[A-Z]/.test(password)) {
      errors.push('Требуется заглавная буква');
    }
    if (policy.requireDigit && !/\d/.test(password)) {
      errors.push('Требуется цифра');
    }
    if (policy.requireSpecialChar && !/[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]/.test(password)) {
      errors.push('Требуется спецсимвол');
    }

    return errors;
  }
}
