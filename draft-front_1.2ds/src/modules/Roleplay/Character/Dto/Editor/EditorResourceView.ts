import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { EditorResourceBonus } from '@/modules/Roleplay/Character/Dto/Editor/EditorResourceBonus';

/** Представление ресурса для попапа редактора персонажа. */
export interface EditorResourceView {
  ruleId: string;
  name: string;
  current: DimensionalNumberValue;
  base: DimensionalNumberValue;
  max: DimensionalNumberValue;
  bonuses: EditorResourceBonus[];
}
