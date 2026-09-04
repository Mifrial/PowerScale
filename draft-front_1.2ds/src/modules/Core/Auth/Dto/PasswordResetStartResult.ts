export interface PasswordResetStartResult {
  status: 'not_found' | 'no_email' | 'sent';
  login?: string;
  resetToken?: string;
}
