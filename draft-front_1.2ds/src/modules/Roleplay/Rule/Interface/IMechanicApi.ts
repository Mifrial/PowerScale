import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';

export interface IMechanicApi {
  getMechanics(signal?: AbortSignal): Promise<Mechanic[]>;
}
