import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StateAggregation, StateSpec, StateValueType } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CombatMasterySection } from '@/modules/Roleplay/Character/Dto/Overview/CombatMasterySection';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { mergeCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

export type CombatEntityKind = 'character' | 'npc';

/** Модель боевой карточки участника: резолюция entity + версия/оверлей + права. */
export interface CombatCardModel {
  kind: CombatEntityKind;
  entityId: number;
  entityKey: CombatEntityKey;
  name: string;
  /** Approved-версия персонажа / версия НПС (null — лист не заполнен). */
  version: CharacterVersion | null;
  /** Оверлей с изменениями; null — изменений ещё не было (пустая запись). */
  overlay: GameCombatOverlay | null;
  /** Версия с применённым оверлеем для view-model карточки (null — версии нет). */
  effectiveVersion: CharacterVersion | null;
  /** CD-6: игрок — своего approved-персонажа; ГМ — любого (персонажи и НПС). */
  canEdit: boolean;
}

/** Строка состояния боевой карточки: правило + записи (индексы в списке боя) для правок. */
export interface CombatStateRow {
  ruleId: string;
  code: string;
  name: string;
  iconCode: string | null;
  valueType: StateValueType;
  aggregation: StateAggregation;
  /** Индексы записей правила в списке состояний (для set/remove по индексу). */
  indices: number[];
  /** Есть ли среди записей блок poison (отравление). */
  poison: boolean;
  /** Человекочитаемое значение (flag → null): «3», «4, 1», «3с1». */
  summary: string | null;
}

export function parseCombatEntityKey(key: CombatEntityKey): { kind: CombatEntityKind; id: number } {
  if (key.startsWith('npc:')) return { kind: 'npc', id: Number(key.slice(4)) };

  return { kind: 'character', id: Number(key.slice(10)) };
}

export function combatEntityName(
  key: CombatEntityKey,
  memberships: GameCharacterMembership[],
  npcs: GameNpc[],
): string {
  const { kind, id } = parseCombatEntityKey(key);
  if (kind === 'npc') return npcs.find((npc) => npc.id === id)?.name ?? '';

  return memberships.find((membership) => membership.characterId === id)?.characterName ?? '';
}

/** CD-6: права правки — ГМ (любой) или владелец approved-персонажа; НПС — только ГМ. */
export function combatCardCanEdit(
  key: CombatEntityKey,
  canEdit: boolean,
  currentUserId: number | null,
  memberships: GameCharacterMembership[],
): boolean {
  if (canEdit) return true;
  const { kind, id } = parseCombatEntityKey(key);
  if (kind !== 'character' || currentUserId === null) return false;
  const membership = memberships.find((item) => item.characterId === id);

  return membership?.characterOwnerId === currentUserId && membership.membershipStatus === 'approved';
}

export function combatCardModel(
  key: CombatEntityKey,
  memberships: GameCharacterMembership[],
  npcs: GameNpc[],
  canEdit: boolean,
  currentUserId: number | null,
  overlay: GameCombatOverlay | null,
): CombatCardModel {
  const { kind, id } = parseCombatEntityKey(key);
  // Модель версий (Баг 1): в игре читается approved + оверлей. При наличии полного листа
  // (overlay.sheet, in-game редактор) он и есть база; иначе — approved-версия.
  const baseVersion =
    kind === 'npc'
      ? (npcs.find((npc) => npc.id === id)?.version ?? null)
      : (overlay?.sheet ?? memberships.find((membership) => membership.characterId === id)?.activeVersion ?? null);
  const hasChanges = overlay !== null && overlay.updatedAt !== '' && baseVersion !== null;
  // Ресурсы/состояния оверлея мержатся и поверх sheet: иначе клик-атака не видна после правки экипировки.
  const effectiveVersion =
    baseVersion === null
      ? null
      : hasChanges && overlay
        ? mergeCombatOverlay(overlay.sheet ?? baseVersion, overlay)
        : baseVersion;

  return {
    kind,
    entityId: id,
    entityKey: key,
    name: combatEntityName(key, memberships, npcs),
    version: baseVersion,
    overlay,
    effectiveVersion,
    canEdit: combatCardCanEdit(key, canEdit, currentUserId, memberships),
  };
}

function poisonName(state: CharacterStateValue, rules: Rule[]): string {
  const poisonRuleId = state.poison?.poisonRuleId;
  const rule = poisonRuleId ? rules.find((candidate) => candidate.id === poisonRuleId) : null;

  return rule?.name ?? 'Отравление';
}

function stateSummary(entries: CharacterStateValue[], spec: StateSpec | null, rules: Rule[]): string | null {
  if (spec === null) return null;
  if (entries.some((entry) => entry.poison)) {
    return entries.map((entry) => poisonName(entry, rules)).join(', ');
  }
  if (spec.value_type === 'number') {
    const values = entries.map((entry) => entry.value ?? 0);
    if (spec.aggregation === 'sum') return String(values.reduce((acc, value) => acc + value, 0));

    return values.join(', ');
  }
  if (spec.value_type === 'dimensional') {
    return entries
      .map((entry) => (entry.dimensionalValue ? new DimensionalNumber(entry.dimensionalValue).toString() : ''))
      .filter(Boolean)
      .join(', ');
  }

  return null;
}

/** Строки состояний боевой карточки: группировка записей по правилу (порядок сохранён). */
export function combatStateRows(states: CharacterStateValue[], rules: Rule[]): CombatStateRow[] {
  const groups = new Map<string, { indices: number[]; entries: CharacterStateValue[] }>();
  states.forEach((state, index) => {
    const group = groups.get(state.stateRuleId) ?? { indices: [], entries: [] };
    group.indices.push(index);
    group.entries.push(state);
    groups.set(state.stateRuleId, group);
  });

  return Array.from(groups.entries()).map(([ruleId, group]) => {
    const rule = rules.find((candidate) => candidate.id === ruleId);
    const spec = rule?.type === 'state' ? (rule.spec as StateSpec) : null;

    return {
      ruleId,
      code: rule?.code ?? '',
      name: rule?.name ?? ruleId,
      iconCode: spec?.icon_code ?? null,
      valueType: spec?.value_type ?? 'flag',
      aggregation: spec?.aggregation ?? 'independent',
      indices: group.indices,
      poison: group.entries.some((entry) => entry.poison),
      summary: stateSummary(group.entries, spec, rules),
    };
  });
}

/**
 * Текущее Истощение участника боя (число или null). Правило ищется в ревизии игры по коду
 * 'exhaustion' (state, number, sum); суммируются записи состояния. Показатель выводится
 * только при итоге > 0 («есть истощение»).
 */
export function combatExhaustion(states: CharacterStateValue[], rules: Rule[]): number | null {
  const rule = rules.find((candidate) => candidate.code === 'exhaustion' && candidate.type === 'state');
  if (!rule) return null;
  const entries = states.filter((state) => state.stateRuleId === rule.id);
  if (entries.length === 0) return null;
  const total = entries.reduce((sum, entry) => sum + (entry.value ?? 0), 0);

  return total > 0 ? total : null;
}

/** Вариант «Добавить состояние»: все state-правила ревизии. */
export interface CombatStateOption {
  ruleId: string;
  code: string;
  name: string;
  iconCode: string | null;
  valueType: StateValueType;
  aggregation: StateAggregation;
}

export function statePickerOptions(rules: Rule[]): CombatStateOption[] {
  return rules
    .filter((rule) => rule.type === 'state')
    .map((rule) => {
      const spec = rule.spec as StateSpec | undefined;

      return {
        ruleId: rule.id,
        code: rule.code,
        name: rule.name,
        iconCode: spec?.icon_code ?? null,
        valueType: spec?.value_type ?? 'flag',
        aggregation: spec?.aggregation ?? 'independent',
      };
    });
}

/** Кандидат макроса быстрых бросков (CD-8): характеристика, боевой стат или оружие. */
export interface QuickRollRecord {
  ruleId: string;
  /** Имя броска: подпись характеристики/оружия; для статов секции — «Ближний бой»/«Дальний бой». */
  name: string;
  value: DimensionalNumberValue;
  valueLabel: string;
}

/** Все кандидаты в быстрые броски обзора: характеристики + статы и оружия секций боя.
 *  Оружие помечается секцией («ББ:»/«ДБ:»), чтобы различать одинаковое оружие ближнего/дальнего боя. */
export function quickRollRecords(overview: CharacterOverview): QuickRollRecord[] {
  const records: QuickRollRecord[] = overview.characteristics.map((characteristic) => ({
    ruleId: characteristic.ruleId,
    name: characteristic.shortName ?? characteristic.name,
    value: characteristic.value,
    valueLabel: characteristic.valueLabel,
  }));

  const sections: { section: CombatMasterySection | null; name: string; prefix: string }[] = [
    { section: overview.combat?.melee ?? null, name: 'Ближний бой', prefix: 'ББ' },
    { section: overview.combat?.ranged ?? null, name: 'Дальний бой', prefix: 'ДБ' },
  ];
  for (const { section, name, prefix } of sections) {
    if (!section) continue;
    records.push({ ruleId: section.stat.ruleId, name, value: section.stat.value, valueLabel: section.stat.valueLabel });
    for (const weapon of section.weapons) {
      records.push({
        ruleId: weapon.ruleId,
        name: `${prefix}: ${weapon.shortName ?? weapon.name}`,
        value: weapon.value,
        valueLabel: weapon.valueLabel,
      });
    }
  }

  return records;
}

/** Резолюция макросов быстрых бросков (CD-8): ruleId → запись кандидатов.
 *  Сохраняет порядок записей; неизвестные ruleId отбрасываются. */
export function resolveQuickRollRecords(ruleIds: string[], records: QuickRollRecord[]): QuickRollRecord[] {
  const byId = new Map(records.map((record) => [record.ruleId, record]));

  return ruleIds.map((ruleId) => byId.get(ruleId)).filter((record): record is QuickRollRecord => record !== undefined);
}

/** Значение по умолчанию для нового состояния из пикера (number → 1, dimensional → 1с0). */
export function defaultStateEntry(option: CombatStateOption): Omit<CharacterStateValue, 'stateRuleId'> {
  if (option.valueType === 'number') return { value: 1 };
  if (option.valueType === 'dimensional') return { dimensionalValue: { base: 1, size: 0 } };

  return {};
}
