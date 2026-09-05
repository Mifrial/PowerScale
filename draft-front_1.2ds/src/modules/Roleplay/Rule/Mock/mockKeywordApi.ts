import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi';
import * as mock from '@/modules/Roleplay/Rule/Mock/mockKeywords';

export const mockKeywordApi: IKeywordApi = {
  getKeywords: mock.fetchTags,
  getKeyword: mock.fetchTag,
  createKeyword: mock.createTag,
  updateKeyword: mock.updateTag,
  deactivate: mock.deactivateTag,
};
