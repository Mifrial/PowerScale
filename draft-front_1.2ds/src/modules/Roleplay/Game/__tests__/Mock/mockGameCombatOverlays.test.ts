import { describe, expect, it } from 'vitest';
import {
  fetchCombatOverlays,
  setCombatResource,
  addCombatState,
  setCombatStateValue,
  removeCombatState,
  setCombatItemEquipped,
  combatKey,
  combatOverlayHasChanges,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { effectiveResources, effectiveStates } from '@/modules/Roleplay/Game/Utils/combatEffectiveState';

const charKey = combatKey('character', 1);

function versionOf() {
  return gameCharacterMemberships.find((m) => m.gameId === 2 && m.characterId === 1)?.activeVersion ?? null;
}

describe('mockGameCombatOverlays: фикстуры и пустые записи', () => {
  it('fetchCombatOverlays возвращает approved-персонажей и активных НПС; без изменений — пустые записи', async () => {
    const overlays = await fetchCombatOverlays(2);
    const keys = overlays.map((o) => o.entityKey).sort();

    expect(keys).toEqual([charKey, combatKey('npc', 5)].sort());
    expect(overlays.every((o) => o.updatedAt === '')).toBe(true);
  });

  it('fetchCombatOverlays для игры без approved-персонажей возвращает только активных НПС', async () => {
    const overlays = await fetchCombatOverlays(1);
    const npcKeys = overlays
      .filter((o) => o.kind === 'npc')
      .map((o) => o.entityKey)
      .sort();
    const activeNpcs = gameNpcs.filter((npc) => npc.gameId === 1 && npc.status === 'active');

    expect(overlays.every((o) => o.kind === 'npc')).toBe(true);
    expect(npcKeys).toEqual(activeNpcs.map((npc) => combatKey('npc', npc.id)).sort());
  });

  it('пустой оверлей не считается изменением (combatOverlayHasChanges = false)', async () => {
    const version = versionOf();
    expect(combatOverlayHasChanges(version, getStoredCombatOverlay(2, charKey))).toBe(false);
  });
});

describe('mockGameCombatOverlays: ресурсы', () => {
  it('setCombatResource пишет переопределение в оверлей; эффективное значение обновляется', async () => {
    const overlay = await setCombatResource(2, charKey, 'rule-18', { base: 2, size: 0 });
    expect(overlay.resources).toEqual([{ ruleId: 'rule-18', current: { base: 2, size: 0 } }]);
    expect(overlay.updatedAt).not.toBe('');

    const effective = effectiveResources(versionOf()!, getStoredCombatOverlay(2, charKey));
    expect(effective.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 2, size: 0 });
    // Незатронутые ресурсы сохраняют версию.
    expect(effective.find((r) => r.ruleId === 'rule-19')?.current).toEqual({ base: 3, size: -1 });
  });

  it('setCombatResource клампит значение к лимиту (0..limit)', async () => {
    const above = await setCombatResource(2, charKey, 'rule-18', { base: 99, size: 0 });
    expect(above.resources[0].current).toEqual({ base: 4, size: 0 });
    const below = await setCombatResource(2, charKey, 'rule-18', { base: -5, size: 0 });
    expect(below.resources[0].current).toEqual({ base: 0, size: 0 });
  });

  it('повторная запись ресурса обновляет переопределение (без дубликатов)', async () => {
    await setCombatResource(2, charKey, 'rule-18', { base: 1, size: 0 });
    const overlay = await setCombatResource(2, charKey, 'rule-18', { base: 3, size: 0 });
    expect(overlay.resources).toEqual([{ ruleId: 'rule-18', current: { base: 3, size: 0 } }]);
  });

  it('размерный ресурс (size -1): кламп идёт в базовых пунктах шкалы, а не в сплющенных', async () => {
    const stepUp = await setCombatResource(2, charKey, 'rule-19', { base: 1, size: -1 });
    expect(stepUp.resources.find((r) => r.ruleId === 'rule-19')?.current).toEqual({ base: 1, size: -1 });
    const clamped = await setCombatResource(2, charKey, 'rule-19', { base: 99, size: -1 });
    expect(clamped.resources.find((r) => r.ruleId === 'rule-19')?.current).toEqual({ base: 8, size: -1 });
    const below = await setCombatResource(2, charKey, 'rule-19', { base: -3, size: -1 });
    expect(below.resources.find((r) => r.ruleId === 'rule-19')?.current).toEqual({ base: 0, size: -1 });
  });

  it('несуществующий ресурс/лист — ошибка', async () => {
    await expect(setCombatResource(2, charKey, 'rule-999', { base: 1, size: 0 })).rejects.toThrow('Ресурс не найден');
    await expect(setCombatResource(2, combatKey('npc', 1), 'rule-18', { base: 1, size: 0 })).rejects.toThrow(
      'Лист участника не заполнен',
    );
  });
});

describe('mockGameCombatOverlays: состояния', () => {
  it('addCombatState засевает список из версии и добавляет состояние', async () => {
    const overlay = await addCombatState(2, charKey, { stateRuleId: 'rule-56', value: 3 });
    expect(overlay.states).toContainEqual({ stateRuleId: 'rule-56', value: 3 });
    expect(overlay.states.length).toBe(versionOf()!.states.length + 1);
    expect(overlay.states[0]).toEqual(versionOf()!.states[0]);
  });

  it('setCombatStateValue меняет значение по индексу', async () => {
    await setCombatStateValue(2, charKey, 0, 7);
    const overlay = getStoredCombatOverlay(2, charKey)!;
    expect(overlay.states[0].value).toBe(7);
  });

  it('removeCombatState удаляет состояние по индексу', async () => {
    const before = getStoredCombatOverlay(2, charKey)!.states.length;
    const overlay = await removeCombatState(2, charKey, 0);
    expect(overlay.states.length).toBe(before - 1);
  });

  it('изменение состояния помечает оверлей как изменённый (hasChanges = true)', async () => {
    await setCombatStateValue(2, charKey, 0, 9);
    const version = versionOf()!;
    const overlay = getStoredCombatOverlay(2, charKey)!;
    expect(combatOverlayHasChanges(version, overlay)).toBe(true);
    expect(effectiveStates(version, overlay)[0].value).toBe(9);
  });
});

describe('mockGameCombatOverlays: НПС (версия + оверлей для UI карточки)', () => {
  it('setCombatResource для НПС пишет в npc.version и возвращает оверлей с ресурсом', async () => {
    const npc = gameNpcs.find((n) => n.id === 5)!;
    npc.version = JSON.parse(JSON.stringify(versions[1])) as (typeof versions)[1];
    const before = npc.version.resources.find((r) => r.ruleId === 'rule-18')!.current;

    const overlay = await setCombatResource(2, combatKey('npc', 5), 'rule-18', { base: 1, size: 0 });
    expect(overlay.updatedAt).not.toBe('');
    const after = npc.version.resources.find((r) => r.ruleId === 'rule-18')!;
    expect(after.current).toEqual({ base: 1, size: before.size });
    expect(overlay.resources.find((item) => item.ruleId === 'rule-18')?.current).toEqual({
      base: 1,
      size: before.size,
    });
  });

  it('addCombatState для НПС пишет в npc.version.states', async () => {
    const npc = gameNpcs.find((n) => n.id === 5)!;
    const before = npc.version!.states.length;
    await addCombatState(2, combatKey('npc', 5), { stateRuleId: 'rule-63', value: 1 });
    expect(npc.version!.states.length).toBe(before + 1);
    expect(npc.version!.states.at(-1)).toEqual({ stateRuleId: 'rule-63', value: 1 });
  });
});

describe('mockGameCombatOverlays: экипировка', () => {
  it('setCombatItemEquipped для персонажа пишет inventory в overlay.sheet', async () => {
    const version = versionOf()!;
    const item = version.inventory[0];
    expect(item.equipped).toBe(true);

    const overlay = await setCombatItemEquipped(2, charKey, item.id, false);
    expect(overlay.sheet?.inventory.find((entry) => entry.id === item.id)?.equipped).toBe(false);
    expect(combatOverlayHasChanges(version, overlay)).toBe(true);
    expect(version.inventory.find((entry) => entry.id === item.id)?.equipped).toBe(true);
  });

  it('setCombatItemEquipped для НПС пишет сразу в npc.version', async () => {
    const npc = gameNpcs.find((n) => n.id === 5)!;
    npc.version = JSON.parse(JSON.stringify(versions[1])) as (typeof versions)[1];
    const item = npc.version.inventory[0];
    expect(item.equipped).toBe(true);

    const overlay = await setCombatItemEquipped(2, combatKey('npc', 5), item.id, false);
    expect(overlay.updatedAt).not.toBe('');
    expect(npc.version.inventory.find((entry) => entry.id === item.id)?.equipped).toBe(false);
  });
});
