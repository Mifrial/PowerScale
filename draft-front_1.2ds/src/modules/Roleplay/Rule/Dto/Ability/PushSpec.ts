/** Спека толчка на действии: контест, урон и допустимые профили. */
export interface PushSpec {
  pool: 'strength' | 'weapon_damage';
  damage: 'crush_from_strength' | 'weapon_times_sr';
  profiles: 'hands_or_shield' | 'slashing_or_blunt_strike';
  posture_rating_divisor_by_damage_type?: Record<string, number>;
}
