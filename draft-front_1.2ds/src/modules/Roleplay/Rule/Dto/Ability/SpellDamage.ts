/** Урон заклинания: тип и сдвиг мощи `power.modify(N)` по опыту школы. */
export interface SpellDamage {
  damage_type_code: string;
  experience_keyword_code: string;
  power_modify_steps: { min_experience: number; modify: number }[];
  /** Снижение размера урона за ипари сверх бесплатных; ниже min урон не наносится. */
  falloff?: {
    free_ipari: number;
    size_per_extra_ipari: number;
    min: { base: number; size: number };
  };
}
