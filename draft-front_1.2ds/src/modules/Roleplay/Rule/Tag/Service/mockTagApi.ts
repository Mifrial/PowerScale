import type { ITagApi } from '../Interface/ITagApi'
import * as mock from './mockTags'

export const mockTagApi: ITagApi = {
  getTags: mock.fetchTags,
  getTag: mock.fetchTag,
  createTag: mock.createTag,
  updateTag: mock.updateTag,
  deactivateTag: mock.deactivateTag,
}
