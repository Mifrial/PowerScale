import type { ProcessFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessFormula';

/** Пакет удара шага процесса или карточки-потомка. */
export interface StrikeModifier {
  advantage?: ProcessFormula;
  extra_successes?: ProcessFormula;
}
