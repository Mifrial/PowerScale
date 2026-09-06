import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { CreateMechanicData } from '@/modules/Roleplay/Rule/Dto/CreateMechanicData';
import type { UpdateMechanicData } from '@/modules/Roleplay/Rule/Dto/UpdateMechanicData';

export interface IMechanicApi {
  getMechanics(signal?: AbortSignal): Promise<Mechanic[]>;
  getMechanic(id: number, signal?: AbortSignal): Promise<Mechanic>;
  createMechanic(data: CreateMechanicData, signal?: AbortSignal): Promise<Mechanic>;
  updateMechanic(id: number, data: UpdateMechanicData, signal?: AbortSignal): Promise<Mechanic>;
}
