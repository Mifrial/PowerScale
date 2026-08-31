import { describe, expect, it } from 'vitest';
import {
  gameCharacterMemberships,
  fetchGameCharacters,
  submitCharacter,
  createGameCharacter,
  moderateCharacter,
  leaveGame,
  updateMembershipVisibility,
  updateCharacterGrants,
  submitCharacterMigration,
  fetchCharacterGameContexts,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameDetails, stopGameSession } from '@/modules/Roleplay/Game/Mock/mockGames';
import { characters, versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { addCustomRule } from '@/modules/Roleplay/Character/Mock/mockCharacterUpdate';
import '@/modules/Roleplay/Game/Mock/mockCharacterSessionOverlay';
import {
  setCombatResource,
  combatKey,
  getStoredCombatOverlay,
  snapshotCombatOverlayStore,
  restoreCombatOverlayStore,
  writeOverlaySheet,
  clearCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { saveInitiative, fetchInitiative } from '@/modules/Roleplay/Game/Mock/mockGameInitiative';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { reactive } from 'vue';

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
  it('leave запрещён во время playing', async () => {
    await expect(leaveGame(2, 1)).rejects.toThrow('сессии');
  });

  it('submitCharacterMigration пишет actual; статус остаётся active', async () => {
    const migrated: CreateCharacterData['version'] = {
      ...versions[1],
      name: 'Торвин (новая ревизия)',
      rulesRevision: 5,
    };
    await expect(submitCharacterMigration(2, 1, migrated)).rejects.toThrow('сессии');
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
  it('во время активной сессии правило уходит в оверлей', async () => {
    const detail = await addCustomRule(1, { kind: 'item', name: 'Амулет дракона', description: 'Жаркое дыхание.' });
    expect(detail.version.customRules?.[0]).toBeUndefined();
    const chars = await fetchGameCharacters(2);
    const torvin = chars.find((membership) => membership.characterId === 1)!;
    expect(torvin.membershipStatus).toBe('active');
    expect(torvin.overlay?.sheet?.customRules?.[0]?.name).toBe('Амулет дракона');
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
  it('после stop overlay коммитится в actual; approved не меняется до approve', async () => {
    const detail = gameDetails.find((d) => d.game.id === 2)!;
    const charKey = combatKey('character', 1);
    const beforeApproved = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!.approvedCharacterVersion!;

    detail.game.status = 'playing';
    await setCombatResource(2, charKey, 'action-points', { base: 1, size: 0 });

    await stopGameSession(2, 'in_process');

    const membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('active');
    expect(membership.approvedCharacterVersion).toEqual(beforeApproved);
    expect(versions[1].resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 1, size: 0 });
    expect(membership.reviewState).toBe('changes_pending');
    expect(getStoredCombatOverlay(2, charKey)).toBeNull();

    const approved = await moderateCharacter(2, 1, 'approve');
    expect(approved.reviewState).toBe('clean');
    expect(approved.approvedCharacterVersion?.resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({
      base: 1,
      size: 0,
    });
  });

  it('stopGameSession на non-playing бросает', async () => {
    await expect(stopGameSession(1, 'in_process')).rejects.toThrow('Сессия не активна');
  });

  it('ошибка integrity на втором PC не мутирует actual, overlay, инициативу и playing', async () => {
    const detail = gameDetails.find((d) => d.game.id === 2)!;
    detail.game.status = 'playing';
    const charKey = combatKey('character', 1);
    const npcKey = combatKey('npc', 5);
    const beforeActual = cloneData(versions[1]);
    await setCombatResource(2, charKey, 'action-points', { base: 1, size: 0 });
    const npcOverlay: GameCombatOverlay = {
      gameId: 2,
      entityKey: npcKey,
      kind: 'npc',
      resources: [{ ruleCode: 'action-points', current: { base: 2, size: 0 } }],
      states: [],
      updatedAt: '2026-08-30T12:00:00.000Z',
    };
    restoreCombatOverlayStore(2, { ...snapshotCombatOverlayStore(2), [npcKey]: npcOverlay });
    await saveInitiative(2, {
      gameId: 2,
      active: true,
      participants: [{ id: 'character:1', name: 'Торвин', kind: 'character', entityId: 1 }],
      activeIndex: 0,
      round: 2,
      updatedAt: '',
    });

    const second = await createGameCharacter(2, {
      spaceId: 1,
      spaceCode: 'razrabotka',
      rulesRevision: 5,
      version: {
        name: 'Второй ПК',
        shortDescription: 'Краткое описание',
        fullDescription: null,
        spaceCode: 'razrabotka',
        rulesRevision: 5,
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
    });
    await moderateCharacter(2, second.characterId, 'approve');
    await writeOverlaySheet(2, combatKey('character', second.characterId), {
      ...versions[second.characterId],
      raceRuleCode: 'missing-rule-xyz',
    });

    await expect(stopGameSession(2, 'in_process')).rejects.toThrow('отсутствующие в ревизии');
    expect(gameDetails.find((d) => d.game.id === 2)?.game.status).toBe('playing');
    expect(versions[1]).toEqual(beforeActual);
    expect(getStoredCombatOverlay(2, charKey)?.resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({
      base: 1,
      size: 0,
    });
    expect(getStoredCombatOverlay(2, npcKey)?.resources[0]?.current).toEqual({ base: 2, size: 0 });
    const initiative = await fetchInitiative(2);
    expect(initiative.active).toBe(true);
    expect(initiative.round).toBe(2);
    clearCombatOverlay(2, combatKey('character', second.characterId));
    const extraIndex = gameCharacterMemberships.findIndex(
      (membership) => membership.characterId === second.characterId,
    );
    if (extraIndex >= 0) gameCharacterMemberships.splice(extraIndex, 1);
  });
});
