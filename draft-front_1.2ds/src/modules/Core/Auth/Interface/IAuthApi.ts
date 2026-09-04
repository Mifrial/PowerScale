import type { User } from '@/modules/Core/User/Dto/User';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';
import type { PasswordResetStartResult } from '@/modules/Core/Auth/Dto/PasswordResetStartResult';
import type { CurrentSession } from '@/modules/Core/Auth/Dto/CurrentSession';

export interface IAuthApi {
  login(loginOrEmail: string, password: string, remember?: boolean): Promise<User>;
  register(login: string, email: string, password: string): Promise<User>;
  logout(): Promise<void>;
  guest(): Promise<void>;
  getCurrentUser(): Promise<CurrentSession | null>;
  startPasswordReset(loginOrEmail: string): Promise<PasswordResetStartResult>;
  finalPasswordReset(login: string, resetToken: string, newPassword: string): Promise<boolean>;
  setPassword(userId: number, newPassword: string, currentPassword?: string): Promise<boolean>;
  getPasswordPolicy(userId?: number): Promise<PasswordPolicy>;
}
