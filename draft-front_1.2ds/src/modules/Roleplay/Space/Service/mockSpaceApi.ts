import type { ISpaceApi } from '../Interface/ISpaceApi'
import * as mock from './mockSpaces'

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
}
