import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IMechanicApi } from '@/modules/Roleplay/Rule/Interface/IMechanicApi';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

/**
 * Транспорт справочника механик.
 */
export class MechanicApi implements IMechanicApi {
  constructor(private readonly engine: Engine) {}

  async getMechanics(signal?: AbortSignal): Promise<Mechanic[]> {
    const res = await this.engine.runAction<Mechanic[]>('mechanic.getList', undefined, signal);

    return res.data ?? [];
  }
}
