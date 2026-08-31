import { describe, expect, it } from 'vitest';
import { isEmptyMembershipDiff, membershipDiff } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import type { CharacteristicDiffDetail, ResourceDiffDetail } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacteristicValue } from '@/modules/Roleplay/Character/Dto/CharacteristicValue';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';

const ability = (ruleCode: string, level: number, extra: Partial<CharacterAbility> = {}): CharacterAbility => ({
  ruleCode,
  level,
  ...extra,
});

const characteristic = (
  ruleCode: string,
  base: number,
  modifiers: CharacteristicValue['modifiers'] = [],
): CharacteristicValue => ({
  ruleCode,
  base: { base, size: 0 },
  modifiers,
});

const resource = (
  ruleCode: string,
  current: number,
  base: number,
  bonuses: ResourceValue['bonuses'] = [],
): ResourceValue => ({
  ruleCode,
  current: { base: current, size: 0 },
  base: { base, size: 0 },
  bonuses,
});

const item = (id: number, ruleCode: string, quantity = 1, equipped = false): InventoryItem => ({
  id,
  ruleCode,
  quantity,
  equipped,
});

const state = (stateRuleCode: string, value: number | undefined = undefined): CharacterStateValue =>
  value === undefined ? { stateRuleCode } : { stateRuleCode, value };

const sense = (
  ruleCode: string,
  value: number,
  extra: Partial<Pick<CharacterSenseValue, 'status' | 'radius'>> = {},
): CharacterSenseValue => ({
  ruleCode,
  value,
  modifiers: [],
  status: extra.status ?? 'precise',
  radius: extra.radius ?? { base: 30, size: 0 },
});

function makeVersion(overrides: Partial<CharacterVersion> = {}): CharacterVersion {
  return {
    name: 'Торвин',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: 'rule-race-human',
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 12, olSpent: 0, olTotal: 4, orSpent: 5, orTotal: 25 },
    money: 100,
    ageYears: 20,
    inventory: [],
    states: [],
    senses: [],
    ...overrides,
  };
}

function scalarAfter(diff: ReturnType<typeof membershipDiff>, label: string): string | undefined {
  return diff.scalars.find((change) => change.label === label)?.after;
}

function sectionChanges(
  diff: ReturnType<typeof membershipDiff>,
  key: string,
): { label: string; kind: string; before: string; after: string }[] {
  return diff.sections.find((section) => section.key === key)?.changes ?? [];
}

describe('membershipDiff: скаляры', () => {
  it('равные версии не дают изменений', () => {
    const diff = membershipDiff(makeVersion(), makeVersion());
    expect(diff.scalars).toHaveLength(0);
    expect(diff.sections).toHaveLength(0);
  });

  it('показывает изменения очков и денег', () => {
    const active = makeVersion();
    const pending = makeVersion({
      points: { osSpent: 12, olSpent: 0, olTotal: 4, orSpent: 8, orTotal: 25 },
      money: 200,
    });
    const diff = membershipDiff(active, pending);
    expect(scalarAfter(diff, 'Очки')).toContain('ОР 8/25');
    expect(scalarAfter(diff, 'Деньги')).toBe('200 гм');
  });

  it('неизменные скаляры не попадают в diff', () => {
    const diff = membershipDiff(makeVersion(), makeVersion({ money: 150 }));
    expect(diff.scalars.map((change) => change.label)).toEqual(['Деньги']);
  });
});

describe('membershipDiff: секции списков', () => {
  it('способность добавлена/удалена/изменена', () => {
    const active = makeVersion({ abilities: [ability('rule-a', 1)] });
    const pending = makeVersion({ abilities: [ability('rule-a', 2), ability('rule-b', 1)] });
    const diff = membershipDiff(active, pending);
    const changes = sectionChanges(diff, 'abilities');
    expect(changes.find((change) => change.label === 'rule-a')).toMatchObject({ kind: 'changed', after: 'ур. 2' });
    expect(changes.find((change) => change.label === 'rule-b')).toMatchObject({ kind: 'added', before: '—' });
  });

  it('множественный навык различается по доменам', () => {
    const active = makeVersion({ abilities: [ability('rule-multi', 1, { domain: 'Лесной' })] });
    const pending = makeVersion({
      abilities: [ability('rule-multi', 1, { domain: 'Лесной' }), ability('rule-multi', 1, { domain: 'Горный' })],
    });
    const diff = membershipDiff(active, pending);
    const changes = sectionChanges(diff, 'abilities');
    expect(changes).toHaveLength(1);
    expect(changes[0].label).toBe('rule-multi|Горный');
    expect(changes[0].kind).toBe('added');
  });

  it('инвентарь: изменение количества и новый экземпляр', () => {
    const active = makeVersion({ inventory: [item(1, 'rule-sword', 1), item(2, 'rule-shield')] });
    const pending = makeVersion({ inventory: [item(1, 'rule-sword', 2), item(3, 'rule-helm')] });
    const diff = membershipDiff(active, pending);
    const changes = sectionChanges(diff, 'inventory');
    expect(changes.find((change) => change.label === 'rule-sword')).toMatchObject({
      kind: 'changed',
      before: '×1',
      after: '×2',
    });
    expect(changes.find((change) => change.label === 'rule-shield')).toMatchObject({ kind: 'removed' });
    expect(changes.find((change) => change.label === 'rule-helm')).toMatchObject({ kind: 'added' });
  });

  it('характеристика: итог (база + модификаторы) и попап-детали с именами', () => {
    const active = makeVersion({ characteristics: [characteristic('rule-str', 3)] });
    const pending = makeVersion({
      characteristics: [
        characteristic('rule-str', 3, [
          { sourceRuleCode: 'rule-gift', sourceLabel: null, delta: 2, target: 'rule-str', scope: null },
        ]),
      ],
    });
    const resolve = (ruleCode: string) => (ruleCode === 'rule-gift' ? 'Врождённая Сила 2' : ruleCode);
    const diff = membershipDiff(active, pending, resolve);
    const changes = sectionChanges(diff, 'characteristics');
    expect(changes).toHaveLength(1);
    expect(changes[0]).toMatchObject({ kind: 'changed', before: '3', after: '5' });
    const detail = (changes[0] as unknown as { detail: CharacteristicDiffDetail }).detail;
    expect(detail.base).toBe('3');
    expect(detail.value).toBe('5');
    expect(detail.modifiers).toEqual([{ name: 'Врождённая Сила 2', delta: 2, scope: null }]);
  });

  it('параметрическая способность: без «ур. N» (уровень — заглушка), только параметры', () => {
    const active = makeVersion();
    const pending = makeVersion({
      abilities: [ability('rule-p', 1, { parameters: { x: { base: 2, size: 0 } } })],
    });
    const diff = membershipDiff(active, pending);
    const changes = sectionChanges(diff, 'abilities');
    expect(changes[0].after).toContain('x=2');
    expect(changes[0].after).not.toContain('ур.');
  });

  it('ресурс: значение/лимит + попап-детали с бонусами', () => {
    const active = makeVersion({ resources: [resource('rule-hp', 6, 12)] });
    const pending = makeVersion({
      resources: [resource('rule-hp', 10, 12, [{ sourceRuleCode: 'rule-x', sourceLabel: null, delta: -2 }])],
    });
    const resolve = (ruleCode: string) => (ruleCode === 'rule-x' ? 'Источник' : ruleCode);
    const diff = membershipDiff(active, pending, resolve);
    const changes = sectionChanges(diff, 'resources');
    expect(changes[0]).toMatchObject({ kind: 'changed', before: '6 / 12', after: '10 / 10' });
    const detail = (changes[0] as unknown as { detail: ResourceDiffDetail }).detail;
    expect(detail.base).toBe('12');
    expect(detail.limit).toBe('10');
    expect(detail.bonuses).toEqual([{ name: 'Источник', delta: -2 }]);
  });

  it('состояние добавлено, чувство изменено', () => {
    const active = makeVersion({ senses: [sense('rule-vision', 2)] });
    const pending = makeVersion({ states: [state('rule-bleed', 2)], senses: [sense('rule-vision', 3)] });
    const diff = membershipDiff(active, pending);
    expect(sectionChanges(diff, 'states')[0]).toMatchObject({ label: 'rule-bleed', kind: 'added', after: '2' });
    expect(sectionChanges(diff, 'senses')[0]).toMatchObject({
      kind: 'changed',
      before: '2; precise; 30',
      after: '3; precise; 30',
    });
  });

  it('изменение статуса или дальности чувства попадает в diff', () => {
    const active = makeVersion({ senses: [sense('rule-vision', 0)] });
    const pending = makeVersion({
      senses: [sense('rule-vision', 0, { status: 'vague', radius: { base: 15, size: 0 } })],
    });
    const diff = membershipDiff(active, pending);

    expect(sectionChanges(diff, 'senses')[0]).toMatchObject({
      kind: 'changed',
      before: '0; precise; 30',
      after: '0; vague; 15',
    });
  });

  it('уникальное правило попадает в diff', () => {
    const pending = makeVersion({
      customRules: [{ id: 1, kind: 'item', name: 'Лаваш', description: 'Большой', status: 'active' }],
    });
    const diff = membershipDiff(makeVersion(), pending);
    expect(isEmptyMembershipDiff(diff)).toBe(false);
    expect(sectionChanges(diff, 'customRules')[0]).toMatchObject({ label: 'Лаваш', kind: 'added' });
  });

  it('изменения в секции отсортированы по label', () => {
    const active = makeVersion({ abilities: [ability('rule-b', 1)] });
    const pending = makeVersion({ abilities: [ability('rule-b', 1), ability('rule-a', 1), ability('rule-c', 1)] });
    const labels = sectionChanges(membershipDiff(active, pending), 'abilities').map((change) => change.label);
    expect(labels).toEqual(['rule-a', 'rule-c']);
  });
});

describe('isEmptyMembershipDiff', () => {
  it('равные версии — пустой diff', () => {
    expect(isEmptyMembershipDiff(membershipDiff(makeVersion(), makeVersion()))).toBe(true);
  });

  it('только смена ревизии — пустой (ревизия не в diff)', () => {
    expect(isEmptyMembershipDiff(membershipDiff(makeVersion(), makeVersion({ rulesRevision: 12 })))).toBe(true);
  });

  it('смена имени — не пустой', () => {
    expect(isEmptyMembershipDiff(membershipDiff(makeVersion(), makeVersion({ name: 'Другой' })))).toBe(false);
  });

  it('первая подача — не пустая', () => {
    expect(isEmptyMembershipDiff(membershipDiff(null, makeVersion()))).toBe(false);
  });
});

describe('membershipDiff: первая подача', () => {
  it('active null — всё помечается добавленным', () => {
    const pending = makeVersion({
      shortDescription: 'Кратко',
      characteristics: [characteristic('rule-str', 4)],
      abilities: [ability('rule-a', 1)],
    });
    const diff = membershipDiff(null, pending);
    expect(diff.scalars.every((change) => change.kind === 'added' && change.before === '—')).toBe(true);
    expect(diff.scalars).toHaveLength(7);
    expect(sectionChanges(diff, 'characteristics')[0]).toMatchObject({ kind: 'added', before: '—', after: '4' });
    expect(sectionChanges(diff, 'abilities')[0]).toMatchObject({ kind: 'added' });
  });
});
