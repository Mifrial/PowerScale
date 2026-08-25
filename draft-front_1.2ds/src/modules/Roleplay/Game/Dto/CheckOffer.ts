import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export type HitDefenseReaction = 'ignore' | 'dodge' | 'block';

/** Род запуска: соло; pairwise handshake; группа ГМ (инициатива — свой UI). */
export type CheckLaunchKind = 'solo' | 'pairwise' | 'group_gm';

export interface CheckOfferProposal {
  initiatorCharacteristic: string | null;
  opponentCharacteristic: string | null;
  initiatorAdv: number;
  opponentAdv: number;
  /** Свободный пул, если нет листа / характеристика не выбрана. */
  initiatorFree?: { diceCount: number; dieSize: number; efficiency: number } | null;
  opponentFree?: { diceCount: number; dieSize: number; efficiency: number } | null;
  /** Попадание: профиль оружия; реакцию и эффективность защиты пишет защитник. */
  hit?: {
    itemRuleId: string;
    itemName: string;
    profileType: 'strike' | 'throw' | 'shoot';
    accuracy: DimensionalNumberValue;
    reaction: HitDefenseReaction | null;
    defenseEfficiency?: DimensionalNumberValue | null;
    blockItemRuleId?: string | null;
    damageTypeCode?: string | null;
    damage?: DimensionalNumberValue;
    penetration?: DimensionalNumberValue;
    actionRuleId?: string | null;
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

/**
 * Оферта pairwise-проверки (согласование сторон). Кубы падают только после accept.
 * Живой транспорт — SSE; сейчас мок.
 */
export interface CheckOffer {
  id: number;
  gameId: number;
  checkCode: string;
  initiator: CombatEntityKey;
  opponent: CombatEntityKey;
  proposal: CheckOfferProposal;
  waitingOn: 'opponent' | 'initiator';
  status: 'pending' | 'accepted' | 'cancelled';
  updatedAt: string;
}

export interface CreateCheckOfferData {
  checkCode: string;
  initiator: CombatEntityKey;
  opponent: CombatEntityKey;
  proposal: CheckOfferProposal;
}
