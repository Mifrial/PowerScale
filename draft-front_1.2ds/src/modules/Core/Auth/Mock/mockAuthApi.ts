import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi';
import {
  mockLogin,
  mockRegister,
  mockLogout,
  mockGuest,
  mockGetCurrentUser,
  mockStartPasswordReset,
  mockFinalPasswordReset,
  mockSetPassword,
  mockGetPasswordPolicy,
} from '@/modules/Core/Auth/Mock/mockAuth';

export const mockAuthApi: IAuthApi = {
  login: mockLogin,
  register: mockRegister,
  logout: mockLogout,
  guest: mockGuest,
  getCurrentUser: mockGetCurrentUser,
  startPasswordReset: mockStartPasswordReset,
  finalPasswordReset: mockFinalPasswordReset,
  setPassword: mockSetPassword,
  getPasswordPolicy: mockGetPasswordPolicy,
};
