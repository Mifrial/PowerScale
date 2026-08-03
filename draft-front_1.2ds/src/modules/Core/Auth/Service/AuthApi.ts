import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { User } from '@/modules/Core/User/Dto/User';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';

export class AuthApi implements IAuthApi {
  constructor(private readonly engine: Engine) {}

  async login(loginOrEmail: string, password: string) {
    const res = await this.engine.runAction<{ user: User }>('auth.login', { loginOrEmail, password });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Login failed');

    return res.data.user;
  }

  async register(login: string, email: string, password: string) {
    const res = await this.engine.runAction<{ user: User }>('auth.register', { login, email, password });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Register failed');

    return res.data.user;
  }

  async logout() {
    await this.engine.runAction('auth.logout');
  }

  async getCurrentUser() {
    const res = await this.engine.runAction<User>('auth.getCurrentUser');

    return res.success ? res.data : null;
  }

  async findUser(loginOrEmail: string) {
    const res = await this.engine.runAction<User>('auth.findUser', { loginOrEmail });

    return res.success ? res.data : null;
  }

  async resetPassword(login: string, resetToken: string, newPassword: string) {
    const res = await this.engine.runAction<boolean>('auth.resetPassword', { login, resetToken, newPassword });

    return res.success && !!res.data;
  }

  async getPasswordPolicy() {
    const res = await this.engine.runAction<PasswordPolicy>('auth.getPasswordPolicy');
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to get password policy');

    return res.data;
  }
}
