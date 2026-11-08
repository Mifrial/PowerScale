import type { StrikeManeuverEffect, StrikeManeuverEffectSelector } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeManeuver';

/** Подмена эффекта объявленного манёвра при высоком РУ. */
export interface ManeuverBoost {
  maneuvers: string[];
  min_success: number;
  min_weapon_mastery?: number;
  replaces?: StrikeManeuverEffectSelector[];
  effects: StrikeManeuverEffect[];
}
