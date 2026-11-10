import type { UnmetAbility } from '@/modules/Roleplay/Character/Service/RequirementEvaluator';

/** Уровень способности в модели редактора: выполнимость требований этого уровня. */
export interface EditorAbilityLevel {
  level: number;
  /** Требования уровня выполнены при текущем снимке персонажа. */
  met: boolean;
  /** Причина невыполнения (null — выполнено). */
  reason: string | null;
  /** Все невыполненные требования уровня по отдельности: способности, опыт, признаки, характеристики. */
  unmetReasons?: string[];
  /** Невыполненные требования-способности: из них строится цепочка покупки. */
  unmetAbilities?: UnmetAbility[];
}
