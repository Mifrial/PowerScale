import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';

export interface CoverInvite {
  coveringKey: CombatEntityKey;
  decision: 'pending' | 'cover' | 'decline';
  reaction?: Exclude<HitDefenseReaction, 'dodge' | 'ignore'>;
  defenseEfficiency?: DimensionalNumberValue | null;
  blockItemRuleCode?: string | null;
  /** Преимущества/помехи прикрывающего на его проверку блока. */
  coveringAdv?: number;
}
