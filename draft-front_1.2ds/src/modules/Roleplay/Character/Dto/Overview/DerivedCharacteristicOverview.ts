import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';

export interface DerivedCharacteristicOverview {
  formula: string;
  /** Человекочитаемая подпись формулы, например «Минимальная из Внимательности и Реакции». */
  label: string | null;
  bases: CharacteristicOverview[];
}
