import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro';
import type { CreateMacroData } from '@/modules/Roleplay/Game/Dto/CreateMacroData';
import type { UpdateMacroData } from '@/modules/Roleplay/Game/Dto/UpdateMacroData';

export interface IMacroApi {
  getMyMacros(signal?: AbortSignal): Promise<UserMacro[]>;
  createMacro(data: CreateMacroData, signal?: AbortSignal): Promise<UserMacro>;
  updateMacro(id: number, data: UpdateMacroData, signal?: AbortSignal): Promise<UserMacro>;
  deleteMacro(id: number, signal?: AbortSignal): Promise<void>;
}
