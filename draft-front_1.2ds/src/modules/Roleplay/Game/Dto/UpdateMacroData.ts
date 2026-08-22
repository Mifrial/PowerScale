import type { MacroRollSpec } from '@/modules/Roleplay/Game/Dto/MacroRollSpec';

export interface UpdateMacroData {
  name?: string;
  textTemplate?: string;
  rolls?: MacroRollSpec[];
}
