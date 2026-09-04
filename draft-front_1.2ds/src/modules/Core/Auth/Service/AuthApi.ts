import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { User } from '@/modules/Core/User/Dto/User';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';
import type { PasswordResetStartResult } from '@/modules/Core/Auth/Dto/PasswordResetStartResult';
import type { CurrentSession } from '@/modules/Core/Auth/Dto/CurrentSession';

export class AuthApi implements IAuthApi {
  constructor(private readonly engine: Engine) {}

  async login(loginOrEmail: string, password: string, remember = false) {
    const res = await this.engine.runAction<{ user: User }>('auth.login', { loginOrEmail, password, remember });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Login failed');

    return res.data.user;
  }

  async register(login: string, email: string, password: string) {
    const res = await this.engine.runAction<{ user: User }>('auth.register', { login, email, password });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Register failed');

    return res.data.user;
  }

  async logout() {
    const res = await this.engine.runAction('auth.logout');
    if (!res.success) throw new Error(res.error?.message ?? 'Logout failed');
  }

  async guest() {
    const res = await this.engine.runAction<{ kind: 'guest' }>('auth.guest');
    if (!res.success) throw new Error(res.error?.message ?? 'Guest login failed');
  }

  async getCurrentUser() {
    const res = await this.engine.runAction<CurrentSession>('auth.getCurrentUser');
    if (!res.success || !res.data) return null;
    if (res.data.kind === 'guest') return { kind: 'guest' as const };
    if (res.data.kind === 'user' && res.data.user) return res.data;

    return null;
  }

  async startPasswordReset(loginOrEmail: string) {
    const res = await this.engine.runAction<PasswordResetStartResult>('auth.startPasswordReset', { loginOrEmail });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to start password reset');

    return res.data;
  }

  async finalPasswordReset(login: string, resetToken: string, newPassword: string) {
    const res = await this.engine.runAction<boolean>('auth.finalPasswordReset', { login, resetToken, newPassword });
    if (!res.success) throw new Error(res.error?.message ?? 'Failed to reset password');

    return !!res.data;
  }

  async setPassword(userId: number, newPassword: string, currentPassword?: string) {
    const res = await this.engine.runAction<boolean>('auth.setPassword', {
      userId,
      newPassword,
      currentPassword,
    });
    if (!res.success) throw new Error(res.error?.message ?? 'Failed to set password');

    return !!res.data;
  }

  async getPasswordPolicy(userId?: number) {
    const res = await this.engine.runAction<PasswordPolicy>(
      'auth.getPasswordPolicy',
      userId === undefined ? {} : { userId },
    );
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to get password policy');

    return res.data;
  }
}
