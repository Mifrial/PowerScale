import { describe, expect, it } from 'vitest';
import {
  gameLoot,
  fetchLoot,
  addLoot,
  updateLoot,
  handoutLoot,
  toggleLootInterest,
  distributeLoot,
  deleteLoot,
} from '@/modules/Roleplay/Game/Mock/mockGameLoot';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { gameDetails, stopGameSession } from '@/modules/Roleplay/Game/Mock/mockGames';
import { gameCharacterMemberships, moderateCharacter } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { getStoredCombatOverlay, combatKey } from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import type { CreateLootData } from '@/modules/Roleplay/Game/Dto/CreateLootData';

const gameIds = new Set(gameDetails.map((detail) => detail.game.id));

function membersOf(gameId: number): number[] {
  return gameDetails.find((detail) => detail.game.id === gameId)?.members.map((member) => member.userId) ?? [];
}

function charactersOf(gameId: number): number[] {
  return gameCharacterMemberships
    .filter((membership) => membership.gameId === gameId)
    .map((membership) => membership.characterId);
}

function npcIdsOf(gameId: number): number[] {
  return gameNpcs.filter((npc) => npc.gameId === gameId).map((npc) => npc.id);
}

function itemData(itemRuleCode: string, quantity = 1): CreateLootData {
  return { group: 'Группа', itemRuleCode, quantity, moneyAmount: null, notes: null };
}

function moneyData(moneyAmount: number): CreateLootData {
  return { group: null, itemRuleCode: null, quantity: 0, moneyAmount, notes: null };
}

describe('mockGameLoot: согласованность фикстур', () => {
  it('gameId, интересы и получатели существуют в моках', () => {
    for (const loot of gameLoot) {
      expect(gameIds.has(loot.gameId), `gameId ${loot.gameId}`).toBe(true);
      const members = membersOf(loot.gameId);
      for (const userId of loot.interestedUserIds) {
        expect(members.includes(userId), `interest ${userId}`).toBe(true);
      }
      const npcIds = npcIdsOf(loot.gameId);
      for (const entry of loot.distribution) {
        if (entry.type === 'character') expect(charactersOf(loot.gameId).includes(entry.characterId ?? -1)).toBe(true);
        if (entry.type === 'npc') expect(npcIds.includes(entry.npcId ?? -1)).toBe(true);
      }
    }
  });

  it('денежная добыча: сумма долей раздачи не превышает сумму', () => {
    for (const loot of gameLoot) {
      if (loot.moneyAmount === null) continue;
      const total = loot.distribution.reduce((sum, entry) => sum + (entry.amount ?? 0), 0);
      expect(total).toBeLessThanOrEqual(loot.moneyAmount);
    }
  });
});

describe('mockGameLoot: создание и правка', () => {
  it('fetchLoot возвращает добычу только нужной игры', async () => {
    const game1 = await fetchLoot(1);
    expect(game1.every((loot) => loot.gameId === 1)).toBe(true);
    expect(game1.length).toBeGreaterThan(0);
  });

  it('addLoot создаёт лут в запасе', async () => {
    const loot = await addLoot(1, itemData('boevoy-posokh'));
    expect(loot.status).toBe('prepared');
    expect(loot.itemRuleCode).toBe('boevoy-posokh');
    expect(loot.distribution).toEqual([]);
  });

  it('addLoot требует ровно один из itemRuleCode/moneyAmount', async () => {
    await expect(
      addLoot(1, { group: null, itemRuleCode: 'boevoy-posokh', quantity: 1, moneyAmount: 100, notes: null }),
    ).rejects.toThrow('предметом или деньгами');
    await expect(
      addLoot(1, { group: null, itemRuleCode: null, quantity: 0, moneyAmount: null, notes: null }),
    ).rejects.toThrow('предметом или деньгами');
  });

  it('updateLoot правит только лут в запасе', async () => {
    const created = await addLoot(1, itemData('boevoy-posokh'));
    const updated = await updateLoot(created.id, moneyData(250));
    expect(updated.moneyAmount).toBe(250);
    expect(updated.itemRuleCode).toBeNull();

    const available = await addLoot(1, itemData('tekko-kagi'));
    await handoutLoot([available.id]);
    await expect(updateLoot(available.id, itemData('kusarigama'))).rejects.toThrow('в запасе');
  });
});

describe('mockGameLoot: выдача на разбор и интерес', () => {
  it('handoutLoot переводит prepared → available', async () => {
    const created = await addLoot(1, itemData('boevoy-posokh'));
    const [handed] = await handoutLoot([created.id]);
    expect(handed.status).toBe('available');
    await expect(handoutLoot([created.id])).rejects.toThrow('из запаса');
  });

  it('toggleLootInterest добавляет и снимает интерес текущего игрока', async () => {
    const created = await addLoot(1, moneyData(50));
    await handoutLoot([created.id]);
    const interested = await toggleLootInterest(created.id);
    expect(interested.interestedUserIds).toContain(1);
    const removed = await toggleLootInterest(created.id);
    expect(removed.interestedUserIds).not.toContain(1);
  });

  it('интерес возможен только к луту на разборе', async () => {
    const created = await addLoot(1, itemData('boevoy-posokh'));
    await expect(toggleLootInterest(created.id)).rejects.toThrow('на разборе');
  });
});

describe('mockGameLoot: раздача', () => {
  async function availableItem(): Promise<number> {
    const loot = await addLoot(1, itemData('boevoy-posokh'));
    await handoutLoot([loot.id]);

    return loot.id;
  }

  async function availableMoney(amount: number): Promise<number> {
    const loot = await addLoot(1, moneyData(amount));
    await handoutLoot([loot.id]);

    return loot.id;
  }

  it('предмет раздаётся одному получателю — персонажу игры', async () => {
    const id = await availableItem();
    const distributed = await distributeLoot(id, { distribution: [{ type: 'character', characterId: 4 }] });
    expect(distributed.status).toBe('distributed');
    expect(distributed.distribution).toEqual([
      { type: 'character', characterId: 4, characterName: expect.any(String), amount: null },
    ]);
  });

  it('предмет можно отдать НПС игры', async () => {
    const id = await availableItem();
    const distributed = await distributeLoot(id, { distribution: [{ type: 'npc', npcId: 1 }] });
    expect(distributed.distribution[0]).toMatchObject({ type: 'npc', npcId: 1, npcName: expect.any(String) });
  });

  it('предмет можно отдать «вникуда»', async () => {
    const id = await availableItem();
    const distributed = await distributeLoot(id, { distribution: [{ type: 'nowhere' }] });
    expect(distributed.distribution).toEqual([{ type: 'nowhere', amount: null }]);
  });

  it('предмет нельзя раздать нескольким получателям', async () => {
    const id = await availableItem();
    await expect(
      distributeLoot(id, {
        distribution: [
          { type: 'character', characterId: 4 },
          { type: 'npc', npcId: 1 },
        ],
      }),
    ).rejects.toThrow('одному получателю');
  });

  it('нельзя раздать персонажу не из этой игры или НПС другой игры', async () => {
    const id = await availableItem();
    await expect(distributeLoot(id, { distribution: [{ type: 'character', characterId: 1 }] })).rejects.toThrow(
      'не персонаж этой игры',
    );
    await expect(distributeLoot(id, { distribution: [{ type: 'npc', npcId: 5 }] })).rejects.toThrow('НПС не найден');
  });

  it('деньги делятся долями, остаток уходит «вникуда»', async () => {
    const id = await availableMoney(100);
    const distributed = await distributeLoot(id, {
      distribution: [
        { type: 'character', characterId: 4, amount: 40 },
        { type: 'npc', npcId: 1, amount: 25 },
      ],
    });
    expect(distributed.status).toBe('distributed');
    const total = distributed.distribution.reduce((sum, entry) => sum + (entry.amount ?? 0), 0);
    expect(total).toBe(100);
    expect(distributed.distribution).toContainEqual({ type: 'nowhere', amount: 35 });
  });

  it('сумма долей не может превышать сумму добычи', async () => {
    const id = await availableMoney(50);
    await expect(
      distributeLoot(id, { distribution: [{ type: 'character', characterId: 4, amount: 60 }] }),
    ).rejects.toThrow('превышает');
  });

  it('деньги записываются в лист персонажа', async () => {
    const before = versions[3].money;
    const id = await availableMoney(100);
    await distributeLoot(id, { distribution: [{ type: 'character', characterId: 3, amount: 50 }] });
    expect(versions[3].money).toBe(before + 50);
  });

  it('предмет записывается в инвентарь персонажа', async () => {
    const beforeCount = versions[4].inventory.length;
    const id = await availableItem();
    await distributeLoot(id, { distribution: [{ type: 'character', characterId: 4 }] });
    expect(versions[4].inventory.length).toBe(beforeCount + 1);
    expect(versions[4].inventory.at(-1)).toMatchObject({ ruleCode: 'boevoy-posokh', quantity: 1, equipped: false });
  });

  it('во время активной сессии добыча пишется в оверлей (approved заморожен, latest чист)', async () => {
    // Торвин (игра 2 — играется) approved; членство несёт замороженную approved-версию.
    const membership = gameCharacterMemberships.find((m) => m.gameId === 2 && m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('active');
    const beforeLatest = versions[1].money;

    const loot = await addLoot(2, moneyData(100));
    await handoutLoot([loot.id]);
    await distributeLoot(loot.id, { distribution: [{ type: 'character', characterId: 1, amount: 60 }] });

    // Latest не тронут, approved заморожен, деньги ушли в сессионный оверлей.
    expect(versions[1].money).toBe(beforeLatest);
    const stored = getStoredCombatOverlay(2, combatKey('character', 1));
    expect(stored?.sheet?.money).toBe(membership.approvedCharacterVersion!.money + 60);

    // После остановки сессии и approve деньги переходят в latest/approved.
    await stopGameSession(2, 'in_process');
    await moderateCharacter(2, 1, 'approve');
    expect(versions[1].money).toBe(beforeLatest + 60);
    expect(getStoredCombatOverlay(2, combatKey('character', 1))).toBeNull();
  });

  it('раздача НПС лениво инициализирует полный лист (Н1 → Н2)', async () => {
    const npc = gameNpcs.find((n) => n.id === 4);
    expect(npc?.version).toBeNull();
    const id = await availableMoney(50);
    await distributeLoot(id, { distribution: [{ type: 'npc', npcId: 4, amount: 25 }] });
    expect(npc?.version).not.toBeNull();
    expect(npc?.version?.money).toBe(25);
    expect(npc?.version?.spaceCode).toBe(gameDetails.find((d) => d.game.id === 1)?.game.spaceCode);
  });

  it('предмет НПС записывается в инвентарь его листа', async () => {
    const npc = gameNpcs.find((n) => n.id === 1);
    const id = await availableItem();
    await distributeLoot(id, { distribution: [{ type: 'npc', npcId: 1 }] });
    expect(npc?.version).not.toBeNull();
    expect(npc?.version?.inventory).toContainEqual(
      expect.objectContaining({ ruleCode: 'boevoy-posokh', quantity: 1, equipped: false }),
    );
  });
});

describe('mockGameLoot: удаление', () => {
  it('deleteLoot удаляет запись', async () => {
    const created = await addLoot(1, itemData('boevoy-posokh'));
    await deleteLoot(created.id);
    const all = await fetchLoot(1);
    expect(all.some((loot) => loot.id === created.id)).toBe(false);
  });
});
