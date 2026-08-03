import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi';
import {
  mockLogin,
  mockRegister,
  mockLogout,
  mockGetCurrentUser,
  mockFindUser,
  mockResetPassword,
  mockGetPasswordPolicy,
} from '@/modules/Core/Auth/Mock/mockAuth';

export const mockAuthApi: IAuthApi = {
  login: mockLogin,
  register: mockRegister,
  logout: mockLogout,
  getCurrentUser: mockGetCurrentUser,
  findUser: mockFindUser,
  resetPassword: mockResetPassword,
  getPasswordPolicy: mockGetPasswordPolicy,
};
