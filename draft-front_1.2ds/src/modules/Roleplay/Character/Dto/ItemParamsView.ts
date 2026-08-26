import type { DefenseLineView } from '@/modules/Roleplay/Character/Dto/DefenseLineView';

export interface ItemParamsView {
  weightLabel: string | null;
  /** Оружие/щит. */
  minStrengthLabel: string | null;
  durabilityLabel: string | null;
  blockDefenseLabel: string | null;
  blockEfficiencyLabel: string | null;
  /** Щит: «Макс. Ловкость/Реакция: 5 (Сила)». */
  characteristicLimitsLabel: string | null;
  /** Сопротивления (блокирования щита / доспеха). */
  resistanceLabels: string[];
  /** Доспех. */
  maxAgilityLabel: string | null;
  strengthPenaltyLabel: string | null;
  /** Слои защиты доспеха. */
  defenseLines: DefenseLineView[];
}
