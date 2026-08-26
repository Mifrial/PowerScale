import { describe, expect, it } from 'vitest';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';

import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';

const version = versions[1];

function makeOverlay(): GameCombatOverlay {
  return {
    gameId: 2,
    entityKey: 'character:1',
    kind: 'character',
    resources: [{ ruleId: 'rule-18', current: { base: 1, size: 0 } }],
    states: [{ stateRuleId: 'rule-63', value: 5 }],
    updatedAt: '2026-08-19T12:00:00',
  };
}

describe('mergeCombatOverlay: применение оверлея на версию', () => {
  it('применяет current ресурсов (с клампом к лимиту) и заменяет состояния', () => {
    const merged = combatOverlayService.mergeCombatOverlay(version, makeOverlay());
    expect(merged.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 1, size: 0 });
    expect(merged.states).toEqual([{ stateRuleId: 'rule-63', value: 5 }]);
  });

  it('значение выше лимита клампится к лимиту актуальной версии', () => {
    const overlay = makeOverlay();
    overlay.resources = [{ ruleId: 'rule-18', current: { base: 99, size: 0 } }];
    expect(
      combatOverlayService.mergeCombatOverlay(version, overlay).resources.find((r) => r.ruleId === 'rule-18')?.current,
    ).toEqual({
      base: 4,
      size: 0,
    });
  });

  it('размерный ресурс: кламп в базовых пунктах (1↓ не сплющивается в 0)', () => {
    const overlay = makeOverlay();
    overlay.resources = [{ ruleId: 'rule-19', current: { base: 1, size: -1 } }];
    expect(
      combatOverlayService.mergeCombatOverlay(version, overlay).resources.find((r) => r.ruleId === 'rule-19')?.current,
    ).toEqual({
      base: 1,
      size: -1,
    });
  });

  it('сохраняет standalone-правки остальных полей (CD-4: мерж, не замена)', () => {
    const changed = { ...version, money: 777, name: 'Торвин II' };
    const merged = combatOverlayService.mergeCombatOverlay(changed, makeOverlay());
    expect(merged.name).toBe('Торвин II');
    expect(merged.money).toBe(777);
    expect(merged.resources.find((r) => r.ruleId === 'rule-19')?.current).toEqual(version.resources[1].current);
  });

  it('не мутирует исходную версию', () => {
    const before = JSON.stringify(version.resources);
    combatOverlayService.mergeCombatOverlay(version, makeOverlay());
    expect(JSON.stringify(version.resources)).toBe(before);
    expect(version.states.length).toBe(7);
  });

  it('без переопределений ресурсов оверлей трогает только состояния', () => {
    const overlay = makeOverlay();
    overlay.resources = [];
    const merged = combatOverlayService.mergeCombatOverlay(version, overlay);
    expect(merged.resources).toEqual(version.resources);
    expect(merged.states).toEqual([{ stateRuleId: 'rule-63', value: 5 }]);
  });

  it('клампит current листа к лимиту даже без переопределения оверлея', () => {
    const over = {
      ...version,
      resources: version.resources.map((resource) =>
        resource.ruleId === 'rule-18' ? { ...resource, current: { base: 99, size: 0 } } : resource,
      ),
    };
    const overlay = makeOverlay();
    overlay.resources = [];
    expect(
      combatOverlayService.mergeCombatOverlay(over, overlay).resources.find((r) => r.ruleId === 'rule-18')?.current,
    ).toEqual({
      base: 4,
      size: 0,
    });
  });
});

describe('preferNewerCombatOverlays / replaceCombatOverlay', () => {
  it('не откатывает локальный оверлей пустым ответом сервера', () => {
    const local = makeOverlay();
    local.updatedAt = '2026-08-23T12:00:01';
    const stale = makeOverlay();
    stale.updatedAt = '';
    stale.resources = [];
    expect(combatOverlayService.preferNewerCombatOverlays([local], [stale])[0]?.resources).toEqual(local.resources);
  });

  it('replaceCombatOverlay отдаёт новый массив с подменённым снимком', () => {
    const previous = makeOverlay();
    const next = makeOverlay();
    next.resources = [{ ruleId: 'rule-18', current: { base: 2, size: 0 } }];
    const list = combatOverlayService.replaceCombatOverlay([previous], next);
    expect(list).not.toBe([previous]);
    expect(list[0]?.resources[0]?.current.base).toBe(2);
  });
});
