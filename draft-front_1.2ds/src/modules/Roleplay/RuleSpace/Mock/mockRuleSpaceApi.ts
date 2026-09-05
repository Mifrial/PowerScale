import type { ISpaceApi } from '@/modules/Roleplay/Space/Interface/ISpaceApi';
import * as mock from '@/modules/Roleplay/Space/Mock/mockSpaces';

export const mockSpaceApi: ISpaceApi = {
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
