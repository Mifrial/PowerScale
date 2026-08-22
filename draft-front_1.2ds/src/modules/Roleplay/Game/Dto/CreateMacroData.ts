import type { MacroRollSpec } from '@/modules/Roleplay/Game/Dto/MacroRollSpec';

export interface CreateMacroData {
  name: string;
  textTemplate: string;
  rolls: MacroRollSpec[];
}
