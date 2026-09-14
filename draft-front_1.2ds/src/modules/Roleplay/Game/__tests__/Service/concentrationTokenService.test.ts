import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { concentrationTokenService } from '@/modules/Roleplay/Game/Service/Instance/concentrationTokenService';

const dim = (base: number, size = 0) => ({ base, size });

function version(overrides: Partial<CharacterVersion> = {}): CharacterVersion {
  return {
    id: 1,
    characterId: 1,
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    rulesRevision: 1,
    raceRuleCode: 'human',
    characteristics: [
      { ruleCode: 'intellect', base: dim(5), modifiers: [] },
      { ruleCode: 'perception', base: dim(3), modifiers: [] },
      { ruleCode: 'willpower', base: dim(5), modifiers: [] },
    ],
    resources: [{ ruleCode: 'concentration', current: dim(2), base: dim(2), bonuses: [] }],
    abilities: [{ ruleCode: 'kontsentratsiya', level: 1 }],
    inventory: [],
    states: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
    money: 0,
    ageYears: null,
    personality: null,
    ...overrides,
  } as CharacterVersion;
}

const overlay = (used: boolean, current = 1): GameCombatOverlay => ({
  gameId: 1,
  entityKey: 'character:1',
  kind: 'character',
  resources: [{ ruleCode: 'concentration', current: dim(current) }],
  states: [],
  updatedAt: '2026-09-13T12:00:00',
  concentrationUsedInCycle: used,
});

const intellect = {
  id: null,
  code: 'intellect',
  type: 'characteristic' as const,
  name: 'Интеллект',
  description: '',
  spaceId: 1,
  createdAt: 0,
};

const willpower = {
  id: null,
  code: 'willpower',
  type: 'characteristic' as const,
  name: 'Сила воли',
  description: '',
  spaceId: 1,
  createdAt: 0,
};

describe('ConcentrationTokenService', () => {
  it('canSpend ложь при current 0 и при выключенной способности', () => {
    const rules: Rule[] = [intellect];
    const empty = version({ resources: [{ ruleCode: 'concentration', current: dim(0), base: dim(1), bonuses: [] }] });
    expect(concentrationTokenService.canSpend(empty, overlay(false, 0), rules, 'check-intellect')).toBe(false);
    const off = version({
      characteristics: [
        { ruleCode: 'intellect', base: dim(3), modifiers: [] },
        { ruleCode: 'perception', base: dim(3), modifiers: [] },
      ],
      resources: [{ ruleCode: 'concentration', current: dim(1), base: dim(0), bonuses: [] }],
    });
    expect(concentrationTokenService.canSpend(off, overlay(false, 1), rules, 'check-intellect')).toBe(false);
  });

  it('refillIfUnused восстанавливает лимит только если жетон не тратили', async () => {
    const calls: { resource?: number; used?: boolean }[] = [];
    const api = {
      async setCombatResource(_g: number, _k: string, _code: string, current: { base: number }) {
        calls.push({ resource: current.base });

        return overlay(true, current.base);
      },
      async setCombatConcentrationUsedInCycle(_g: number, _k: string, used: boolean) {
        calls.push({ used });

        return overlay(used, 2);
      },
    } as unknown as IGameApi;

    await concentrationTokenService.refillIfUnused(api, 1, 'character:1', version(), overlay(false, 0));
    expect(calls).toEqual([{ resource: 2 }, { used: false }]);

    calls.length = 0;
    await concentrationTokenService.refillIfUnused(api, 1, 'character:1', version(), overlay(true, 0));
    expect(calls).toEqual([{ used: false }]);
  });

  it('maxSpend: без улучшения 1; Предельная 1 при 5↑ — 2; уровень 2 при 5↑↑ — 3', () => {
    const rules: Rule[] = [intellect];
    const tokens = overlay(false, 3);
    expect(concentrationTokenService.maxSpend(version(), tokens, rules, 'check-hit')).toBe(1);

    const peak1 = version({
      abilities: [
        { ruleCode: 'kontsentratsiya', level: 1 },
        { ruleCode: 'predelnaya-kontsentratsiya', level: 1 },
      ],
      characteristics: [
        { ruleCode: 'intellect', base: dim(5, 1), modifiers: [] },
        { ruleCode: 'perception', base: dim(3), modifiers: [] },
      ],
    });
    expect(concentrationTokenService.maxSpend(peak1, tokens, rules, 'check-hit')).toBe(2);

    const peak2 = version({
      abilities: [
        { ruleCode: 'kontsentratsiya', level: 1 },
        { ruleCode: 'predelnaya-kontsentratsiya', level: 2 },
      ],
      characteristics: [
        { ruleCode: 'intellect', base: dim(5, 2), modifiers: [] },
        { ruleCode: 'perception', base: dim(3), modifiers: [] },
      ],
    });
    expect(concentrationTokenService.maxSpend(peak2, tokens, rules, 'check-hit')).toBe(3);
  });

  it('maxSpend снижает live-уровень, если размер характеристики ниже требования', () => {
    const rules: Rule[] = [intellect];
    const tokens = overlay(false, 3);
    const bought2 = version({
      abilities: [
        { ruleCode: 'kontsentratsiya', level: 1 },
        { ruleCode: 'predelnaya-kontsentratsiya', level: 2 },
      ],
      characteristics: [
        { ruleCode: 'intellect', base: dim(5, 1), modifiers: [] },
        { ruleCode: 'perception', base: dim(3), modifiers: [] },
      ],
    });
    expect(concentrationTokenService.maxSpend(bought2, tokens, rules, 'check-hit')).toBe(2);

    const bought1plain = version({
      abilities: [
        { ruleCode: 'kontsentratsiya', level: 1 },
        { ruleCode: 'predelnaya-kontsentratsiya', level: 1 },
      ],
    });
    expect(concentrationTokenService.maxSpend(bought1plain, tokens, rules, 'check-hit')).toBe(1);
  });

  it('parseSpendAmount и tokenAdvantage: true → 1, число даёт delta', () => {
    expect(concentrationTokenService.parseSpendAmount(true)).toBe(1);
    expect(concentrationTokenService.parseSpendAmount(false)).toBe(0);
    expect(concentrationTokenService.parseSpendAmount(2)).toBe(2);
    expect(concentrationTokenService.tokenAdvantage(2).delta).toBe(2);
  });

  it('spendToken вычитает запрошенное число жетонов', async () => {
    const calls: number[] = [];
    const api = {
      async setCombatResource(_g: number, _k: string, _code: string, current: { base: number }) {
        calls.push(current.base);

        return overlay(true, current.base);
      },
      async setCombatConcentrationUsedInCycle() {
        return overlay(true, 1);
      },
    } as unknown as IGameApi;

    await concentrationTokenService.spendToken(api, 1, 'character:1', version(), overlay(false, 3), 2);
    expect(calls).toEqual([1]);
  });

  it('Сосредоточение воли открывает проверки Силы воли и истощения', () => {
    const rules: Rule[] = [intellect, willpower];
    const tokens = overlay(false, 2);
    expect(concentrationTokenService.canSpend(version(), tokens, rules, 'check-willpower')).toBe(false);
    expect(concentrationTokenService.canSpend(version(), tokens, rules, 'check-exhaustion')).toBe(false);
    const focused = version({
      abilities: [
        { ruleCode: 'kontsentratsiya', level: 1 },
        { ruleCode: 'sosredotochenie-voli', level: 1 },
      ],
    });
    expect(concentrationTokenService.canSpend(focused, tokens, rules, 'check-willpower')).toBe(true);
    expect(concentrationTokenService.canSpend(focused, tokens, rules, 'check-exhaustion')).toBe(true);
    const weakWill = version({
      abilities: focused.abilities,
      characteristics: [
        { ruleCode: 'intellect', base: dim(5), modifiers: [] },
        { ruleCode: 'perception', base: dim(3), modifiers: [] },
        { ruleCode: 'willpower', base: dim(4), modifiers: [] },
      ],
    });
    expect(concentrationTokenService.canSpend(weakWill, tokens, rules, 'check-willpower')).toBe(false);
  });

  it('длительное напряжение поднимает потолок до 10 ходов', () => {
    const rules: Rule[] = [intellect, willpower];
    const tokens = overlay(false, 2);
    expect(concentrationTokenService.canSpend(version(), tokens, rules, 'check-hit', null, 2)).toBe(false);
    const stretched = version({
      abilities: [
        { ruleCode: 'kontsentratsiya', level: 1 },
        { ruleCode: 'dlitelnoe-napryazhenie', level: 1 },
      ],
      characteristics: [
        { ruleCode: 'intellect', base: dim(5, 1), modifiers: [] },
        { ruleCode: 'perception', base: dim(3), modifiers: [] },
        { ruleCode: 'willpower', base: dim(5), modifiers: [] },
      ],
    });
    expect(concentrationTokenService.maxActionTurns(stretched, rules)).toBe(10);
    expect(concentrationTokenService.canSpend(stretched, tokens, rules, 'check-hit', null, 2)).toBe(true);
    expect(concentrationTokenService.canSpend(stretched, tokens, rules, 'check-hit', null, 11)).toBe(false);
  });
});
