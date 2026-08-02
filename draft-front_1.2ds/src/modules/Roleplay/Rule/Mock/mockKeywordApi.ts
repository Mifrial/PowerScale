import type { IKeywordApi } from '../Interface/IKeywordApi'
import * as mock from './mockKeywords'

export const mockKeywordApi: IKeywordApi = {
  getTags: mock.fetchTags,
  getTag: mock.fetchTag,
  createTag: mock.createTag,
  updateTag: mock.updateTag,
  deactivateTag: mock.deactivateTag,
}
