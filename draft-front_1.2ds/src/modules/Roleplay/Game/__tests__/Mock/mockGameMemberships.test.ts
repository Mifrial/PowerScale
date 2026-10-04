import { describe, expect, it } from 'vitest';
import {
  gameCharacterMemberships,
  fetchGameCharacters,
  submitCharacter,
  createGameCharacter,
  moderateCharacter,
  moderateCharacterCommand,
  leaveGame,
  updateMembershipVisibility,
  updateCharacterGrants,
  submitCharacterMigration,
  fetchCharacterGameContexts,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameDetails, stopGameSession } from '@/modules/Roleplay/Game/Mock/mockGames';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import { characters, getCharacterActualVersion, versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { addCustomRule } from '@/modules/Roleplay/Character/Mock/mockCharacterUpdate';
import '@/modules/Roleplay/Game/Mock/mockCharacterSessionRuntimePort';
import {
  setCombatResource,
  combatKey,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import { reactive } from 'vue';
import {
  clearMockGameState,
  configureMockGameState,
  startGameSession,
} from '@/modules/Roleplay/Game/Mock/mockGameState';

const gameIds = new Set(gameDetails.map((detail) => detail.game.id));
const characterIds = new Set(characters.map((character) => character.id));

function makeCreateData(name: string): CreateCharacterData {
  return {
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 12,
    version: {
      name,
      shortDescription: 'Краткое описание',
      fullDescription: null,
      spaceCode: 'actual',
      rulesRevision: 12,
      raceRuleCode: null,
      characteristics: [],
      resources: [],
      abilities: [],
      points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
      money: 0,
      ageYears: null,
      inventory: [],
      states: [],
      senses: [],
    },
    status: 'ready',
  };
}

async function openSession(gameId: number): Promise<void> {
  const started = await startGameSession({
    commandId: createRandomId(),
    commandType: 'startSession',
    gameId,
    sessionId: null,
    battleId: null,
    participantAdmission: 'currentEligible',
    payload: {},
  });
  if (started.kind === 'conflict') throw new Error(started.conflict.code);
}

describe('mockGameMemberships: согласованность фикстур', () => {
  it('gameId и characterId существуют в моках', () => {
    for (const membership of gameCharacterMemberships) {
      expect(gameIds.has(membership.gameId), `gameId ${membership.gameId}`).toBe(true);
      expect(characterIds.has(membership.characterId), `characterId ${membership.characterId}`).toBe(true);
    }
  });

  it('фикстуры имеют валидные статусы членства и роли', () => {
    for (const membership of gameCharacterMemberships) {
      expect(['submitted', 'active', 'left']).toContain(membership.membershipStatus);
      expect(['owner', 'gm', 'player']).toContain(membership.role);
    }
  });

  it('fetchGameCharacters возвращает членства только нужной игры', async () => {
    const game1 = await fetchGameCharacters(1);

    expect(game1.every((membership) => membership.gameId === 1)).toBe(true);
  });
});

describe('mockGameMemberships: создание персонажа «через игру»', () => {
  it('createGameCharacter создаёт персонажа и submitted-членство', async () => {
    const membership = await createGameCharacter(1, makeCreateData('Новый герой'));
    expect(membership.gameId).toBe(1);
    expect(membership.characterName).toBe('Новый герой');
    expect(membership.characterOwnerId).toBe(1);
    expect(membership.membershipStatus).toBe('submitted');
    expect(membership.approvedCharacterVersion).toBeNull();
    expect(characters.some((c) => c.id === membership.characterId && c.status === 'ready')).toBe(true);
  });

  it('createGameCharacter создаёт уникального персонажа', async () => {
    const first = await createGameCharacter(1, makeCreateData('Первый'));
    const second = await createGameCharacter(1, makeCreateData('Второй'));
    expect(first.characterId).not.toBe(second.characterId);
  });
});

describe('mockGameMemberships: подача и модерация', () => {
  it('подача черновика запрещена', () => {
    return expect(submitCharacter(1, 2)).rejects.toThrow('готового');
  });

  it('повторная подача active запрещена', async () => {
    await expect(submitCharacter(2, 1)).rejects.toThrow('уже связан');
  });

  it('второй non-left membership запрещён', async () => {
    await expect(submitCharacter(1, 1)).rejects.toThrow('уже связан');
  });

  it('approve чужой ревизии запрещён', async () => {
    await expect(moderateCharacter(1, 3, 'approve')).rejects.toThrow('Ревизия персонажа не совпадает');
  });

  it('подача деактивированного персонажа запрещена', async () => {
    await expect(submitCharacter(1, 5)).rejects.toThrow('деактивирован');
  });

  it('approve: submitted становится active со снимком actual', async () => {
    const membership = await createGameCharacter(1, makeCreateData('Кандидат'));
    const moderated = await moderateCharacter(1, membership.characterId, 'approve');

    expect(moderated.membershipStatus).toBe('active');
    expect(moderated.approvedCharacterVersion?.name).toBe('Кандидат');
  });

  it('approve клонирует Vue-прокси actual', async () => {
    const membership = await createGameCharacter(1, makeCreateData('Прокси-лист'));
    versions[membership.characterId] = reactive(versions[membership.characterId]!);
    const moderated = await moderateCharacter(1, membership.characterId, 'approve');
    expect(moderated.membershipStatus).toBe('active');
    expect(moderated.approvedCharacterVersion?.name).toBe('Прокси-лист');
  });

  it('rejectApplication удаляет только submitted', async () => {
    const created = await createGameCharacter(1, makeCreateData('На отклонение'));
    await moderateCharacter(1, created.characterId, 'rejectApplication');
    const left = (await fetchGameCharacters(1)).find((membership) => membership.characterId === created.characterId);
    expect(left).toBeUndefined();
  });

  it('rejectApplication нельзя для active', async () => {
    await expect(moderateCharacter(2, 1, 'rejectApplication')).rejects.toThrow('Отклонить можно только заявку');
  });

  it('членства несут зеркало видимости персонажа', async () => {
    const game1 = await fetchGameCharacters(1);
    for (const membership of game1) {
      const character = characters.find((c) => c.id === membership.characterId);
      expect(membership.visibility).toEqual(character?.visibility);
    }
  });

  it('updateMembershipVisibility меняет видимость листа', async () => {
    const updated = await updateMembershipVisibility(1, 3, []);
    expect(updated.visibility).toEqual([]);
    expect(characters.find((c) => c.id === 3)?.visibility).toEqual([]);
  });

  it('getCharacterGameContexts — не-left максимум одна игра', async () => {
    const contexts = await fetchCharacterGameContexts(1);
    expect(contexts.length).toBeLessThanOrEqual(1);
  });
});

describe('mockGameMemberships: бонусные очки от ГМ', () => {
  it('updateCharacterGrants выставляет os/or/ol бонусы на членство', async () => {
    const updated = await updateCharacterGrants(1, 3, { osBonus: 2, orBonus: 5, olBonus: 1 });
    expect(updated.osBonus).toBe(2);
    expect(updated.orBonus).toBe(5);
    expect(updated.olBonus).toBe(1);
  });

  it('гранты на несуществующее членство запрещены', async () => {
    await expect(updateCharacterGrants(1, 999, { osBonus: 0, orBonus: 0, olBonus: 0 })).rejects.toThrow(
      'Членство не найдено',
    );
  });
});

describe('mockGameMemberships: leave и миграция', () => {
  it('leave запрещён, пока запущена сессия', async () => {
    await openSession(2);
    await expect(leaveGame(2, 1)).rejects.toThrow('сессии');
    clearMockGameState();
  });

  it('submitCharacterMigration пишет actual; статус остаётся active', async () => {
    const migrated: CreateCharacterData['version'] = {
      ...versions[1],
      name: 'Торвин (новая ревизия)',
      rulesRevision: 5,
    };
    await openSession(2);
    await expect(submitCharacterMigration(2, 1, migrated)).rejects.toThrow('сессии');
    clearMockGameState();
  });

  it('миграция чужого персонажа запрещена', async () => {
    const migrated = { ...versions[3], name: 'Гаррик (рев. 12)', rulesRevision: 12 };
    await expect(submitCharacterMigration(1, 3, migrated)).rejects.toThrow('владелец');
  });

  it('миграция left запрещена', async () => {
    const created = await createGameCharacter(1, makeCreateData('На выход'));
    await moderateCharacter(1, created.characterId, 'approve');
    await leaveGame(1, created.characterId);
    const migrated = { ...versions[created.characterId], rulesRevision: 12, spaceCode: 'actual' };
    await expect(submitCharacterMigration(1, created.characterId, migrated)).rejects.toThrow('активному');
  });

  it('миграция submitted запрещена', async () => {
    const migrated = { ...versions[4], spaceCode: 'actual', rulesRevision: 12 };
    await expect(submitCharacterMigration(1, 4, migrated)).rejects.toThrow('активному');
  });

  it('миграция на чужую ревизию запрещена', async () => {
    await mockLogin('admin', 'test');
    const migrated = { ...versions[3], name: 'Гаррик', rulesRevision: 5, spaceCode: 'razrabotka' };
    await expect(submitCharacterMigration(1, 3, migrated)).rejects.toThrow('Ревизия персонажа не совпадает');
    await mockLogout();
  });

  it('миграция со stale token запрещена', async () => {
    const created = await createGameCharacter(1, makeCreateData('С устаревшим токеном'));
    await moderateCharacter(1, created.characterId, 'approve');
    const migrated = { ...cloneData(versions[created.characterId]), name: 'Новое имя' };
    await expect(submitCharacterMigration(1, created.characterId, migrated, '"stale"')).rejects.toThrow('изменился');
  });

  it('миграция вне сессии меняет actual и даёт changes_pending', async () => {
    const created = await createGameCharacter(1, makeCreateData('До миграции'));
    await moderateCharacter(1, created.characterId, 'approve');
    const migrated = { ...cloneData(versions[created.characterId]), name: 'После миграции' };
    const token = JSON.stringify(cloneData(versions[created.characterId]));
    const updated = await submitCharacterMigration(1, created.characterId, migrated, token);
    expect(updated.membershipStatus).toBe('active');
    expect(updated.reviewState).toBe('changes_pending');
    expect(versions[created.characterId].name).toBe('После миграции');
    expect(updated.approvedCharacterVersion?.name).toBe('До миграции');
  });
});

describe('mockGameMemberships: кастомное правило', () => {
  it('во время активной сессии правило пишется в actual', async () => {
    const detail = await addCustomRule(1, { kind: 'item', name: 'Амулет дракона', description: 'Жаркое дыхание.' });
    expect(detail.version.customRules?.[0]?.name).toBe('Амулет дракона');
    const chars = await fetchGameCharacters(2);
    const torvin = chars.find((membership) => membership.characterId === 1)!;
    expect(torvin.membershipStatus).toBe('active');
    expect(versions[1].customRules?.[0]?.name).toBe('Амулет дракона');
  });

  it('вне сессии правило идёт в actual; approved заморожен', async () => {
    const detail = await addCustomRule(3, { kind: 'item', name: 'Лаваш', description: 'Большой' });
    expect(detail.version.customRules?.[0]?.name).toBe('Лаваш');
    const chars = await fetchGameCharacters(1);
    const garrick = chars.find((membership) => membership.characterId === 3)!;
    expect(garrick.membershipStatus).toBe('active');
    expect(garrick.reviewState).toBe('changes_pending');
    expect(garrick.approvedCharacterVersion?.customRules?.[0]?.name).not.toBe('Лаваш');
  });
});

describe('mockGameMemberships: остановка сессии', () => {
  it('после stop actual уже сохранён; approved не меняется до approve', async () => {
    const charKey = combatKey('character', 1);
    const beforeOverlay = getStoredCombatOverlay(2, charKey);
    const beforeApproved = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!.approvedCharacterVersion!;

    await openSession(2);
    await setCombatResource(2, charKey, 'action-points', { base: 1, size: 0 });

    await stopGameSession(2);

    const membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('active');
    expect(membership.approvedCharacterVersion).toEqual(beforeApproved);
    expect(versions[1].resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 1, size: 0 });
    expect(membership.reviewState).toBe('changes_pending');
    expect(getStoredCombatOverlay(2, charKey)).toEqual(beforeOverlay);

    const approved = await moderateCharacter(2, 1, 'approve');
    expect(approved.reviewState).toBe('clean');
    expect(approved.approvedCharacterVersion?.resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({
      base: 1,
      size: 0,
    });
  });

  it('stopGameSession без сессии бросает', async () => {
    clearMockGameState();
    await expect(stopGameSession(1)).rejects.toThrow('Сессия не активна');
  });

  it('stop не выполняет повторный full-sheet commit', async () => {
    await openSession(2);
    const charKey = combatKey('character', 1);
    const beforeOverlay = getStoredCombatOverlay(2, charKey);
    await setCombatResource(2, charKey, 'action-points', { base: 1, size: 0 });
    await stopGameSession(2);
    expect(versions[1].resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 1, size: 0 });
    expect(getStoredCombatOverlay(2, charKey)).toEqual(beforeOverlay);
  });
});

describe('mockGameMemberships: approve во время active session', () => {
  it('одобряет active membership через CAS и replay-ит тот же command', async () => {
    const created = await createGameCharacter(1, makeCreateData('Approve в сессии'));
    await moderateCharacter(1, created.characterId, 'approve');
    configureMockGameState();
    try {
      const session = await startGameSession({
        commandId: 'approve-session',
        commandType: 'startSession',
        gameId: 1,
        sessionId: null,
        battleId: null,
        participantEntityKeys: [`character:${created.characterId}`],
        payload: {},
      });
      if (session.kind !== 'transition') throw new Error('Session was not created');
      const membership = gameCharacterMemberships.find(
        (entry) => entry.gameId === 1 && entry.characterId === created.characterId,
      );
      if (!membership) throw new Error('Membership was not created');
      const command = {
        commandId: 'approve-active',
        gameId: 1,
        characterId: created.characterId,
        action: 'approve' as const,
        expectedActualVersion: getCharacterActualVersion(created.characterId),
        expectedMembershipRevision: membership.membershipRevision,
      };

      const approved = await moderateCharacterCommand(command);
      const replay = await moderateCharacterCommand(command);

      expect(approved.kind).toBe('transition');
      expect(replay).toEqual(approved);
      if (approved.kind === 'transition') expect(approved.membership.reviewState).toBe('clean');
    } finally {
      clearMockGameState();
    }
  });
});
