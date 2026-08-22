import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';

/**
 * Инстанс чувства на персонаже: значение — модификатор к Внимательности (число),
 * агрегированный из грантов `sense_modify` по общему правилу источников (макс+ / макс− у одного).
 * `modifiers` — вклады с источниками (для трассировки).
 */
export interface CharacterSenseValue {
  ruleId: string;
  value: number;
  modifiers: CharacteristicModifier[];
}
