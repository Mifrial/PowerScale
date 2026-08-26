import { describe, expect, it } from 'vitest';
import {
  gameCharacterMemberships,
  fetchGameCharacters,
  submitCharacter,
  createGameCharacter,
  moderateCharacter,
  updateMembershipVisibility,
  updateCharacterGrants,
  submitCharacterMigration,
  submitCombatChanges,
  syncCharacterVersionToMemberships,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameDetails, updateGame } from '@/modules/Roleplay/Game/Mock/mockGames';
import { characters, versions, fetchCharacter } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { addCustomRule } from '@/modules/Roleplay/Character/Mock/mockCharacterUpdate';
import '@/modules/Roleplay/Game/Mock/mockCharacterSessionOverlay';
import { fetchRevision } from '@/modules/Roleplay/Space/Mock/mockSpaces';
import {
  setCombatResource,
  combatKey,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { toCreateGameData } from '@/modules/Roleplay/Game/Utils/toCreateGameData';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';

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
      raceRuleId: null,
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
      expect(['pending', 'approved', 'rejected', 'left']).toContain(membership.membershipStatus);
      expect(['owner', 'gm', 'player']).toContain(membership.role);
    }
  });

  it('имена правил pending-версий резолвятся в ревизии игры (для diff модерации)', async () => {
    for (const membership of gameCharacterMemberships) {
      if (!membership.pendingVersion) continue;
      const detail = gameDetails.find((d) => d.game.id === membership.gameId);
      expect(detail, `game ${membership.gameId}`).toBeDefined();
      if (!detail) continue;
      // Персонаж, ожидающий перевода на новую ревизию игры, ссылается на правила СВОЕЙ ревизии — пропускаем.
      if (membership.activeVersion && membership.activeVersion.rulesRevision !== detail.game.rulesRevision) continue;
      const revision = await fetchRevision(detail.game.spaceId, detail.game.rulesRevision);
      const names = new Set(revision.rules.map((rule) => rule.id));
      const missing: string[] = [];
      const version = membership.pendingVersion;
      for (const characteristic of version.characteristics)
        if (!names.has(characteristic.ruleId)) missing.push(`char ${characteristic.ruleId}`);
      for (const ability of version.abilities)
        if (!names.has(ability.ruleId)) missing.push(`ability ${ability.ruleId}`);
      for (const resource of version.resources) if (!names.has(resource.ruleId)) missing.push(`res ${resource.ruleId}`);
      for (const item of version.inventory) {
        if (item.ruleId !== null && !names.has(item.ruleId)) missing.push(`item ${item.ruleId}`);
      }
      for (const state of version.states) if (!names.has(state.stateRuleId)) missing.push(`state ${state.stateRuleId}`);
      for (const sense of version.senses) if (!names.has(sense.ruleId)) missing.push(`sense ${sense.ruleId}`);
      expect(missing, `game ${membership.gameId} character ${membership.characterId}`).toEqual([]);
    }
  });

  it('fetchGameCharacters возвращает членства только нужной игры', async () => {
    const game1 = await fetchGameCharacters(1);

    expect(game1.every((membership) => membership.gameId === 1)).toBe(true);
  });
});

describe('mockGameMemberships: создание персонажа «через игру»', () => {
  it('createGameCharacter создаёт персонажа и pending-членство', async () => {
    const membership = await createGameCharacter(1, makeCreateData('Новый герой'));
    expect(membership.gameId).toBe(1);
    expect(membership.characterName).toBe('Новый герой');
    expect(membership.characterOwnerId).toBe(1);
    expect(membership.membershipStatus).toBe('pending');
    expect(membership.activeVersion).toBeNull();
    expect(membership.pendingVersion).not.toBeNull();
    expect(characters.some((c) => c.id === membership.characterId && c.status === 'ready')).toBe(true);
  });

  it('createGameCharacter создаёт уникального персонажа (не конфликтует с существующими)', async () => {
    const first = await createGameCharacter(1, makeCreateData('Первый'));
    const second = await createGameCharacter(1, makeCreateData('Второй'));
    expect(first.characterId).not.toBe(second.characterId);
    const game1 = await fetchGameCharacters(1);
    expect(game1.filter((m) => m.characterId === first.characterId).length).toBe(1);
    expect(game1.filter((m) => m.characterId === second.characterId).length).toBe(1);
  });
});

describe('mockGameMemberships: подача и модерация', () => {
  it('подача готового персонажа другой ревизии сразу отклоняется', async () => {
    const membership = await submitCharacter(1, 1);
    expect(membership.membershipStatus).toBe('rejected');
    expect(membership.pendingVersion).toBeNull();
  });

  it('подача черновика запрещена', () => {
    return expect(submitCharacter(1, 2)).rejects.toThrow('готового');
  });

  it('повторная подача approved запрещена', async () => {
    await expect(submitCharacter(2, 1)).rejects.toThrow('уже связан');
  });

  it('approve чужой ревизии запрещён', async () => {
    await expect(moderateCharacter(1, 3, 'approve')).rejects.toThrow('Ревизия персонажа не совпадает');
  });

  it('отклонённого можно подать снова, если ревизия совпадает', async () => {
    versions[3] = { ...versions[3], rulesRevision: 12 };
    const again = await submitCharacter(1, 3);
    expect(again.membershipStatus).toBe('approved');
    expect(again.pendingVersion).toBeNull();
    expect(again.activeVersion?.rulesRevision).toBe(12);
  });

  it('подача деактивированного персонажа запрещена', async () => {
    await expect(submitCharacter(1, 5)).rejects.toThrow('деактивирован');
  });

  it('approve: pending становится active', async () => {
    const submitted = await submitCharacter(5, 1);
    const moderated = await moderateCharacter(5, 1, 'approve');

    expect(moderated.membershipStatus).toBe('approved');
    expect(moderated.pendingVersion).toBeNull();
    expect(moderated.activeVersion).toEqual(submitted.pendingVersion);
  });

  it('reject: pending сбрасывается, статус rejected', async () => {
    const moderated = await moderateCharacter(1, 4, 'reject');

    expect(moderated.membershipStatus).toBe('rejected');
    expect(moderated.pendingVersion).toBeNull();
  });

  it('членства несут зеркало видимости персонажа (источник — character.visibility)', async () => {
    const game1 = await fetchGameCharacters(1);
    for (const membership of game1) {
      const character = characters.find((c) => c.id === membership.characterId);
      expect(membership.visibility).toEqual(character?.visibility);
    }
  });

  it('updateMembershipVisibility меняет видимость листа (общую)', async () => {
    const updated = await updateMembershipVisibility(1, 3, []);
    expect(updated.visibility).toEqual([]);
    // видимость общая — изменился и сам персонаж
    expect(characters.find((c) => c.id === 3)?.visibility).toEqual([]);
  });
});

describe('mockGameMemberships: бонусные очки от ГМ', () => {
  it('updateCharacterGrants выставляет os/or/ol бонусы на членство', async () => {
    const updated = await updateCharacterGrants(1, 3, { osBonus: 2, orBonus: 5, olBonus: 1 });
    expect(updated.osBonus).toBe(2);
    expect(updated.orBonus).toBe(5);
    expect(updated.olBonus).toBe(1);
    const fetched = await fetchGameCharacters(1);
    const membership = fetched.find((m) => m.characterId === 3);
    expect(membership?.osBonus).toBe(2);
    expect(membership?.orBonus).toBe(5);
    expect(membership?.olBonus).toBe(1);
  });

  it('гранты на несуществующее членство запрещены', async () => {
    await expect(updateCharacterGrants(1, 999, { osBonus: 0, orBonus: 0, olBonus: 0 })).rejects.toThrow(
      'Членство не найдено',
    );
  });
});

describe('mockGameMemberships: миграция персонажа в игре', () => {
  it('submitCharacterMigration ставит версию в pending на модерацию (active остаётся замороженным)', async () => {
    const migrated: CreateCharacterData['version'] = {
      ...versions[1],
      name: 'Торвин (новая ревизия)',
      rulesRevision: 5,
    };
    const updated = await submitCharacterMigration(2, 1, migrated);
    expect(updated.membershipStatus).toBe('pending');
    expect(updated.pendingVersion?.name).toBe('Торвин (новая ревизия)');
    expect(updated.pendingVersion?.rulesRevision).toBe(5);
    // Approved-версия заморожена и не меняется до approve.
    expect(updated.activeVersion?.rulesRevision).toBe(5);
  });

  it('пустой diff миграции принимается сразу', async () => {
    const chars = await fetchGameCharacters(2);
    const torvin = chars.find((membership) => membership.characterId === 1);
    if (torvin?.membershipStatus === 'pending') await moderateCharacter(2, 1, 'approve');
    const active = (await fetchGameCharacters(2)).find((membership) => membership.characterId === 1)?.activeVersion;
    expect(active).toBeDefined();
    if (!active) return;
    const migrated = { ...active, budgets: { osTotal: 1, moneyBudget: 0 } };
    const updated = await submitCharacterMigration(2, 1, migrated);
    expect(updated.membershipStatus).toBe('approved');
    expect(updated.pendingVersion).toBeNull();
    expect(updated.activeVersion?.budgets).toEqual({ osTotal: 1, moneyBudget: 0 });
  });
});

describe('mockGameMemberships: выдача кастомного правила (роутер, модель версий — Баг 1)', () => {
  it('во время активной сессии правило уходит в оверлей (approved не трогается, latest чист)', async () => {
    // Торвин (игра 2 — играется) был переведён миграционным тестом в pending — возвращаем approved.
    // Оверлей пуст, поэтому guard активной сессии не срабатывает.
    await moderateCharacter(2, 1, 'approve');

    const detail = await addCustomRule(1, { kind: 'item', name: 'Амулет дракона', description: 'Жаркое дыхание.' });

    // Карточка (latest) не тронута — запись живёт в сессионном оверлее.
    expect(detail.version.customRules?.[0]).toBeUndefined();

    const chars = await fetchGameCharacters(2);
    const torvin = chars.find((membership) => membership.characterId === 1)!;
    expect(torvin.membershipStatus).toBe('approved');
    expect(torvin.pendingVersion).toBeNull();
    // Игра видит правило через оверлей (approved + overlay).
    expect(torvin.overlay?.sheet?.customRules?.[0]?.name).toBe('Амулет дракона');
  });

  it('вне сессии правило идёт в latest с автоподачей; approve делает его активной версией', async () => {
    // Гаррик (игра 1 — не играется): миграция + approve, затем выдача правила → автоподача → approve.
    const migrated = { ...versions[3], rulesRevision: 12, customRules: undefined };
    await submitCharacterMigration(1, 3, migrated);
    await moderateCharacter(1, 3, 'approve');
    let chars = await fetchGameCharacters(1);
    let garrick = chars.find((membership) => membership.characterId === 3)!;
    expect(garrick.membershipStatus).toBe('approved');
    expect(garrick.activeVersion?.rulesRevision).toBe(12);

    const detail = await addCustomRule(3, { kind: 'item', name: 'Лаваш', description: 'Большой' });
    expect(detail.version.customRules?.[0]?.name).toBe('Лаваш');

    // Вне сессии выдача автоподаёт на модерацию (pending = latest).
    chars = await fetchGameCharacters(1);
    garrick = chars.find((membership) => membership.characterId === 3)!;
    expect(garrick.membershipStatus).toBe('pending');

    await moderateCharacter(1, 3, 'approve');
    chars = await fetchGameCharacters(1);
    garrick = chars.find((membership) => membership.characterId === 3)!;
    expect(garrick.membershipStatus).toBe('approved');
    expect(garrick.activeVersion?.rulesRevision).toBe(12);
    expect(garrick.activeVersion?.customRules?.[0]?.name).toBe('Лаваш');

    // Лист тоже видит новую версию (ревизию и кастом-запись) — `details[id].version` перепривязан.
    const sheet = await fetchCharacter(3);
    expect(sheet.version.rulesRevision).toBe(12);
    expect(sheet.version.customRules?.[0]?.name).toBe('Лаваш');
  });
});

describe('mockGameMemberships: остановка сессии (модель версий — Баг 1)', () => {
  it('approved-персонаж после остановки: activeVersion сохранён, pending собран, статус pending; approve фиксирует', async () => {
    const detail = gameDetails.find((d) => d.game.id === 2)!;
    const charKey = combatKey('character', 1);
    // Сброс в approved вне сессии (guard не сработает): предыдущие тесты могли оставить оверлей/статус.
    detail.game.status = 'in_process';
    await moderateCharacter(2, 1, 'approve');
    const beforeActive = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!.activeVersion!;

    // In-game правка (оверлей) — ресурс персонажа.
    detail.game.status = 'playing';
    await setCombatResource(2, charKey, 'rule-18', { base: 1, size: 0 });

    // Остановка сессии: updateGame → in_process + submitCombatChanges.
    await updateGame(2, { ...toCreateGameData(detail), status: 'in_process' });
    await submitCombatChanges(2);

    const chars = await fetchGameCharacters(2);
    const membership = chars.find((m) => m.characterId === 1)!;
    // Approved-версия не затирается — ощущение «исчезновения» ложное.
    expect(membership.membershipStatus).toBe('pending');
    expect(membership.activeVersion).not.toBeNull();
    expect(membership.activeVersion).toEqual(beforeActive);
    // Правка собрана в pending (боевой ресурс из оверлея).
    expect(membership.pendingVersion?.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({
      base: 1,
      size: 0,
    });

    // Approve: активной становится собранная версия, статус approved, оверлей очищен.
    const approved = await moderateCharacter(2, 1, 'approve');
    expect(approved.membershipStatus).toBe('approved');
    expect(approved.pendingVersion).toBeNull();
    expect(approved.activeVersion?.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({
      base: 1,
      size: 0,
    });
  });

  it('queued pending можно одобрить во время новой сессии; живой оверлей не сбрасывается', async () => {
    const detail = gameDetails.find((d) => d.game.id === 2)!;
    const charKey = combatKey('character', 1);
    detail.game.status = 'in_process';
    const current = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    if (current.membershipStatus === 'pending') await moderateCharacter(2, 1, 'approve');

    versions[1] = { ...versions[1], money: 4242 };
    syncCharacterVersionToMemberships(1);
    expect((await fetchGameCharacters(2)).find((m) => m.characterId === 1)?.membershipStatus).toBe('pending');

    detail.game.status = 'playing';
    await setCombatResource(2, charKey, 'rule-18', { base: 1, size: 0 });

    const approved = await moderateCharacter(2, 1, 'approve');
    expect(approved.membershipStatus).toBe('approved');
    expect(approved.activeVersion?.money).toBe(4242);
    expect(getStoredCombatOverlay(2, charKey)?.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({
      base: 1,
      size: 0,
    });
  });
});
