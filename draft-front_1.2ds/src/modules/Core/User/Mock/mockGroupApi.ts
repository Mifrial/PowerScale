import type { IGroupApi } from '@/modules/Core/User/Interface/IGroupApi';
import * as mock from '@/modules/Core/User/Mock/mockGroups';

export const mockGroupApi: IGroupApi = {
  getGroups: mock.fetchGroups,
  getGroup: mock.fetchGroup,
  createGroup: mock.createGroup,
  updateGroup: mock.updateGroup,
  deactivateGroup: mock.deactivateGroup,
};
