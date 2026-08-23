import type { CheckOffer, CheckOfferProposal, CreateCheckOfferData } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

const delay = (ms = 80) => new Promise((r) => setTimeout(r, ms));

const offers = new Map<number, CheckOffer>();
let nextId = 1;

function snapshot(offer: CheckOffer): CheckOffer {
  return JSON.parse(JSON.stringify(offer)) as CheckOffer;
}

function requirePending(id: number): CheckOffer {
  const offer = offers.get(id);
  if (!offer) throw new Error('Оферта не найдена');
  if (offer.status !== 'pending') throw new Error('Оферта уже закрыта');

  return offer;
}

function actorRole(offer: CheckOffer, actorKey: CombatEntityKey): 'initiator' | 'opponent' {
  if (actorKey === offer.initiator) return 'initiator';
  if (actorKey === offer.opponent) return 'opponent';
  throw new Error('Вы не участник этой проверки');
}

export async function createCheckOffer(gameId: number, data: CreateCheckOfferData): Promise<CheckOffer> {
  await delay();
  if (data.initiator === data.opponent) throw new Error('Нужен другой участник');
  const offer: CheckOffer = {
    id: nextId++,
    gameId,
    checkCode: data.checkCode,
    initiator: data.initiator,
    opponent: data.opponent,
    proposal: { ...data.proposal },
    waitingOn: 'opponent',
    status: 'pending',
    updatedAt: new Date().toISOString(),
  };
  offers.set(offer.id, offer);

  return snapshot(offer);
}

export async function reviseCheckOffer(
  offerId: number,
  actorKey: CombatEntityKey,
  proposal: CheckOfferProposal,
): Promise<CheckOffer> {
  await delay();
  const offer = requirePending(offerId);
  const role = actorRole(offer, actorKey);
  if (offer.waitingOn !== role) throw new Error('Сейчас ход другой стороны');
  offer.proposal = { ...proposal };
  offer.waitingOn = role === 'initiator' ? 'opponent' : 'initiator';
  offer.updatedAt = new Date().toISOString();

  return snapshot(offer);
}

export async function acceptCheckOffer(
  offerId: number,
  actorKey: CombatEntityKey,
  proposal?: CheckOfferProposal,
): Promise<CheckOffer> {
  await delay();
  const offer = requirePending(offerId);
  const role = actorRole(offer, actorKey);
  if (offer.waitingOn !== role) throw new Error('Сейчас ход другой стороны');
  if (proposal) offer.proposal = { ...proposal };
  offer.status = 'accepted';
  offer.updatedAt = new Date().toISOString();

  return snapshot(offer);
}

export async function cancelCheckOffer(offerId: number, actorKey: CombatEntityKey): Promise<CheckOffer> {
  await delay();
  const offer = requirePending(offerId);
  actorRole(offer, actorKey);
  offer.status = 'cancelled';
  offer.updatedAt = new Date().toISOString();

  return snapshot(offer);
}

/** Оферты, где стороне пора ответить (закладка под SSE). */
export async function getPendingCheckOffers(gameId: number, entityKey: CombatEntityKey): Promise<CheckOffer[]> {
  await delay(40);

  return [...offers.values()]
    .filter(
      (offer) =>
        offer.gameId === gameId &&
        offer.status === 'pending' &&
        ((offer.waitingOn === 'opponent' && offer.opponent === entityKey) ||
          (offer.waitingOn === 'initiator' && offer.initiator === entityKey)),
    )
    .map(snapshot);
}

/** Все незакрытые оферты с участием сущности (ожидание чужого хода тоже видно). */
export async function getCheckOffersForEntity(gameId: number, entityKey: CombatEntityKey): Promise<CheckOffer[]> {
  await delay(40);

  return [...offers.values()]
    .filter(
      (offer) =>
        offer.gameId === gameId &&
        offer.status === 'pending' &&
        (offer.initiator === entityKey || offer.opponent === entityKey),
    )
    .map(snapshot);
}

/** Все незакрытые оферты игры (ведущий видит ход защитника, не переключая спикера). */
export async function getCheckOffersForGame(gameId: number): Promise<CheckOffer[]> {
  await delay(40);

  return [...offers.values()].filter((offer) => offer.gameId === gameId && offer.status === 'pending').map(snapshot);
}

export function resetCheckOffers(): void {
  offers.clear();
  nextId = 1;
}
