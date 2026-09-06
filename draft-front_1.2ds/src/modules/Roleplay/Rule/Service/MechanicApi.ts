import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IMechanicApi } from '@/modules/Roleplay/Rule/Interface/IMechanicApi';
import type { CreateMechanicData } from '@/modules/Roleplay/Rule/Dto/CreateMechanicData';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { UpdateMechanicData } from '@/modules/Roleplay/Rule/Dto/UpdateMechanicData';

/**
 * Транспорт справочника механик.
 */
export class MechanicApi implements IMechanicApi {
  constructor(private readonly engine: Engine) {}

  async getMechanics(signal?: AbortSignal): Promise<Mechanic[]> {
    const res = await this.engine.runAction<Mechanic[]>('mechanic.getList', undefined, signal);

    return res.data ?? [];
  }

  async getMechanic(id: number, signal?: AbortSignal): Promise<Mechanic> {
    const res = await this.engine.runAction<Mechanic>('mechanic.get', { id }, signal);
    if (!res.data) throw new Error('Механика не найдена');

    return res.data;
  }

  async createMechanic(data: CreateMechanicData, signal?: AbortSignal): Promise<Mechanic> {
    const res = await this.engine.runAction<Mechanic>(
      'mechanic.create',
      { code: data.code, name: data.name, version: data.version, description: data.description ?? '' },
      signal,
    );
    if (!res.data) throw new Error('Не удалось создать механику');

    return res.data;
  }

  async updateMechanic(id: number, data: UpdateMechanicData, signal?: AbortSignal): Promise<Mechanic> {
    const res = await this.engine.runAction<Mechanic>('mechanic.update', { id, ...data }, signal);
    if (!res.data) throw new Error('Не удалось обновить механику');

    return res.data;
  }
}
