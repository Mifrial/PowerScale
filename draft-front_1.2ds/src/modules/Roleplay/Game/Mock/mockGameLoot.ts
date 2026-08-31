import type { GameLoot, GameLootDistribution } from '@/modules/Roleplay/Game/Dto/GameLoot';
import type { CreateLootData } from '@/modules/Roleplay/Game/Dto/CreateLootData';
import type { DistributeLootData } from '@/modules/Roleplay/Game/Dto/DistributeLootData';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { versions, syncCharacterVersion } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import {
  gameCharacterMemberships,
  syncCharacterVersionToMemberships,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { sessionTarget, overlaySheetBase } from '@/modules/Roleplay/Character/Mock/mockCharacterUpdate';
import { combatKey, writeOverlaySheet } from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import '@/modules/Roleplay/Game/Mock/mockCharacterSessionOverlay';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

// Идентификаторы предметов инвентаря, добавляемых при раздаче добычи (за пределами фикстур).
let nextInventoryItemId = 1000;

/** Убеждается, что у НПС есть полный лист: минимальный — при отсутствии (полный лист — Н2). */
function ensureNpcVersion(npc: GameNpc): CharacterVersion {
  if (npc.version) return npc.version;
  const game = gameDetails.find((detail) => detail.game.id === npc.gameId)?.game;
  npc.version = {
    name: npc.name,
    shortDescription: npc.shortDescription,
    fullDescription: npc.fullDescription,
    spaceCode: game?.spaceCode ?? '',
    rulesRevision: game?.rulesRevision ?? 1,
    raceRuleCode: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
    money: 0,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
  };

  return npc.version;
}

// Добыча игр (ТР §8 «Добыча»). Инварианты: gameId — из mockGames, интересы — участники игры,
// получатели раздачи — участники игры / НПС игры / «вникуда»; itemRuleCode — предмет ревизии.
export const gameLoot: GameLoot[] = [
  {
    id: 1,
    gameId: 1,
    group: 'Тролльи холмы',
    itemRuleCode: 'boevoy-posokh',
    quantity: 1,
    moneyAmount: null,
    notes: 'Боевой посох из логова вождей.',
    status: 'prepared',
    interestedUserIds: [],
    distribution: [],
    createdAt: '2026-08-01T10:00:00',
    updatedAt: '2026-08-01T10:00:00',
  },
  {
    id: 2,
    gameId: 1,
    group: 'Тролльи холмы',
    itemRuleCode: 'kusarigama',
    quantity: 2,
    moneyAmount: null,
    notes: null,
    status: 'prepared',
    interestedUserIds: [],
    distribution: [],
    createdAt: '2026-08-01T10:05:00',
    updatedAt: '2026-08-01T10:05:00',
  },
  {
    id: 3,
    gameId: 1,
    group: 'Тролльи холмы',
    itemRuleCode: null,
    quantity: 0,
    moneyAmount: 100,
    notes: 'Казна набега.',
    status: 'available',
    interestedUserIds: [1, 4],
    distribution: [],
    createdAt: '2026-08-02T12:00:00',
    updatedAt: '2026-08-02T12:00:00',
  },
  {
    id: 4,
    gameId: 1,
    group: 'Сундук короля',
    itemRuleCode: 'tekko-kagi',
    quantity: 1,
    moneyAmount: null,
    notes: null,
    status: 'distributed',
    interestedUserIds: [1],
    distribution: [{ type: 'character', characterId: 3, characterName: 'Гаррик из Тени', amount: null }],
    createdAt: '2026-07-25T09:00:00',
    updatedAt: '2026-07-26T18:00:00',
  },
  {
    id: 5,
    gameId: 1,
    group: 'Сундук короля',
    itemRuleCode: null,
    quantity: 0,
    moneyAmount: 500,
    notes: 'Выкуп за заложников.',
    status: 'prepared',
    interestedUserIds: [],
    distribution: [],
    createdAt: '2026-08-03T09:00:00',
    updatedAt: '2026-08-03T09:00:00',
  },
  {
    id: 6,
    gameId: 2,
    group: null,
    itemRuleCode: 'boevoy-posokh',
    quantity: 1,
    moneyAmount: null,
    notes: 'Награда за курсовую работу.',
    status: 'available',
    interestedUserIds: [4],
    distribution: [],
    createdAt: '2026-08-05T11:00:00',
    updatedAt: '2026-08-05T11:00:00',
  },
];

let nextLootId = Math.max(0, ...gameLoot.map((loot) => loot.id)) + 1;

export async function fetchLoot(gameId: number, _signal?: AbortSignal): Promise<GameLoot[]> {
  await delay(150);

  return gameLoot.filter((loot) => loot.gameId === gameId);
}

function assertCreateData(data: CreateLootData): void {
  const hasItem = data.itemRuleCode !== null;
  const hasMoney = data.moneyAmount !== null;
  if (hasItem === hasMoney) throw new Error('Лут должен быть предметом или деньгами (не оба сразу)');
  if (hasItem && (!Number.isInteger(data.quantity) || data.quantity < 1))
    throw new Error('Количество предмета должно быть ≥ 1');
  if (hasMoney && data.moneyAmount !== null && data.moneyAmount <= 0) throw new Error('Сумма денег должна быть > 0');
}

function buildLoot(gameId: number, data: CreateLootData): GameLoot {
  const now = new Date().toISOString();

  return {
    id: nextLootId++,
    gameId,
    group: data.group,
    itemRuleCode: data.itemRuleCode,
    quantity: data.itemRuleCode !== null ? data.quantity : 0,
    moneyAmount: data.moneyAmount,
    notes: data.notes,
    status: 'prepared',
    interestedUserIds: [],
    distribution: [],
    createdAt: now,
    updatedAt: now,
  };
}

/** Создание записи добычи ведущим (в запас, prepared). */
export async function addLoot(gameId: number, data: CreateLootData, _signal?: AbortSignal): Promise<GameLoot> {
  await delay(200);
  assertCreateData(data);
  const loot = buildLoot(gameId, data);
  gameLoot.push(loot);

  return { ...loot };
}

/** Редактирование добычи ведущим (только пока она в запасе). */
export async function updateLoot(lootId: number, data: CreateLootData, _signal?: AbortSignal): Promise<GameLoot> {
  await delay(200);
  const idx = gameLoot.findIndex((loot) => loot.id === lootId);
  if (idx === -1) throw new Error('Добыча не найдена');
  if (gameLoot[idx].status !== 'prepared') throw new Error('Правка доступна только для лута в запасе');
  assertCreateData(data);
  gameLoot[idx] = { ...buildLoot(gameLoot[idx].gameId, data), id: lootId, updatedAt: new Date().toISOString() };

  return { ...gameLoot[idx] };
}

/** Выдача добычи «на разбор» ведущим: prepared → available. */
export async function handoutLoot(lootIds: number[], _signal?: AbortSignal): Promise<GameLoot[]> {
  await delay(200);
  const result: GameLoot[] = [];
  for (const lootId of lootIds) {
    const idx = gameLoot.findIndex((loot) => loot.id === lootId);
    if (idx === -1) throw new Error('Добыча не найдена');
    if (gameLoot[idx].status !== 'prepared') throw new Error('На разбор можно выдать только лут из запаса');
    gameLoot[idx] = { ...gameLoot[idx], status: 'available', updatedAt: new Date().toISOString() };
    result.push({ ...gameLoot[idx] });
  }

  return result;
}

/** Переключение интереса текущего игрока к луту «на разборе». */
export async function toggleLootInterest(lootId: number, _signal?: AbortSignal): Promise<GameLoot> {
  await delay(150);
  const idx = gameLoot.findIndex((loot) => loot.id === lootId);
  if (idx === -1) throw new Error('Добыча не найдена');
  const loot = gameLoot[idx];
  if (loot.status !== 'available') throw new Error('Интерес можно проявлять только к луту на разборе');
  const userId = getCurrentUserId();
  const interested = loot.interestedUserIds.includes(userId)
    ? loot.interestedUserIds.filter((id) => id !== userId)
    : [...loot.interestedUserIds, userId];
  gameLoot[idx] = { ...loot, interestedUserIds: interested, updatedAt: new Date().toISOString() };

  return { ...gameLoot[idx] };
}

/**
 * Фиксация раздачи добычи ведущим: доступные получатели — персонажи игры, НПС игры, «вникуда».
 * Деньги/предметы записываются в лист получателя (персонажа/НПС): деньги — в `money`,
 * предметы — в `inventory` (связь с карточкой через живые ссылки `versions[id]`/`npc.version`).
 */
export async function distributeLoot(
  lootId: number,
  data: DistributeLootData,
  _signal?: AbortSignal,
): Promise<GameLoot> {
  await delay(200);
  const idx = gameLoot.findIndex((loot) => loot.id === lootId);
  if (idx === -1) throw new Error('Добыча не найдена');
  const loot = gameLoot[idx];
  if (loot.status !== 'available') throw new Error('Раздавать можно только лут, выданный на разбор');

  const npcs = gameNpcs.filter((npc) => npc.gameId === loot.gameId);

  const resolved: GameLootDistribution[] = data.distribution.map((entry) => {
    if (entry.type === 'character') {
      const membership = gameCharacterMemberships.find(
        (membership) => membership.gameId === loot.gameId && membership.characterId === entry.characterId,
      );
      if (!membership) throw new Error('Получатель не персонаж этой игры');

      return {
        type: 'character',
        characterId: membership.characterId,
        characterName: membership.characterName,
        amount: entry.amount ?? null,
      };
    }
    if (entry.type === 'npc') {
      const npc = npcs.find((n) => n.id === entry.npcId);
      if (!npc) throw new Error('НПС не найден в игре');

      return { type: 'npc', npcId: npc.id, npcName: npc.name, amount: entry.amount ?? null };
    }

    return { type: 'nowhere', amount: entry.amount ?? null };
  });

  if (loot.itemRuleCode !== null) {
    if (resolved.length !== 1) throw new Error('Предмет раздаётся одному получателю');
  } else if (loot.moneyAmount !== null) {
    const total = resolved.reduce((sum, entry) => sum + (entry.amount ?? 0), 0);
    if (total > loot.moneyAmount) throw new Error('Сумма долей превышает сумму добычи');
    if (total < loot.moneyAmount) resolved.push({ type: 'nowhere', amount: loot.moneyAmount - total });
  }

  // Запись добычи в лист получателя. Во время активной сессии персонажа (approved + playing) —
  // в сессионный оверлей (игра читает approved + overlay), иначе — в latest с автоподачей.
  for (const entry of resolved) {
    const amount = entry.amount ?? null;
    if (entry.type === 'character') {
      const target = sessionTarget(entry.characterId ?? -1, loot.gameId);
      if (target) {
        const sheet = await overlaySheetBase(target, entry.characterId ?? -1);
        if (amount !== null) sheet.money += amount;
        if (loot.itemRuleCode !== null) {
          sheet.inventory.push({
            id: nextInventoryItemId++,
            ruleCode: loot.itemRuleCode,
            quantity: loot.quantity,
            equipped: false,
          });
        }
        await writeOverlaySheet(target.gameId, combatKey('character', entry.characterId ?? -1), sheet);
        continue;
      }
      const version = versions[entry.characterId ?? -1];
      if (version) {
        if (amount !== null) version.money += amount;
        if (loot.itemRuleCode !== null) {
          version.inventory.push({
            id: nextInventoryItemId++,
            ruleCode: loot.itemRuleCode,
            quantity: loot.quantity,
            equipped: false,
          });
        }
        syncCharacterVersion(entry.characterId ?? -1);
        syncCharacterVersionToMemberships(entry.characterId ?? -1);
      }
    } else if (entry.type === 'npc' && entry.npcId !== undefined) {
      const npc = npcs.find((n) => n.id === entry.npcId);
      if (!npc) continue;
      const version = ensureNpcVersion(npc);
      if (amount !== null) version.money += amount;
      if (loot.itemRuleCode !== null) {
        version.inventory.push({
          id: nextInventoryItemId++,
          ruleCode: loot.itemRuleCode,
          quantity: loot.quantity,
          equipped: false,
        });
      }
    }
  }

  gameLoot[idx] = { ...loot, status: 'distributed', distribution: resolved, updatedAt: new Date().toISOString() };

  return { ...gameLoot[idx] };
}

export async function deleteLoot(lootId: number, _signal?: AbortSignal): Promise<void> {
  await delay(200);
  const idx = gameLoot.findIndex((loot) => loot.id === lootId);
  if (idx === -1) throw new Error('Добыча не найдена');
  gameLoot.splice(idx, 1);
}
