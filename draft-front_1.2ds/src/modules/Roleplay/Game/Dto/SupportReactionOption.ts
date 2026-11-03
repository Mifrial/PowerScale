import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { SupportReactionWeapon } from '@/modules/Roleplay/Game/Dto/SupportReactionWeapon';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';

/** Персонаж пользователя, который может поддержать открытый удар. */
export interface SupportReactionOption {
  membership: GameCharacterMembership;
  rule: Rule;
  sides: SupportSide[];
  weapons: SupportReactionWeapon[];
}
