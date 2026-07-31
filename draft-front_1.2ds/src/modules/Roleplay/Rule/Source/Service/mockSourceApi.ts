import type { ISourceApi } from '../Interface/ISourceApi'
import * as mock from './mockSources'

export const mockSourceApi: ISourceApi = {
  getSources: mock.fetchSources,
}
