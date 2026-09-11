import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageResult';
import type { HitCheckRoll } from '@/modules/Roleplay/Game/Dto/HitCheckRoll';
import type { SpellCastRollOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastRollOutcome';

export type SpellCastRefuseReason = 'not_enough_ap' | 'needs_hit_offer';

export type SpellCastDelivery = 'hit' | 'milk' | 'auto' | 'none';

export interface SpellCastExecutionResult {
  started: boolean;
  refuseReason: SpellCastRefuseReason | null;
  spentAp: number;
  remainingActionPoints: DimensionalNumberValue | null;
  autoFail: boolean;
  milk: boolean;
  delivery: SpellCastDelivery;
  hit: HitCheckRoll | null;
  attackSr: number | null;
  spellSr: number | null;
  spellDamage: DimensionalNumberValue | null;
  weaponApply: ApplyAttackDamageResult | null;
  spellApply: ApplyAttackDamageResult | null;
  cast: SpellCastRollOutcome | null;
}
