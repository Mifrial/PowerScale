import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi'
import * as mock from '@/modules/Roleplay/Rule/Mock/mockKeywords'

export const mockKeywordApi: IKeywordApi = {
  getTags: mock.fetchTags,
  getTag: mock.fetchTag,
  createTag: mock.createTag,
  updateTag: mock.updateTag,
  deactivateTag: mock.deactivateTag,
}
