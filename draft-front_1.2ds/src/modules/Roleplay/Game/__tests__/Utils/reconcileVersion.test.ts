import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import {
  effectiveOverlayVersion,
  reconcileVersion,
  versionConflicts,
  type ConflictChoices,
} from '@/modules/Roleplay/Game/Utils/reconcileVersion';

function version(partial: Partial<CharacterVersion> = {}): CharacterVersion {
  return {
    name: 'Торвин',
    shortDescription: 'Описание',
    fullDescription: null,
    spaceCode: 'actual',
    rulesRevision: 5,
    raceRuleId: 'rule-1',
    characteristics: [],
    resources: [{ ruleId: 'rule-18', current: { base: 0, size: 0 }, base: { base: 2, size: 0 }, bonuses: [] }],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
    money: 200,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
    ...partial,
  };
}

function overlay(partial: Partial<GameCombatOverlay> = {}): GameCombatOverlay {
  return {
    gameId: 2,
    entityKey: 'character:1',
    kind: 'character',
    resources: [],
    states: [],
    updatedAt: '2026-08-20T10:00:00Z',
    ...partial,
  };
}

describe('reconcileVersion: three-way мерж версий (модель Баг 1)', () => {
  it('без active (первая подача) возвращает latest', () => {
    const latest = version({ name: 'Новый', money: 300 });
    const result = reconcileVersion(null, latest, overlay());
    expect(result).toEqual(latest);
    expect(result).not.toBe(latest);
  });

  it('без изменений оверлея возвращает latest', () => {
    const active = version();
    const latest = version({ money: 300 });
    const result = reconcileVersion(active, latest, null);
    expect(result.money).toBe(300);
  });

  it('поле изменил только latest → побеждает latest', () => {
    const active = version({ money: 200 });
    const latest = version({ money: 300 });
    // Оверлей трогает только деньги? нет — оверлей трогает состояния; деньги — только latest.
    const result = reconcileVersion(active, latest, overlay({ states: [{ stateRuleId: 'rule-63', value: 5 }] }));
    expect(result.money).toBe(300);
    expect(result.states).toContainEqual({ stateRuleId: 'rule-63', value: 5 });
  });

  it('поле изменил только оверлей → побеждает оверлей', () => {
    const active = version({ money: 200 });
    const latest = version({ money: 200 });
    const result = reconcileVersion(
      active,
      latest,
      overlay({ resources: [{ ruleId: 'rule-18', current: { base: 1, size: 0 } }] }),
    );
    expect(result.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 1, size: 0 });
  });

  it('конфликт: default — оверлей, выбор ведущего — latest', () => {
    const active = version({ money: 200 });
    const latest = version({ money: 300 });
    const overlayMoney = version({ money: 250 });
    const overlayWithSheet = overlay({ sheet: overlayMoney });
    // sheet несёт полный лист, поэтому кандидат оверлея — sheet с деньгами 250.
    expect(reconcileVersion(active, latest, overlayWithSheet).money).toBe(250);
    expect(reconcileVersion(active, latest, overlayWithSheet, { money: 'latest' }).money).toBe(300);
  });

  it('присутствие sheet делает его базой кандидата оверлея', () => {
    const active = version();
    const latest = version({ money: 300 });
    const sheet = version({ money: 250, name: 'Торвин (в бою)' });
    const result = reconcileVersion(active, latest, overlay({ sheet }));
    expect(result.money).toBe(250);
    expect(result.name).toBe('Торвин (в бою)');
  });
});

describe('effectiveOverlayVersion', () => {
  it('пустой оверлей (updatedAt === "") → null', () => {
    expect(effectiveOverlayVersion(version(), overlay({ updatedAt: '' }))).toBeNull();
  });

  it('без sheet — активная версия с боевыми правками поверх', () => {
    const active = version({ money: 200 });
    const result = effectiveOverlayVersion(
      active,
      overlay({ resources: [{ ruleId: 'rule-18', current: { base: 1, size: 0 } }] }),
    );
    expect(result?.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 1, size: 0 });
    expect(result?.money).toBe(200);
  });

  it('с sheet — полная копия листа', () => {
    const active = version();
    const sheet = version({ name: 'В бою' });
    const result = effectiveOverlayVersion(active, overlay({ sheet }));
    expect(result?.name).toBe('В бою');
  });
});

describe('versionConflicts', () => {
  it('конфликтные поля: изменили и latest, и оверлей относительно active', () => {
    const active = version({ money: 200 });
    const latest = version({ money: 300, name: 'Торвин Огненный' });
    const sheet = version({ money: 250, name: 'Драконоборец' });
    const conflicts = versionConflicts(active, latest, overlay({ sheet }));
    const keys = conflicts.map((c) => c.field);
    expect(keys).toContain('money');
    expect(keys).toContain('name');
    // Раса/возраст и пр. не менялись — конфликтов нет.
    expect(conflicts.find((c) => c.field === 'raceRuleId')).toBeUndefined();
  });

  it('по умолчанию выбор — overlay; choices проставляются', () => {
    const active = version({ money: 200 });
    const latest = version({ money: 300 });
    const sheet = version({ money: 250 });
    const choices: ConflictChoices = { money: 'latest' };
    const conflicts = versionConflicts(active, latest, overlay({ sheet }), choices);
    expect(conflicts.find((c) => c.field === 'money')?.choice).toBe('latest');
  });
});
