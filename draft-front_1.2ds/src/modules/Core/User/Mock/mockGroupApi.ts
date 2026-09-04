import type { IGroupApi } from '@/modules/Core/User/Interface/IGroupApi';
import * as mock from '@/modules/Core/User/Mock/mockGroups';

export const mockGroupApi: IGroupApi = {
  findPage: mock.mockFindPage,
  getGroup: mock.fetchGroup,
  getGroupMembers: mock.getGroupMembers,
  createGroup: mock.createGroup,
  updateGroup: mock.updateGroup,
  deactivateGroup: mock.deactivateGroup,
};
