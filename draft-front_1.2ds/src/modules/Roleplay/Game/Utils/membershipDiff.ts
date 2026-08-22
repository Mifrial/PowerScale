import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacteristicValue } from '@/modules/Roleplay/Character/Dto/CharacteristicValue';
import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

export type DiffKind = 'added' | 'removed' | 'changed';

/** Строка изменения: label — имя элемента/поля, before/after — человекочитаемые значения. */
export interface DiffChange {
  key: string;
  label: string;
  kind: DiffKind;
  before: string;
  after: string;
  /** Структурированные данные для UI (например, модификаторы характеристики — для попапа). */
  detail?: unknown;
}

/** Детали характеристики для попапа: итог, база, модификаторы (с именами). */
export interface CharacteristicDiffDetail {
  value: string;
  base: string;
  modifiers: { name: string; delta: number; scope: string | null }[];
}

/** Детали ресурса для попапа: значение, лимит, база лимита, бонусы (с именами). */
export interface ResourceDiffDetail {
  current: string;
  limit: string;
  base: string;
  bonuses: { name: string; delta: number }[];
}

export type DiffSectionKey = 'characteristics' | 'resources' | 'abilities' | 'inventory' | 'states' | 'senses';

/** Секция списка листа: по-элементные изменения (не по количеству). */
export interface DiffSection {
  key: DiffSectionKey;
  label: string;
  changes: DiffChange[];
}

export interface MembershipDiff {
  /** Скалярные поля (имя, раса, возраст, очки, деньги, описания). */
  scalars: DiffChange[];
  /** Списки листа: характеристики/ресурсы/способности/инвентарь/состояния/чувства. */
  sections: DiffSection[];
}

function dim(value: DimensionalNumberValue): string {
  return DimensionalNumber.from(value).toString();
}

/** Итог характеристики как в движке: база + Σ дельт не-условных модификаторов. */
function characteristicValue(characteristic: CharacteristicValue): DimensionalNumberValue {
  const delta = characteristic.modifiers
    .filter((modifier) => modifier.scope === null || modifier.scope === undefined)
    .reduce((sum, modifier) => sum + modifier.delta, 0);

  return CharacteristicNumber.from(characteristic.base).modifyWith(delta).value;
}

function characteristicLabel(characteristic: CharacteristicValue): string {
  return characteristic.ruleId;
}

function characteristicRender(characteristic: CharacteristicValue): string {
  return dim(characteristicValue(characteristic));
}

function characteristicDetail(
  characteristic: CharacteristicValue,
  resolve: (ruleId: string) => string,
): CharacteristicDiffDetail {
  return {
    value: dim(characteristicValue(characteristic)),
    base: dim(characteristic.base),
    modifiers: characteristic.modifiers.map((modifier) => ({
      name: modifierName(modifier, resolve),
      delta: modifier.delta,
      scope: modifier.scope,
    })),
  };
}

function modifierName(modifier: CharacteristicModifier, resolve: (ruleId: string) => string): string {
  if (modifier.sourceLabel) return modifier.sourceLabel;

  return modifier.sourceRuleId ? resolve(modifier.sourceRuleId) : 'источник';
}

function resourceLabel(resource: ResourceValue): string {
  return resource.ruleId;
}

function resourceRender(resource: ResourceValue): string {
  const bonuses = resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);
  const limit = bonuses === 0 ? resource.base : DimensionalNumber.from(resource.base).modify(bonuses).value;

  return `${dim(resource.current)} / ${dim(limit)}`;
}

function resourceDetail(resource: ResourceValue, resolve: (ruleId: string) => string): ResourceDiffDetail {
  const bonuses = resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);
  const limit = bonuses === 0 ? resource.base : DimensionalNumber.from(resource.base).modify(bonuses).value;

  return {
    current: dim(resource.current),
    limit: dim(limit),
    base: dim(resource.base),
    bonuses: resource.bonuses.map((bonus) => ({
      name: bonus.sourceLabel ?? (bonus.sourceRuleId ? resolve(bonus.sourceRuleId) : 'источник'),
      delta: bonus.delta,
    })),
  };
}

function abilityKey(ability: CharacterAbility): string {
  return ability.domain !== undefined && ability.domain !== null
    ? `${ability.ruleId}|${ability.domain}`
    : ability.ruleId;
}

function abilityLabel(ability: CharacterAbility): string {
  return abilityKey(ability);
}

function paramValue(value: DimensionalNumberValue | number): string {
  return typeof value === 'number' ? String(value) : dim(value);
}

function abilityRender(ability: CharacterAbility): string {
  const parameters = ability.parameters ?? {};
  const hasParameters = Object.keys(parameters).length > 0;
  const parts: string[] = [];
  // Параметрические способности (напр. «Врождённая Сила X»): уровень — заглушка (всегда 1), показываем параметры.
  if (!hasParameters) parts.push(`ур. ${ability.level}`);
  if (ability.zone) parts.push(`зона ${ability.zone}`);
  if (hasParameters) {
    parts.push(
      Object.entries(parameters)
        .map(([key, value]) => `${key}=${paramValue(value)}`)
        .join(', '),
    );
  }
  if (ability.gifted) parts.push('(дар)');

  return parts.join(' · ');
}

function pointsLabel(version: CharacterVersion | null): string {
  if (!version) return '—';
  const parts = [`ОС ${version.points.osSpent}`];
  parts.push(`ОЛ ${version.points.olSpent}/${version.points.olTotal}`);
  const or =
    version.points.orTotal === null
      ? String(version.points.orSpent)
      : `${version.points.orSpent}/${version.points.orTotal}`;
  parts.push(`ОР ${or}`);

  return parts.join(' · ');
}

function moneyLabel(version: CharacterVersion | null): string {
  return version ? `${version.money} гм` : '—';
}

function inventoryKey(item: InventoryItem): string {
  return `${item.ruleId}|${item.id}`;
}

function inventoryLabel(item: InventoryItem): string {
  return item.ruleId ?? `custom:${item.id}`;
}

function inventoryRender(item: InventoryItem): string {
  const parts = [`×${item.quantity}`];
  if (item.equipped) parts.push('экипировано');
  if (item.note) parts.push(`«${item.note}»`);

  return parts.join(', ');
}

function stateKey(state: CharacterStateValue, index: number): string {
  return `${state.stateRuleId}|${index}`;
}

function stateLabel(state: CharacterStateValue): string {
  return state.stateRuleId;
}

function stateRender(state: CharacterStateValue): string {
  if (state.poison) return `яд: ${state.poison.poisonRuleId ?? '?'}`;
  if (state.dimensionalValue) return dim(state.dimensionalValue);
  if (state.value !== undefined) return String(state.value);

  return 'активно';
}

function senseLabel(sense: CharacterSenseValue): string {
  return sense.ruleId;
}

function senseRender(sense: CharacterSenseValue): string {
  return String(sense.value);
}

/** По-элементный diff двух списков листа по ключу (added/removed/changed), стабильная сортировка по label. */
function diffList<T>(
  active: T[],
  pending: T[],
  keyOf: (item: T, index: number) => string,
  labelOf: (item: T) => string,
  renderOf: (item: T) => string,
  detailOf?: (item: T) => unknown,
): DiffChange[] {
  const activeMap = new Map(active.map((item, index) => [keyOf(item, index), item]));
  const pendingMap = new Map(pending.map((item, index) => [keyOf(item, index), item]));
  const keys = new Set([...activeMap.keys(), ...pendingMap.keys()]);
  const changes: DiffChange[] = [];
  for (const key of keys) {
    const activeItem = activeMap.get(key);
    const pendingItem = pendingMap.get(key);
    if (activeItem === undefined && pendingItem !== undefined) {
      changes.push({
        key,
        label: labelOf(pendingItem),
        kind: 'added',
        before: '—',
        after: renderOf(pendingItem),
        detail: detailOf?.(pendingItem),
      });
    } else if (activeItem !== undefined && pendingItem === undefined) {
      changes.push({
        key,
        label: labelOf(activeItem),
        kind: 'removed',
        before: renderOf(activeItem),
        after: '—',
        detail: detailOf?.(activeItem),
      });
    } else if (activeItem !== undefined && pendingItem !== undefined) {
      const before = renderOf(activeItem);
      const after = renderOf(pendingItem);
      if (before !== after)
        changes.push({
          key,
          label: labelOf(pendingItem),
          kind: 'changed',
          before,
          after,
          detail: detailOf?.(pendingItem),
        });
    }
  }

  return changes.sort((a, b) => a.label.localeCompare(b.label));
}

const SCALARS: { key: string; label: string; render: (version: CharacterVersion | null) => string }[] = [
  { key: 'name', label: 'Имя', render: (version) => version?.name ?? '—' },
  { key: 'shortDescription', label: 'Краткое описание', render: (version) => version?.shortDescription ?? '—' },
  { key: 'fullDescription', label: 'Полное описание', render: (version) => version?.fullDescription ?? '—' },
  { key: 'race', label: 'Раса', render: (version) => version?.raceRuleId ?? '—' },
  { key: 'age', label: 'Возраст', render: (version) => (version?.ageYears == null ? '—' : String(version.ageYears)) },
  { key: 'points', label: 'Очки', render: pointsLabel },
  { key: 'money', label: 'Деньги', render: moneyLabel },
];

function scalarChanges(active: CharacterVersion | null, pending: CharacterVersion | null): DiffChange[] {
  const result: (DiffChange | null)[] = SCALARS.map((field) => {
    if (active === null) {
      return { key: field.key, label: field.label, kind: 'added', before: '—', after: field.render(pending) };
    }
    const before = field.render(active);
    const after = field.render(pending);
    if (before === after) return null;

    return { key: field.key, label: field.label, kind: 'changed', before, after };
  });

  return result.filter((change): change is DiffChange => change !== null);
}

/**
 * Diff двух состояний листа персонажа в игре (модерация, ТР §7.8): по-элементное сравнение
 * списков листа + скалярные поля. Первая подача (active null) — всё помечается 'added'.
 * `resolve` резолвит ruleId в имя (имена из ревизий); label'ы содержат ruleId.
 */
export function membershipDiff(
  active: CharacterVersion | null,
  pending: CharacterVersion | null,
  resolve: (ruleId: string) => string = (ruleId) => ruleId,
): MembershipDiff {
  const sections: DiffSection[] = [
    {
      key: 'characteristics',
      label: 'Характеристики',
      changes: diffList(
        active?.characteristics ?? [],
        pending?.characteristics ?? [],
        (item) => item.ruleId,
        characteristicLabel,
        characteristicRender,
        (item) => characteristicDetail(item, resolve),
      ),
    },
    {
      key: 'resources',
      label: 'Ресурсы',
      changes: diffList(
        active?.resources ?? [],
        pending?.resources ?? [],
        (item) => item.ruleId,
        resourceLabel,
        resourceRender,
        (item) => resourceDetail(item, resolve),
      ),
    },
    {
      key: 'abilities',
      label: 'Способности',
      changes: diffList(active?.abilities ?? [], pending?.abilities ?? [], abilityKey, abilityLabel, abilityRender),
    },
    {
      key: 'inventory',
      label: 'Инвентарь',
      changes: diffList(
        active?.inventory ?? [],
        pending?.inventory ?? [],
        inventoryKey,
        inventoryLabel,
        inventoryRender,
      ),
    },
    {
      key: 'states',
      label: 'Состояния',
      changes: diffList(active?.states ?? [], pending?.states ?? [], stateKey, stateLabel, stateRender),
    },
    {
      key: 'senses',
      label: 'Чувства',
      changes: diffList(active?.senses ?? [], pending?.senses ?? [], (item) => item.ruleId, senseLabel, senseRender),
    },
  ];

  return {
    scalars: scalarChanges(active, pending),
    sections: sections.filter((section) => section.changes.length > 0),
  };
}
