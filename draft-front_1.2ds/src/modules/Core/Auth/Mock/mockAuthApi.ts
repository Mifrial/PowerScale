import type { IAuthApi } from '../Interface/IAuthApi'
import { mockLogin, mockRegister, mockLogout, mockGetCurrentUser, mockFindUser, mockResetPassword, mockGetPasswordPolicy } from './mockAuth'

export const mockAuthApi: IAuthApi = {
  login: mockLogin,
  register: mockRegister,
  logout: mockLogout,
  getCurrentUser: mockGetCurrentUser,
  findUser: mockFindUser,
  resetPassword: mockResetPassword,
  getPasswordPolicy: mockGetPasswordPolicy,
}
