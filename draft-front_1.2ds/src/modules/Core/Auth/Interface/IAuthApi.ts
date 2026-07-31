import type { User } from '@/modules/Core/User/Interface/types'
import type { PasswordPolicy } from './types'

export interface IAuthApi {
  login(loginOrEmail: string, password: string): Promise<User>
  register(login: string, email: string, password: string): Promise<User>
  logout(): Promise<void>
  getCurrentUser(): Promise<User | null>
  findUser(loginOrEmail: string): Promise<User | null>
  resetPassword(login: string, token: string, newPassword: string): Promise<boolean>
  getPasswordPolicy(): Promise<PasswordPolicy>
}
