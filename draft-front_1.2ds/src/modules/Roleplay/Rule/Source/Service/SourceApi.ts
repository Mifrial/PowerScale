import type { Engine } from '@/modules/Core/Engine'
import type { ISourceApi } from '../Interface/ISourceApi'
import type { Source } from '../Interface/types'

export class SourceApi implements ISourceApi {
  constructor(private engine: Engine) {}

  async getSources(signal?: AbortSignal): Promise<Source[]> {
    const res = await this.engine.runAction<Source[]>('source.getList', undefined, signal)
    return res.data ?? []
  }
}
