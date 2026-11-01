/** Спека толчка на действии: проверка, урон и допустимые профили. */
export interface PushSpec {
  check?: string;
  profiles: 'hands_or_shield' | 'slashing_or_blunt_strike';
  block_profiles?: 'any' | 'hands_or_shield' | 'shield_only' | 'none' | 'slashing_or_blunt_strike';
  damage_modifier?: number;
  stability_bonus_cap?: number;
  posture_rating_divisor_by_damage_type?: Record<string, number>;
}
