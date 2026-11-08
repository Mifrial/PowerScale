/** Типизированный эффект манёвра: добавляет состояние цели. */
export interface StrikeManeuverEffect {
  kind: 'state';
  state: string;
  amount: number;
}

/** Селектор эффекта, который усиление манёвра заменяет. */
export interface StrikeManeuverEffectSelector {
  kind: 'state';
  state: string;
}

/** Манёвр, который совершают сразу после удара с нужным РУ. */
export interface StrikeManeuver {
  min_success: number;
  min_weapon_mastery?: number;
  effects: StrikeManeuverEffect[];
}
