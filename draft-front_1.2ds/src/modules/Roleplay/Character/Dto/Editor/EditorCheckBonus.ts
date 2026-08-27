import type { EditorCheckBonusModifier } from '@/modules/Roleplay/Character/Dto/Editor/EditorCheckBonusModifier';

export interface EditorCheckBonus {
  checkCode: string;
  checkName: string;
  delta: number;
  modifiers: EditorCheckBonusModifier[];
}
