import type { IUserApi } from '../Interface/IUserApi'
import * as mock from './mockUsers'

export const mockUserApi: IUserApi = {
  getUsers: mock.mockGetUsers,
  getUser: mock.mockGetUser,
  getUsersByIds: mock.mockGetUsersByIds,
  createUser: mock.mockCreateUser,
  updateUser: mock.mockUpdateUser,
  deactivateUser: mock.mockDeactivateUser,
}
