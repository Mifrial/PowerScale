import type { PasswordPolicy } from '../Interface/types'

const DEFAULT_POLICY: PasswordPolicy = {
  minLength: 4,
  requireMixedCase: false,
  requireDigit: false,
  requireSpecialChar: false,
}

export function validatePassword(password: string, policy: PasswordPolicy = DEFAULT_POLICY): string[] {
  const errors: string[] = []
  if (password.length < policy.minLength) {
    errors.push(`Минимальная длина — ${policy.minLength} символа`)
  }
  if (policy.requireMixedCase && !/[a-z]/.test(password)) {
    errors.push('Требуется строчная буква')
  }
  if (policy.requireMixedCase && !/[A-Z]/.test(password)) {
    errors.push('Требуется заглавная буква')
  }
  if (policy.requireDigit && !/\d/.test(password)) {
    errors.push('Требуется цифра')
  }
  if (policy.requireSpecialChar && !/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
    errors.push('Требуется спецсимвол')
  }
  return errors
}
