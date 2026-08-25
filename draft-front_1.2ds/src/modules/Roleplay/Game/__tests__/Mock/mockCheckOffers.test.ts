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
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOffer';

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
        itemRuleId: 'sword',
        itemName: 'Меч',
        profileType: 'strike',
        accuracy: { base: 4, size: 0 },
        reaction: 'dodge',
        defenseEfficiency: { base: 4, size: -1 },
        blockItemRuleId: null,
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
});
