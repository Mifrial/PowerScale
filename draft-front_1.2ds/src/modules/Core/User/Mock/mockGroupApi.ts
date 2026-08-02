import type { IGroupApi } from '../Interface/IGroupApi'
import * as mock from './mockGroups'

export const mockGroupApi: IGroupApi = {
  getGroups: mock.fetchGroups,
  getGroup: mock.fetchGroup,
  createGroup: mock.createGroup,
  updateGroup: mock.updateGroup,
  deactivateGroup: mock.deactivateGroup,
}
