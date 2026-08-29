import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CreateCheckOfferData } from '@/modules/Roleplay/Game/Dto/CreateCheckOfferData';
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
  if (actorKey === offer.opponent || offer.waitingOnTargets?.includes(actorKey)) return 'opponent';
  throw new Error('Вы не участник этой проверки');
}

function targetProposalsOf(offer: CheckOffer): NonNullable<CheckOfferProposal['targetProposals']> {
  return offer.proposal.targetProposals ?? [];
}

function pendingTargetsOf(offer: CheckOffer): CombatEntityKey[] {
  return targetProposalsOf(offer)
    .filter((target) => target.hit.reaction === null)
    .map((target) => target.targetKey);
}

export async function createCheckOffer(gameId: number, data: CreateCheckOfferData): Promise<CheckOffer> {
  await delay();
  if (data.initiator === data.opponent) throw new Error('Нужен другой участник');
  const attackTargets = [...new Set(data.proposal.attackAction?.strikes.map((strike) => strike.targetKey) ?? [])];
  const hit = data.proposal.hit;
  const isWide = data.proposal.attackAction?.mode === 'wide';
  if (isWide && (attackTargets.length === 0 || attackTargets.length > 3)) {
    throw new Error('Широкий удар может иметь от одной до трёх целей');
  }
  if (isWide && attackTargets.length !== (data.proposal.attackAction?.strikes.length ?? 0)) {
    throw new Error('Цели Широкого удара должны быть различными');
  }
  const targetProposals =
    isWide && hit
      ? attackTargets.map((targetKey) => ({
          targetKey,
          hit: { ...hit, reaction: null },
        }))
      : undefined;
  const offer: CheckOffer = {
    id: nextId++,
    gameId,
    checkCode: data.checkCode,
    initiator: data.initiator,
    opponent: data.opponent,
    proposal: { ...data.proposal, targetProposals },
    waitingOn: 'opponent',
    waitingOnTargets: targetProposals?.map((target) => target.targetKey),
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
  if (role === 'opponent' && offer.waitingOnTargets && !offer.waitingOnTargets.includes(actorKey))
    throw new Error('Сейчас ход другой стороны');
  if (role === 'initiator' && offer.waitingOn !== role) throw new Error('Сейчас ход другой стороны');
  if (role === 'opponent' && offer.waitingOnTargets) {
    const target = targetProposalsOf(offer).find((entry) => entry.targetKey === actorKey);
    if (target && proposal.hit) target.hit = { ...target.hit, ...proposal.hit };
    offer.proposal = { ...offer.proposal, ...proposal, targetProposals: targetProposalsOf(offer) };
    offer.waitingOnTargets = pendingTargetsOf(offer);
    offer.waitingOn = offer.waitingOnTargets.length > 0 ? 'opponent' : 'initiator';
  } else {
    offer.proposal = { ...proposal, targetProposals: offer.proposal.targetProposals };
    offer.waitingOn = role === 'initiator' ? 'opponent' : 'initiator';
  }
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
  if (role === 'opponent' && offer.waitingOnTargets && !offer.waitingOnTargets.includes(actorKey))
    throw new Error('Сейчас ход другой стороны');
  if (role === 'initiator' && offer.waitingOn !== role) throw new Error('Сейчас ход другой стороны');
  if (proposal) {
    if (role === 'opponent' && offer.waitingOnTargets) {
      const target = targetProposalsOf(offer).find((entry) => entry.targetKey === actorKey);
      if (target && proposal.hit) target.hit = { ...target.hit, ...proposal.hit };
      offer.proposal = { ...offer.proposal, ...proposal, targetProposals: targetProposalsOf(offer) };
      offer.waitingOnTargets = pendingTargetsOf(offer);
      offer.waitingOn = offer.waitingOnTargets.length > 0 ? 'opponent' : 'initiator';
    } else {
      offer.proposal = { ...proposal, targetProposals: offer.proposal.targetProposals };
    }
  }
  if (offer.waitingOnTargets?.length) return snapshot(offer);
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
        ((offer.waitingOn === 'opponent' &&
          (offer.opponent === entityKey || offer.waitingOnTargets?.includes(entityKey))) ||
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
        (offer.initiator === entityKey ||
          offer.opponent === entityKey ||
          offer.waitingOnTargets?.includes(entityKey) ||
          targetProposalsOf(offer).some((target) => target.targetKey === entityKey)),
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
