import { describe, expect, it } from 'vitest';
import {
  fetchCombatOverlays,
  setCombatResource,
  addCombatState,
  replaceCombatState,
  setCombatStateValue,
  removeCombatState,
  setCombatItemEquipped,
  setCombatItemOccupyHands,
  combatKey,
  combatOverlayHasChanges,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { getStoredCharacterVersion, versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';

const charKey = combatKey('character', 1);

function versionOf() {
  return getStoredCharacterVersion(1);
}

describe('mockGameCombatOverlays: фикстуры и пустые записи', () => {
  it('fetchCombatOverlays возвращает approved-персонажей и активных НПС; без изменений — пустые записи', async () => {
    const overlays = await fetchCombatOverlays(2);
    const keys = overlays.map((o) => o.entityKey).sort();

    expect(keys).toEqual([charKey, combatKey('npc', 5)].sort());
    expect(overlays.every((o) => o.updatedAt === '')).toBe(true);
  });

  it('fetchCombatOverlays для игры 1 включает active-персонажей и активных НПС', async () => {
    const overlays = await fetchCombatOverlays(1);
    const characterKeys = overlays.filter((o) => o.kind === 'character').map((o) => o.entityKey);
    const npcKeys = overlays
      .filter((o) => o.kind === 'npc')
      .map((o) => o.entityKey)
      .sort();
    const activeNpcs = gameNpcs.filter((npc) => npc.gameId === 1 && npc.status === 'active');

    expect(characterKeys).toContain(combatKey('character', 3));
    expect(characterKeys).not.toContain(combatKey('character', 4));
    expect(npcKeys).toEqual(activeNpcs.map((npc) => combatKey('npc', npc.id)).sort());
  });

  it('пустой оверлей не считается изменением (combatOverlayHasChanges = false)', async () => {
    const version = versionOf();
    expect(combatOverlayHasChanges(version, getStoredCombatOverlay(2, charKey))).toBe(false);
  });
});

describe('mockGameCombatOverlays: ресурсы', () => {
  it('setCombatResource пишет в actual; эффективное значение обновляется', async () => {
    const result = await setCombatResource(2, charKey, 'action-points', { base: 2, size: 0 });
    expect(result.status).toBe('applied');

    const effective = versionOf()!.resources;
    expect(effective.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 2, size: 0 });
    // Незатронутые ресурсы сохраняют версию.
    expect(effective.find((r) => r.ruleCode === 'spirit-energy')?.current).toEqual({ base: 3, size: -1 });
  });

  it('setCombatResource клампит значение к лимиту (0..limit)', async () => {
    await setCombatResource(2, charKey, 'action-points', { base: 99, size: 0 });
    expect(versionOf()!.resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 4, size: 0 });
    await setCombatResource(2, charKey, 'action-points', { base: -5, size: 0 });
    expect(versionOf()!.resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 0, size: 0 });
  });

  it('повторная запись ресурса обновляет переопределение (без дубликатов)', async () => {
    await setCombatResource(2, charKey, 'action-points', { base: 1, size: 0 });
    await setCombatResource(2, charKey, 'action-points', { base: 3, size: 0 });
    expect(versionOf()!.resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 3, size: 0 });
  });

  it('размерный ресурс (size -1): кламп идёт в базовых пунктах шкалы, а не в сплющенных', async () => {
    await setCombatResource(2, charKey, 'spirit-energy', { base: 1, size: -1 });
    expect(versionOf()!.resources.find((r) => r.ruleCode === 'spirit-energy')?.current).toEqual({ base: 1, size: -1 });
    await setCombatResource(2, charKey, 'spirit-energy', { base: 99, size: -1 });
    expect(versionOf()!.resources.find((r) => r.ruleCode === 'spirit-energy')?.current).toEqual({ base: 8, size: -1 });
    await setCombatResource(2, charKey, 'spirit-energy', { base: -3, size: -1 });
    expect(versionOf()!.resources.find((r) => r.ruleCode === 'spirit-energy')?.current).toEqual({ base: 0, size: -1 });
  });

  it('несуществующий ресурс/лист — ошибка', async () => {
    await expect(setCombatResource(2, charKey, 'rule-999', { base: 1, size: 0 })).rejects.toThrow('Ресурс не найден');
    await expect(setCombatResource(2, combatKey('npc', 1), 'action-points', { base: 1, size: 0 })).rejects.toThrow(
      'Лист участника не заполнен',
    );
  });
});

describe('mockGameCombatOverlays: состояния', () => {
  it('addCombatState засевает список из версии и добавляет состояние', async () => {
    const result = await addCombatState(2, charKey, { stateRuleCode: 'exhaustion', value: 3 });
    expect(versionOf()!.states).toContainEqual({ stateRuleCode: 'exhaustion', value: 3 });
    expect(result.status).toBe('applied');
  });

  it('setCombatStateValue меняет значение по индексу', async () => {
    await setCombatStateValue(2, charKey, 0, 7);
    expect(versionOf()!.states[0].value).toBe(7);
  });

  it('replaceCombatState меняет запись целиком', async () => {
    await replaceCombatState(2, charKey, 0, { stateRuleCode: 'exhaustion', value: 4, dotTurnsLeft: 2 });
    expect(versionOf()!.states[0]).toMatchObject({ value: 4, dotTurnsLeft: 2 });
  });

  it('removeCombatState удаляет состояние по индексу', async () => {
    const before = versionOf()!.states.length;
    await removeCombatState(2, charKey, 0);
    expect(versionOf()!.states.length).toBe(before - 1);
  });

  it('изменение состояния не записывает лист в transient overlay', async () => {
    await setCombatStateValue(2, charKey, 0, 9);
    const version = versionOf()!;
    const overlay = getStoredCombatOverlay(2, charKey)!;
    expect(combatOverlayHasChanges(version, overlay)).toBe(false);
    expect(version.states[0].value).toBe(9);
  });
});

describe('mockGameCombatOverlays: НПС actual', () => {
  it('setCombatResource для НПС пишет в npc.version', async () => {
    const npc = gameNpcs.find((n) => n.id === 5)!;
    npc.version = JSON.parse(JSON.stringify(versions[1])) as (typeof versions)[1];
    const before = npc.version.resources.find((r) => r.ruleCode === 'action-points')!.current;

    const result = await setCombatResource(2, combatKey('npc', 5), 'action-points', { base: 1, size: 0 });
    expect(result.status).toBe('applied');
    const after = npc.version.resources.find((r) => r.ruleCode === 'action-points')!;
    expect(after.current).toEqual({ base: 1, size: before.size });
  });

  it('addCombatState для НПС пишет в npc.version.states', async () => {
    const npc = gameNpcs.find((n) => n.id === 5)!;
    const before = npc.version!.states.length;
    await addCombatState(2, combatKey('npc', 5), { stateRuleCode: 'stunned', value: 1 });
    expect(npc.version!.states.length).toBe(before + 1);
    expect(npc.version!.states.at(-1)).toEqual({ stateRuleCode: 'stunned', value: 1 });
  });
});

describe('mockGameCombatOverlays: экипировка', () => {
  it('setCombatItemEquipped для персонажа пишет inventory в actual', async () => {
    const version = versionOf()!;
    const item = version.inventory[0];
    expect(item.equipped).toBe(true);

    const result = await setCombatItemEquipped(2, charKey, item.id, false);
    expect(result.status).toBe('applied');
    expect(getStoredCharacterVersion(1).inventory.find((entry) => entry.id === item.id)?.equipped).toBe(false);
    expect(combatOverlayHasChanges(version, getStoredCombatOverlay(2, charKey))).toBe(false);
    expect(version.inventory.find((entry) => entry.id === item.id)?.equipped).toBe(true);
  });

  it('setCombatItemEquipped для НПС пишет сразу в npc.version', async () => {
    const npc = gameNpcs.find((n) => n.id === 5)!;
    npc.version = JSON.parse(JSON.stringify(versions[1])) as (typeof versions)[1];
    const item = npc.version.inventory[0];
    const result = await setCombatItemEquipped(2, combatKey('npc', 5), item.id, false);
    expect(result.status).toBe('applied');
    expect(npc.version.inventory.find((entry) => entry.id === item.id)?.equipped).toBe(false);
  });

  it('setCombatItemOccupyHands для персонажа пишет occupyHands в actual', async () => {
    const version = getStoredCharacterVersion(1);
    const item = version.inventory[0];
    expect(item).toBeDefined();
    item.ruleCode = 'boevoy-posokh';
    item.occupyHands = 1;
    item.equipped = true;

    await setCombatItemEquipped(2, charKey, item.id, true);
    const result = await setCombatItemOccupyHands(2, charKey, item.id, 2);
    expect(result.status).toBe('applied');
    expect(getStoredCharacterVersion(1).inventory.find((entry) => entry.id === item.id)).toBeDefined();
  });
});
