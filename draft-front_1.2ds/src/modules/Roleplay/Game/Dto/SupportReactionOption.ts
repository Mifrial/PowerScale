import type { SupportReactionActor } from '@/modules/Roleplay/Game/Dto/SupportReactionActor';
import type { SupportReactionWeapon } from '@/modules/Roleplay/Game/Dto/SupportReactionWeapon';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';

/** Участник, который может поддержать открытый удар, и его доступные средства. */
export interface SupportReactionOption {
  actor: SupportReactionActor;
  rule: Rule;
  sides: SupportSide[];
  weapons: SupportReactionWeapon[];
}
