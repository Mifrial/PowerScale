import type { AttackActionStrike } from '@/modules/Roleplay/Game/Dto/AttackActionStrike';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CheckOfferStrikeProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferStrikeProposal';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { reactionOdCost } from '@/modules/Roleplay/Game/Utils/combatActions';

/**
 * Слоты реакции на последовательные удары одной оферты (Обоерукая: два ответа, не wide).
 */
export class SequentialStrikeOfferService {
  isMultiStrike(proposal: Pick<CheckOfferProposal, 'attackAction'>): boolean {
    return proposal.attackAction?.reactionMode === 'sequential' && (proposal.attackAction.strikes.length ?? 0) > 1;
  }

  slotsFrom(proposal: Pick<CheckOfferProposal, 'strikeProposals'> | null | undefined): CheckOfferStrikeProposal[] {
    return proposal?.strikeProposals ?? [];
  }

  createSlots(
    strikes: AttackActionStrike[],
    hit: NonNullable<CheckOfferProposal['hit']>,
  ): CheckOfferStrikeProposal[] {
    return strikes.map((strike, strikeIndex) => ({
      strikeIndex,
      targetKey: strike.targetKey,
      hit: {
        ...hit,
        itemRuleCode: strike.profile.itemRuleCode,
        itemName: strike.profile.itemName,
        profileType: strike.profile.profileType,
        profileIndex: strike.profile.profileIndex,
        accuracy: strike.profile.accuracy,
        damageTypeCode: strike.profile.damageTypeCode,
        damage: strike.profile.damage,
        penetration: strike.profile.penetration,
        reach: strike.profile.reach,
        falloff: strike.profile.falloff,
        reaction: null,
      },
    }));
  }

  nextPending(slots: CheckOfferStrikeProposal[], actorKey: CombatEntityKey | null): CheckOfferStrikeProposal | null {
    return slots.find((slot) => slot.hit.reaction === null && (actorKey === null || slot.targetKey === actorKey)) ?? null;
  }

  pendingTargetKeys(slots: CheckOfferStrikeProposal[]): CombatEntityKey[] {
    const keys: CombatEntityKey[] = [];
    for (const slot of slots) {
      if (slot.hit.reaction !== null || keys.includes(slot.targetKey)) continue;
      keys.push(slot.targetKey);
    }

    return keys;
  }

  fillNextHit(
    slots: CheckOfferStrikeProposal[],
    actorKey: CombatEntityKey,
    hit: NonNullable<CheckOfferProposal['hit']>,
  ): CheckOfferStrikeProposal[] {
    const index = slots.findIndex((slot) => slot.targetKey === actorKey && slot.hit.reaction === null);
    if (index < 0) return slots;

    return slots.map((slot, slotIndex) =>
      slotIndex === index ? { ...slot, hit: { ...slot.hit, ...hit } } : slot,
    );
  }

  committedReactionOd(slots: CheckOfferStrikeProposal[], rules: Rule[]): number {
    return slots.reduce((total, slot) => total + reactionOdCost(slot.hit.reaction, rules), 0);
  }
}
