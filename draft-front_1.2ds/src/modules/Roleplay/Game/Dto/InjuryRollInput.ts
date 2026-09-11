import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { InjuryDifficultySource } from '@/modules/Roleplay/Game/Dto/InjuryOutcome';

export interface InjuryRollInput {
  /** Повреждения = урон − сопротивление (не сырой урон оружия). */
  leftoverDamage: number;
  woundStrength: number;
  /** Ручной запуск: сложность вместо автоформулы. */
  difficulty?: number;
  endurance: number;
  exhaustion: number;
  attackSr: number;
  damageTypeCode?: string | null;
  advantages?: AdvantageModifier[];
  actorKey?: DiceRollResult['spec']['actorKey'];
  label?: string;
  /** Принудительный триггер в [i] (остатки разных типов → упадок сил). */
  forceSource?: InjuryDifficultySource;
}
