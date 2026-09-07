import type { IKeywordApi } from '@/modules/Roleplay/Keyword/Interface/IKeywordApi';
import * as mock from '@/modules/Roleplay/Keyword/Mock/mockKeywords';

export const mockKeywordApi: IKeywordApi = {
  getKeywords: mock.fetchTags,
  getKeyword: mock.fetchTag,
  createKeyword: mock.createTag,
  updateKeyword: mock.updateTag,
  deactivate: mock.deactivateTag,
};
