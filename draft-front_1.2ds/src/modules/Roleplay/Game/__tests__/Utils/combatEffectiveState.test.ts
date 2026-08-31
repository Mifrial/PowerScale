import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import {
  resourceLimitBase,
  effectiveResources,
  effectiveStates,
  statesEqual,
} from '@/modules/Roleplay/Game/Utils/combatEffectiveState';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';

const version: CharacterVersion = versions[1];

function makeOverlay(partial: Partial<GameCombatOverlay> = {}): GameCombatOverlay {
  return {
    gameId: 2,
    entityKey: 'character:1',
    kind: 'character',
    resources: [],
    states: [],
    updatedAt: '2026-08-19T12:00:00',
    ...partial,
  };
}

describe('combatEffectiveState: ресурсы', () => {
  it('resourceLimitBase считает базу + сумму бонусов в базовых пунктах', () => {
    expect(
      resourceLimitBase({ ruleCode: 'r', current: { base: 0, size: 0 }, base: { base: 5, size: 0 }, bonuses: [] }),
    ).toBe(5);
    expect(
      resourceLimitBase({
        ruleCode: 'r',
        current: { base: 0, size: 0 },
        base: { base: 3, size: 0 },
        bonuses: [
          { sourceRuleCode: null, sourceLabel: 'x', delta: 2 },
          { sourceRuleCode: null, sourceLabel: 'y', delta: -1 },
        ],
      }),
    ).toBe(4);
  });

  it('resourceLimitBase для размерной шкалы возвращает лимит в базовых пунктах (не сплющенный)', () => {
    expect(
      resourceLimitBase({ ruleCode: 'r', current: { base: 3, size: -1 }, base: { base: 8, size: -1 }, bonuses: [] }),
    ).toBe(8);
  });

  it('effectiveResources применяет переопределения current из оверлея, остальное берёт из версии', () => {
    const overlay = makeOverlay({ resources: [{ ruleCode: 'action-points', current: { base: 1, size: 0 } }] });
    const effective = effectiveResources(version, overlay);

    expect(effective.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 1, size: 0 });
    expect(effective.find((r) => r.ruleCode === 'spirit-energy')?.current).toEqual(version.resources[1].current);
    // Оверлей не мутирует версию.
    expect(version.resources[0].current).toEqual({ base: 4, size: 0 });
  });

  it('без оверлея эффективные ресурсы = ресурсы версии', () => {
    expect(effectiveResources(version, null)).toBe(version.resources);
  });
});

describe('combatEffectiveState: состояния', () => {
  it('без оверлея эффективные состояния = состояния версии (копия)', () => {
    const effective = effectiveStates(version, null);
    expect(effective).toEqual(version.states);
    expect(effective).not.toBe(version.states);
  });

  it("пустая запись (updatedAt === '') считается отсутствием оверлея — берём версию", () => {
    const overlay = makeOverlay({ updatedAt: '' });
    expect(effectiveStates(version, overlay)).toEqual(version.states);
  });

  it('реальный оверлей — авторитетный список состояний', () => {
    const overlay = makeOverlay({ states: [{ stateRuleCode: 'stunned', value: 5 }] });
    expect(effectiveStates(version, overlay)).toEqual([{ stateRuleCode: 'stunned', value: 5 }]);
  });
});

describe('combatEffectiveState: сравнение состояний', () => {
  it('statesEqual сравнивает содержимое, а не ссылки', () => {
    expect(statesEqual([{ stateRuleCode: 'a', value: 1 }], [{ stateRuleCode: 'a', value: 1 }])).toBe(true);
    expect(statesEqual([{ stateRuleCode: 'a', value: 1 }], [{ stateRuleCode: 'a', value: 2 }])).toBe(false);
    expect(statesEqual([], [])).toBe(true);
  });
});
