import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { AttackAction } from '@/modules/Roleplay/Game/Dto/AttackAction';
import type { CheckOfferTargetProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferTargetProposal';

export interface CheckOfferProposal {
  initiatorCharacteristic: string | null;
  opponentCharacteristic: string | null;
  initiatorAdv: number;
  opponentAdv: number;
  /** Свободный пул, если нет листа / характеристика не выбрана. */
  initiatorFree?: { diceCount: number; dieSize: number; efficiency: number } | null;
  opponentFree?: { diceCount: number; dieSize: number; efficiency: number } | null;
  /** Готовый план одновременной атаки; `hit` сохраняется для обратной совместимости. */
  attackAction?: AttackAction | null;
  /** Независимые ответы целей для групповой атаки. */
  targetProposals?: CheckOfferTargetProposal[];
  /** Попадание: профиль оружия; реакцию и эффективность защиты пишет защитник. */
  hit?: {
    itemRuleCode: string;
    itemName: string;
    profileType: 'strike' | 'throw' | 'shoot';
    profileIndex?: number;
    accuracy: DimensionalNumberValue;
    reaction: HitDefenseReaction | null;
    defenseEfficiency?: DimensionalNumberValue | null;
    blockItemRuleCode?: string | null;
    damageTypeCode?: string | null;
    damage?: DimensionalNumberValue;
    penetration?: DimensionalNumberValue;
    actionRuleCode?: string | null;
    actionName?: string;
    actionOd?: number;
    distanceIpari?: number | null;
    cover?: number;
    reach?: number;
    falloff?: DimensionalNumberValue;
    flank?: boolean;
    turn?: boolean;
  } | null;
}
