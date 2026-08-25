export type InjuryHealUnit = 'days' | 'decades' | 'months' | 'years';

export interface InjuryHealRoll {
  diceCount: number;
  dieFaces: number;
  unit: InjuryHealUnit;
  rolls: number[];
  total: number;
}

export type InjuryDifficultySource = 'manual' | 'leftover' | 'wound' | 'exhaustion';

/** Из чего сложилась сложность проверки на увечье. */
export interface InjuryDifficultyBreakdown {
  leftoverDamage: number;
  endurance: number;
  fromDamage: number;
  woundStrength: number;
  woundDivisor: number;
  fromWound: number;
  exhaustion: number;
  exhaustionOffset: number;
  fromExhaustion: number;
  extraDifficulty: number;
  source: InjuryDifficultySource;
  total: number;
}

export interface InjuryOutcome {
  strength: number;
  permanent: boolean;
  temporary: boolean;
  lethal: boolean;
  disfiguring: boolean;
  heal?: InjuryHealRoll;
  /** Сложность проверки (после хуков типа). */
  difficulty?: number;
  /** РУ: успехи − сложность, без свёртки отрицательной базы. */
  rating?: number;
  breakdown?: InjuryDifficultyBreakdown;
}
