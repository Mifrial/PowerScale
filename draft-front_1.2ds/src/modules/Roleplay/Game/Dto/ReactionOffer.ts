import type { ReactionActor } from '@/modules/Roleplay/Game/Dto/ReactionActor';
import type { ReactionWeapon } from '@/modules/Roleplay/Game/Dto/ReactionWeapon';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ReactionSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ReactionSpec';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';

/** Сторона удара с именем участника — чтобы окно не показывало «attacker». */
export interface ReactionSide {
  value: SupportSide;
  name: string;
}

/** Доступная реакция: карточка, объявляющий её участник и варианты эффекта. */
export interface ReactionOffer {
  rule: Rule;
  actor: ReactionActor;
  reaction: ReactionSpec;
  sides: ReactionSide[];
  weapons: ReactionWeapon[];
  advantage: number;
  source: string;
  cost: number;
}
