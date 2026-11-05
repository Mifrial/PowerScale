import type { ReactionActor } from '@/modules/Roleplay/Game/Dto/ReactionActor';
import type { ReactionWeapon } from '@/modules/Roleplay/Game/Dto/ReactionWeapon';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ReactionSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ReactionSpec';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';

/** Сторона удара с именем участника — чтобы окно не показывало «attacker». */
export interface ReactionSide {
  value: SupportSide;
  name: string;
}

/** Эффект Поддержки: кому преимущество и каким оружием. */
export interface ReactionSupportEffect {
  kind: 'support';
  sides: ReactionSide[];
  weapons: ReactionWeapon[];
  advantage: number;
  source: string;
}

/** Эффект Прикрытия: разрешённые защиты и предметы блока. */
export interface ReactionCoverEffect {
  kind: 'cover';
  reactions: string[];
  defenseAdvantages: AdvantageModifier[];
  blockItems: ReactionWeapon[];
}

/** Доступная реакция: карточка, объявляющий её участник и варианты эффекта. */
export interface ReactionOffer {
  rule: Rule;
  actor: ReactionActor;
  reaction: ReactionSpec;
  cost: number;
  effect: ReactionSupportEffect | ReactionCoverEffect;
  /** Строка широкого удара, к которой относится заявка. */
  target?: { type: string; id: number };
  /** Подпись выбора, когда одна карточка доступна на несколько строк. */
  label?: string;
}
