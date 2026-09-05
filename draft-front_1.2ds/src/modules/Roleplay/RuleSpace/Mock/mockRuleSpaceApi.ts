import type { IRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Interface/IRuleSpaceApi';
import * as mock from '@/modules/Roleplay/RuleSpace/Mock/mockSpaces';

export const mockRuleSpaceApi: IRuleSpaceApi = {
  getSpaces: mock.fetchSpaces,
  getSpace: mock.fetchSpace,
  getSpaceByCode: mock.fetchSpaceByCode,
  createSpace: mock.createSpace,
  updateSpace: mock.updateSpace,
  deactivateSpace: mock.deactivateSpace,
  getRevisions: mock.fetchRevisions,
  getRevision: mock.fetchRevision,
  commitDraft: mock.commitDraft,
};
