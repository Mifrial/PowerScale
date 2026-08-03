import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro';
import type { MacroRollSpec } from '@/modules/Roleplay/Game/Dto/MacroRollSpec';

export interface CreateMacroData {
  name: string;
  textTemplate: string;
  rolls: MacroRollSpec[];
}

export interface UpdateMacroData {
  name?: string;
  textTemplate?: string;
  rolls?: MacroRollSpec[];
}

export interface IMacroApi {
  getMyMacros(signal?: AbortSignal): Promise<UserMacro[]>;
  createMacro(data: CreateMacroData, signal?: AbortSignal): Promise<UserMacro>;
  updateMacro(id: number, data: UpdateMacroData, signal?: AbortSignal): Promise<UserMacro>;
  deleteMacro(id: number, signal?: AbortSignal): Promise<void>;
}
