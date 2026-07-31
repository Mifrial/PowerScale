import type { Source } from './types'

export interface ISourceApi {
  getSources(signal?: AbortSignal): Promise<Source[]>
}
