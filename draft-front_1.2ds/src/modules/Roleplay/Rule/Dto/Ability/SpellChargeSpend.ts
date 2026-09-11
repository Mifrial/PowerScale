import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';

/** Трата заряда: открыть каст с предикатом, не список заклинаний. */
export interface SpellChargeSpend {
  action_points: number;
  amount: number;
  keyword_code: string;
  exclude_duration_types: SpellDuration['type'][];
  creation_max: 'turn_ap';
}
