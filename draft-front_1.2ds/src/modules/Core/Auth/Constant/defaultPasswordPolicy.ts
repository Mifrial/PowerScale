import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';

export const DEFAULT_PASSWORD_POLICY: PasswordPolicy = {
  minLength: 4,
  requireMixedCase: false,
  requireDigit: false,
  requireSpecialChar: false,
};
