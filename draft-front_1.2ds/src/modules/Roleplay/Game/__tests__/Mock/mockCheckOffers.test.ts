import { beforeEach, describe, expect, it } from 'vitest';
import {
  acceptCheckOffer,
  cancelCheckOffer,
  createCheckOffer,
  getCheckOffersForEntity,
  getCheckOffersForGame,
  getPendingCheckOffers,
  resetCheckOffers,
  reviseCheckOffer,
} from '@/modules/Roleplay/Game/Mock/mockCheckOffers';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';

const initiator = 'character:1' as const;
const opponent = 'character:2' as const;
const proposal: CheckOfferProposal = {
  initiatorCharacteristic: 'strength',
  opponentCharacteristic: 'strength',
  initiatorAdv: 0,
  opponentAdv: 1,
};

describe('mockCheckOffers: handshake pairwise', () => {
  beforeEach(() => resetCheckOffers());

  it('create → waitingOn opponent; pending только у того, чей ход', async () => {
    const created = await createCheckOffer(7, {
      checkCode: 'check-strength',
      initiator,
      opponent,
      proposal,
    });
    expect(created.status).toBe('pending');
    expect(created.waitingOn).toBe('opponent');
    expect(await getPendingCheckOffers(7, opponent)).toHaveLength(1);
    expect(await getPendingCheckOffers(7, initiator)).toHaveLength(0);
    expect(await getCheckOffersForEntity(7, initiator)).toHaveLength(1);
  });

  it('revise оппонентом возвращает ход инициатору', async () => {
    const created = await createCheckOffer(7, {
      checkCode: 'check-simple',
      initiator,
      opponent,
      proposal,
    });
    const revised = await reviseCheckOffer(created.id, opponent, { ...proposal, opponentCharacteristic: 'agility' });
    expect(revised.waitingOn).toBe('initiator');
    expect(revised.proposal.opponentCharacteristic).toBe('agility');
    expect(await getPendingCheckOffers(7, initiator)).toHaveLength(1);
  });

  it('accept закрывает оферту; повтор и чужой ход — ошибка', async () => {
    const created = await createCheckOffer(7, {
      checkCode: 'check-strength',
      initiator,
      opponent,
      proposal,
    });
    await expect(acceptCheckOffer(created.id, initiator)).rejects.toThrow('Сейчас ход другой стороны');
    const accepted = await acceptCheckOffer(created.id, opponent);
    expect(accepted.status).toBe('accepted');
    await expect(acceptCheckOffer(created.id, opponent)).rejects.toThrow('Оферта уже закрыта');
    expect(await getPendingCheckOffers(7, opponent)).toHaveLength(0);
  });

  it('getCheckOffersForGame отдаёт pending всей игры', async () => {
    await createCheckOffer(7, { checkCode: 'check-hit', initiator, opponent, proposal });
    expect(await getCheckOffersForGame(7)).toHaveLength(1);
    expect(await getCheckOffersForGame(8)).toHaveLength(0);
  });

  it('accept пишет proposal защитника', async () => {
    const created = await createCheckOffer(7, { checkCode: 'check-hit', initiator, opponent, proposal });
    const accepted = await acceptCheckOffer(created.id, opponent, {
      ...proposal,
      opponentAdv: 2,
      hit: {
        itemRuleCode: 'sword',
        itemName: 'Меч',
        profileType: 'strike',
        accuracy: { base: 4, size: 0 },
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
        blockItemRuleCode: null,
      },
    });
    expect(accepted.status).toBe('accepted');
    expect(accepted.proposal.hit?.reaction).toBe('dodge');
    expect(accepted.proposal.opponentAdv).toBe(2);
  });

  it('cancel участником снимает оферту', async () => {
    const created = await createCheckOffer(7, {
      checkCode: 'check-strength',
      initiator,
      opponent,
      proposal,
    });
    const cancelled = await cancelCheckOffer(created.id, initiator);
    expect(cancelled.status).toBe('cancelled');
    expect(await getCheckOffersForEntity(7, opponent)).toHaveLength(0);
  });

  it('cancel оппонентом тоже снимает оферту (игнор совместной проверки)', async () => {
    const created = await createCheckOffer(7, {
      checkCode: 'check-strength',
      initiator,
      opponent,
      proposal,
    });
    const cancelled = await cancelCheckOffer(created.id, opponent);
    expect(cancelled.status).toBe('cancelled');
    expect(await getCheckOffersForEntity(7, initiator)).toHaveLength(0);
  });

  it('групповая оферта ждёт независимые ответы всех целей', async () => {
    const targetThree = 'character:3' as const;
    const groupHit = {
      itemRuleCode: 'sword',
      itemName: 'Меч',
      profileType: 'strike' as const,
      accuracy: { base: 4, size: 0 },
      reaction: null,
    };
    const groupProposal = {
      ...proposal,
      attackAction: {
        initiator,
        source: { kind: 'action', actionRuleCode: 'wide' },
        mode: 'wide',
        strikes: [
          { targetKey: opponent, profile: {} },
          { targetKey: 'character:3', profile: {} },
        ],
        reactionMode: 'simultaneous',
        totalOdCost: 4,
      },
      hit: groupHit,
    } as unknown as CheckOfferProposal;
    const created = await createCheckOffer(7, {
      checkCode: 'check-hit',
      initiator,
      opponent,
      proposal: groupProposal,
    });

    expect(await getPendingCheckOffers(7, targetThree)).toHaveLength(1);
    await reviseCheckOffer(created.id, targetThree, {
      ...groupProposal,
      hit: { ...groupHit, reaction: 'dodge' },
    });
    expect((await getCheckOffersForGame(7))[0]?.waitingOn).toBe('opponent');
    const revised = await reviseCheckOffer(created.id, opponent, {
      ...groupProposal,
      hit: { ...groupHit, reaction: 'ignore' },
    });

    expect(revised.waitingOn).toBe('initiator');
    expect(revised.waitingOnTargets).toEqual([]);
    const accepted = await acceptCheckOffer(created.id, initiator);
    expect(accepted.status).toBe('accepted');
  });

  it('последовательные удары ждут отдельную реакцию на каждый', async () => {
    const strikeHit = {
      itemRuleCode: 'ruka',
      itemName: 'Рука',
      profileType: 'strike' as const,
      accuracy: { base: 4, size: 0 },
      reaction: null,
    };
    const sequentialProposal = {
      ...proposal,
      attackAction: {
        initiator,
        source: { kind: 'action', actionRuleCode: 'dual' },
        strikes: [
          { targetKey: opponent, profile: { itemRuleCode: 'ruka', itemName: 'Рука' } },
          { targetKey: opponent, profile: { itemRuleCode: 'dagger', itemName: 'Кинжал' } },
        ],
        reactionMode: 'sequential',
        totalOdCost: 4,
      },
      hit: strikeHit,
    } as unknown as CheckOfferProposal;
    const created = await createCheckOffer(7, {
      checkCode: 'check-hit',
      initiator,
      opponent,
      proposal: sequentialProposal,
    });
    expect(created.waitingOnTargets).toEqual([opponent]);
    expect(created.proposal.strikeProposals).toHaveLength(2);
    const first = await acceptCheckOffer(created.id, opponent, {
      ...sequentialProposal,
      hit: { ...strikeHit, reaction: 'dodge' },
    });
    expect(first.status).toBe('pending');
    expect(first.proposal.strikeProposals?.[0]?.hit.reaction).toBe('dodge');
    expect(first.proposal.strikeProposals?.[1]?.hit.reaction).toBeNull();
    const second = await acceptCheckOffer(created.id, opponent, {
      ...sequentialProposal,
      hit: { ...strikeHit, itemRuleCode: 'dagger', itemName: 'Кинжал', reaction: 'ignore' },
    });
    expect(second.status).toBe('accepted');
    expect(second.proposal.strikeProposals?.map((slot) => slot.hit.reaction)).toEqual(['dodge', 'ignore']);
  });

  it('прикрывающий отправляет преимущества атакующему и не закрывает оферту', async () => {
    const coverer = 'character:3' as const;
    const hitProposal: CheckOfferProposal = {
      ...proposal,
      coverInvites: [{ coveringKey: coverer, decision: 'pending' }],
      hit: {
        itemRuleCode: 'dagger',
        itemName: 'Кинжал',
        profileType: 'strike',
        accuracy: { base: 5, size: 0 },
        reaction: null,
      },
    };
    const created = await createCheckOffer(7, {
      checkCode: 'check-hit',
      initiator,
      opponent,
      proposal: hitProposal,
    });
    expect(created.waitingOn).toBe('covering');
    const revised = await reviseCheckOffer(created.id, coverer, {
      ...hitProposal,
      coverInvites: [{ coveringKey: coverer, decision: 'pending', coveringAdv: 2 }],
    });
    expect(revised.status).toBe('pending');
    expect(revised.waitingOn).toBe('initiator');
    expect(revised.proposal.coverInvites?.[0]?.coveringAdv).toBe(2);
    const confirmed = await acceptCheckOffer(created.id, initiator, revised.proposal);
    expect(confirmed.status).toBe('pending');
    expect(confirmed.waitingOn).toBe('covering');
  });

  it('создаёт reservation для touch-offer и меняет её статус при commit/cancel', async () => {
    const spellProposal: CheckOfferProposal = {
      ...proposal,
      spellCast: {
        spellCode: 'discharge',
        usedPower: { base: 4, size: 0 },
        availableControl: { base: 3, size: 0 },
        parameterValues: {},
        hasSpellTarget: true,
        spellTargetKey: opponent,
        targetResistanceAmount: 0,
        parameterPower: { base: 4, size: 0 },
        checkCode: 'check-intellect',
        castCheckCode: 'check-spell-cast',
        characteristicValue: { base: 5, size: 0 },
        characteristicName: 'Интеллект',
        touchActionCode: 'simple-touch',
        touchActionName: 'Простое касание',
        spellOd: 2,
        touchOd: 3,
        spentAp: 5,
        trainingDifficultyDelta: -1,
        pendingSignature: 'interstructure-energy-transfer:-1:5',
      },
    };
    const created = await createCheckOffer(7, {
      checkCode: 'check-hit',
      initiator,
      opponent,
      proposal: spellProposal,
    });

    expect(created.spellCastReservation).toMatchObject({
      offerId: created.id,
      casterKey: initiator,
      reservedActionCost: 5,
      trainingDifficultyDelta: -1,
      reservedPendingSignature: 'interstructure-energy-transfer:-1:5',
      status: 'reserved',
    });
    const accepted = await acceptCheckOffer(created.id, opponent);
    expect(accepted.spellCastReservation?.status).toBe('committed');

    const second = await createCheckOffer(7, {
      checkCode: 'check-hit',
      initiator,
      opponent,
      proposal: spellProposal,
    });
    const cancelled = await cancelCheckOffer(second.id, initiator);
    expect(cancelled.spellCastReservation?.status).toBe('released');
  });
});
