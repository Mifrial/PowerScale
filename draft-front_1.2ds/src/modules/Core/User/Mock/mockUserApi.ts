import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi';
import * as mock from '@/modules/Core/User/Mock/mockUsers';

export const mockUserApi: IUserApi = {
  findPage: mock.mockFindPage,
  getUser: mock.mockGetUser,
  getUsersByIds: mock.mockGetUsersByIds,
  createUser: mock.mockCreateUser,
  updateUser: mock.mockUpdateUser,
  deactivateUser: mock.mockDeactivateUser,
};
