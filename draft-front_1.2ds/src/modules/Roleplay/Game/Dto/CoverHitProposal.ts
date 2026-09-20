import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';

export interface CoverHitProposal {
  coveringKey: CombatEntityKey;
  reaction: Exclude<HitDefenseReaction, 'dodge' | 'ignore'>;
  defenseEfficiency?: DimensionalNumberValue | null;
  blockItemRuleCode?: string | null;
  /** После провала прикрывающего: оставить цель или перенести удар. */
  redirectChoice?: 'keep' | 'redirect' | null;
}
