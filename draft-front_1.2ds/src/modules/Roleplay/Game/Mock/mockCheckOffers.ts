import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CreateCheckOfferData } from '@/modules/Roleplay/Game/Dto/CreateCheckOfferData';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { sequentialStrikeOfferService } from '@/modules/Roleplay/Game/Service/Instance/sequentialStrikeOfferService';
import { coveringService } from '@/modules/Roleplay/Game/Service/Instance/coveringService';

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

function actorRole(offer: CheckOffer, actorKey: CombatEntityKey): 'initiator' | 'opponent' | 'covering' {
  if (actorKey === offer.initiator) return 'initiator';
  if (offer.waitingOn === 'covering' && offer.waitingOnCoverers?.includes(actorKey)) return 'covering';
  if (actorKey === offer.opponent || offer.waitingOnTargets?.includes(actorKey)) return 'opponent';
  throw new Error('Вы не участник этой проверки');
}

function pendingCoverersOf(offer: CheckOffer): CombatEntityKey[] {
  return coveringService.pendingInvites(offer.proposal.coverInvites).map((invite) => invite.coveringKey);
}

function refreshWait(offer: CheckOffer): void {
  const coverers = pendingCoverersOf(offer);
  if (coverers.length) {
    offer.waitingOn = 'covering';
    offer.waitingOnCoverers = coverers;
    offer.waitingOnTargets = [];

    return;
  }
  offer.waitingOnCoverers = [];
  offer.waitingOnTargets = pendingTargetsOf(offer);
  offer.waitingOn = offer.waitingOnTargets.length > 0 ? 'opponent' : 'initiator';
}

function applyCoverProposal(offer: CheckOffer, actorKey: CombatEntityKey, proposal: CheckOfferProposal): void {
  const invites = offer.proposal.coverInvites ?? [];
  const nextInvite = proposal.coverInvites?.find((invite) => invite.coveringKey === actorKey);
  offer.proposal = {
    ...offer.proposal,
    coverInvites: invites.map((invite) => (invite.coveringKey === actorKey && nextInvite ? nextInvite : invite)),
  };
  refreshWait(offer);
}

function targetProposalsOf(offer: CheckOffer): NonNullable<CheckOfferProposal['targetProposals']> {
  return offer.proposal.targetProposals ?? [];
}

function reactionSlotsOf(offer: CheckOffer): NonNullable<CheckOfferProposal['strikeProposals']> {
  return sequentialStrikeOfferService.slotsFrom(offer.proposal);
}

function pendingTargetsOf(offer: CheckOffer): CombatEntityKey[] {
  const sequentialPending = sequentialStrikeOfferService.pendingTargetKeys(reactionSlotsOf(offer));
  if (sequentialPending.length) return sequentialPending;
  const wide = targetProposalsOf(offer)
    .filter((target) => target.hit.reaction === null)
    .map((target) => target.targetKey);
  if (wide.length) return wide;
  if (offer.proposal.hit && offer.proposal.hit.reaction === null) return [offer.opponent];

  return [];
}

function syncSpellCastReservation(offer: CheckOffer): void {
  const context = offer.proposal.spellCast;
  if (!context) {
    delete offer.spellCastReservation;

    return;
  }
  const current = offer.spellCastReservation;
  offer.spellCastReservation = {
    reservationId: current?.reservationId ?? `spell-cast:${offer.id}`,
    offerId: offer.id,
    casterKey: offer.initiator,
    reservedActionCost: context.spentAp,
    trainingDifficultyDelta: context.trainingDifficultyDelta ?? 0,
    reservedPendingSignature: context.pendingSignature ?? null,
    status: current?.status ?? 'reserved',
  };
}

function applyOpponentProposal(offer: CheckOffer, actorKey: CombatEntityKey, proposal: CheckOfferProposal): void {
  const sequentialSlots = reactionSlotsOf(offer);
  if (sequentialSlots.length && proposal.hit) {
    offer.proposal = {
      ...offer.proposal,
      ...proposal,
      strikeProposals: sequentialStrikeOfferService.fillNextHit(sequentialSlots, actorKey, proposal.hit),
      targetProposals: offer.proposal.targetProposals,
      coverInvites: offer.proposal.coverInvites,
    };
  } else {
    const target = targetProposalsOf(offer).find((entry) => entry.targetKey === actorKey);
    if (target && proposal.hit) target.hit = { ...target.hit, ...proposal.hit };
    offer.proposal = {
      ...offer.proposal,
      ...proposal,
      targetProposals: targetProposalsOf(offer),
      strikeProposals: offer.proposal.strikeProposals,
      coverInvites: offer.proposal.coverInvites,
    };
  }
  refreshWait(offer);
}

export async function createCheckOffer(gameId: number, data: CreateCheckOfferData): Promise<CheckOffer> {
  await delay();
  if (data.initiator === data.opponent) throw new Error('Нужен другой участник');
  if (
    data.proposal.spellCast &&
    [...offers.values()].some(
      (offer) =>
        offer.gameId === gameId &&
        offer.status === 'pending' &&
        offer.initiator === data.initiator &&
        offer.spellCastReservation?.status === 'reserved',
    )
  ) {
    throw new Error('У персонажа уже есть незавершённое предложение сотворения');
  }
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
  const strikeProposals =
    sequentialStrikeOfferService.isMultiStrike(data.proposal) && hit
      ? sequentialStrikeOfferService.createSlots(data.proposal.attackAction?.strikes ?? [], hit)
      : undefined;
  const coverInvites = data.proposal.coverInvites ?? [];
  const pendingCoverers = coveringService.pendingInvites(coverInvites).map((invite) => invite.coveringKey);
  const offer: CheckOffer = {
    id: nextId++,
    gameId,
    checkCode: data.checkCode,
    initiator: data.initiator,
    opponent: data.opponent,
    proposal: { ...data.proposal, targetProposals, strikeProposals, coverInvites },
    waitingOn: pendingCoverers.length ? 'covering' : 'opponent',
    waitingOnCoverers: pendingCoverers,
    waitingOnTargets: pendingCoverers.length
      ? []
      : strikeProposals
        ? sequentialStrikeOfferService.pendingTargetKeys(strikeProposals)
        : targetProposals?.map((target) => target.targetKey),
    status: 'pending',
    updatedAt: new Date().toISOString(),
  };
  syncSpellCastReservation(offer);
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
  if (role === 'covering' && offer.waitingOn !== 'covering') throw new Error('Сейчас ход другой стороны');
  if (role === 'opponent' && offer.waitingOnTargets && !offer.waitingOnTargets.includes(actorKey))
    throw new Error('Сейчас ход другой стороны');
  if (role === 'initiator' && offer.waitingOn !== role) throw new Error('Сейчас ход другой стороны');
  if (role === 'covering') {
    applyCoverProposal(offer, actorKey, proposal);
    offer.waitingOn = 'initiator';
    offer.waitingOnCoverers = pendingCoverersOf(offer);
    offer.waitingOnTargets = [];
  } else if (role === 'opponent' && offer.waitingOnTargets) {
    applyOpponentProposal(offer, actorKey, proposal);
  } else {
    offer.proposal = {
      ...proposal,
      targetProposals: offer.proposal.targetProposals,
      strikeProposals: offer.proposal.strikeProposals,
    };
    offer.waitingOn = role === 'initiator' ? (pendingCoverersOf(offer).length ? 'covering' : 'opponent') : 'initiator';
    if (offer.waitingOn === 'covering') offer.waitingOnCoverers = pendingCoverersOf(offer);
  }
  syncSpellCastReservation(offer);
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
  if (role === 'covering' && offer.waitingOn !== 'covering') throw new Error('Сейчас ход другой стороны');
  if (role === 'opponent' && offer.waitingOnTargets && !offer.waitingOnTargets.includes(actorKey))
    throw new Error('Сейчас ход другой стороны');
  if (role === 'initiator' && offer.waitingOn !== role) throw new Error('Сейчас ход другой стороны');
  if (proposal) {
    if (role === 'covering') {
      applyCoverProposal(offer, actorKey, proposal);
    } else if (role === 'opponent' && offer.waitingOnTargets) {
      applyOpponentProposal(offer, actorKey, proposal);
    } else {
      offer.proposal = {
        ...proposal,
        targetProposals: offer.proposal.targetProposals,
        strikeProposals: offer.proposal.strikeProposals,
        coverInvites: proposal.coverInvites ?? offer.proposal.coverInvites,
      };
    }
  }
  syncSpellCastReservation(offer);
  refreshWait(offer);
  if (pendingCoverersOf(offer).length || offer.waitingOnTargets?.length) return snapshot(offer);
  offer.status = 'accepted';
  if (offer.spellCastReservation) offer.spellCastReservation.status = 'committed';
  offer.updatedAt = new Date().toISOString();

  return snapshot(offer);
}

export async function cancelCheckOffer(offerId: number, actorKey: CombatEntityKey): Promise<CheckOffer> {
  await delay();
  const offer = requirePending(offerId);
  actorRole(offer, actorKey);
  offer.status = 'cancelled';
  if (offer.spellCastReservation) offer.spellCastReservation.status = 'released';
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
        ((offer.waitingOn === 'covering' && offer.waitingOnCoverers?.includes(entityKey)) ||
          (offer.waitingOn === 'opponent' &&
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
          offer.waitingOnCoverers?.includes(entityKey) ||
          offer.waitingOnTargets?.includes(entityKey) ||
          targetProposalsOf(offer).some((target) => target.targetKey === entityKey) ||
          reactionSlotsOf(offer).some((slot) => slot.targetKey === entityKey)),
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
