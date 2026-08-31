import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterPoisonValue } from '@/modules/Roleplay/Character/Dto/CharacterPoisonValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { ACCUMULATED_DAMAGE_STATE_CODE, POISONING_STATE_CODE } from '@/modules/Roleplay/Rule/init';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CombatMasterySection } from '@/modules/Roleplay/Character/Dto/Overview/CombatMasterySection';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';

import { resourceLimitBase } from '@/modules/Roleplay/Game/Utils/combatEffectiveState';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';

import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { liveActionPointsLimitService } from '@/modules/Roleplay/Character/init';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

import type { CombatEntityKind } from '@/modules/Roleplay/Game/Enum/CombatEntityKind';
import type { CombatCardModel } from '@/modules/Roleplay/Game/Dto/CombatCardModel';
import type { CombatStateRow } from '@/modules/Roleplay/Game/Dto/CombatStateRow';
import type { CombatStateOption } from '@/modules/Roleplay/Game/Dto/CombatStateOption';
import type { QuickRollRecord } from '@/modules/Roleplay/Game/Dto/QuickRollRecord';
export class CombatCardModelService {
  parseCombatEntityKey(key: CombatEntityKey): { kind: CombatEntityKind; id: number } {
    if (key.startsWith('npc:')) return { kind: 'npc', id: Number(key.slice(4)) };

    return { kind: 'character', id: Number(key.slice(10)) };
  }

  combatEntityName(key: CombatEntityKey, memberships: GameCharacterMembership[], npcs: GameNpc[]): string {
    const { kind, id } = this.parseCombatEntityKey(key);
    if (kind === 'npc') return npcs.find((npc) => npc.id === id)?.name ?? '';

    return memberships.find((membership) => membership.characterId === id)?.characterName ?? '';
  }

  /** CD-6: права правки — ГМ (любой) или владелец approved-персонажа; НПС — только ГМ. */
  combatCardCanEdit(
    key: CombatEntityKey,
    canEdit: boolean,
    currentUserId: number | null,
    memberships: GameCharacterMembership[],
  ): boolean {
    if (canEdit) return true;
    const { kind, id } = this.parseCombatEntityKey(key);
    if (kind !== 'character' || currentUserId === null) return false;
    const membership = memberships.find((item) => item.characterId === id);

    return membership?.characterOwnerId === currentUserId && membership.membershipStatus === 'active';
  }

  combatCardModel(
    key: CombatEntityKey,
    memberships: GameCharacterMembership[],
    npcs: GameNpc[],
    canEdit: boolean,
    currentUserId: number | null,
    overlay: GameCombatOverlay | null,
  ): CombatCardModel {
    const { kind, id } = this.parseCombatEntityKey(key);
    const membership = kind === 'character' ? memberships.find((item) => item.characterId === id) : undefined;
    const baseVersion =
      kind === 'npc'
        ? (npcs.find((npc) => npc.id === id)?.version ?? null)
        : (membership?.approvedCharacterVersion ?? null);
    const hasChanges = overlay !== null && overlay.updatedAt !== '' && baseVersion !== null;
    const effectiveVersion =
      kind === 'character'
        ? sessionCharacterService.resolve(membership?.approvedCharacterVersion ?? null, overlay)
        : baseVersion === null
          ? null
          : hasChanges && overlay
            ? combatOverlayService.mergeCombatOverlay(overlay.sheet ?? baseVersion, overlay)
            : baseVersion;

    return {
      kind,
      entityId: id,
      entityKey: key,
      name: this.combatEntityName(key, memberships, npcs),
      version: baseVersion,
      overlay,
      effectiveVersion,
      canEdit: this.combatCardCanEdit(key, canEdit, currentUserId, memberships),
    };
  }

  poisonName(state: CharacterStateValue, rules: Rule[]): string {
    const poisonRuleCode = state.poison?.poisonRuleCode;
    const rule = poisonRuleCode ? rules.find((candidate) => candidate.code === poisonRuleCode) : null;

    return rule?.name ?? 'Отравление';
  }

  private maimUnitShort(unit: NonNullable<CharacterStateValue['maim']>['healUnit']): string {
    if (unit === 'days') return 'дн.';
    if (unit === 'decades') return 'дек.';
    if (unit === 'months') return 'мес.';

    return 'лет';
  }

  /**
   * Полный срок увечья на тайле: интервал −1 × сила (или «пост.»).
   * Пустая строка, если срока нет.
   */
  maimTotalDurationLabel(state: CharacterStateValue): string {
    const maim = state.maim;
    if (!maim) return '';
    if (maim.permanent) return 'пост.';
    if (maim.healTotal == null || !maim.healUnit) return '';
    const total = maim.healTotal * Math.max(0, state.value ?? 0);

    return `${total} ${this.maimUnitShort(maim.healUnit)}`;
  }

  /** Подпись одной записи увечья на карточке (сила + срок + флаги). */
  maimStateLabel(state: CharacterStateValue): string {
    const strength = String(state.value ?? 0);
    const maim = state.maim;
    if (!maim) return strength;
    const bits = [strength];
    if (maim.permanent) bits.push('пост.');
    else if (maim.healTotal != null && maim.healUnit)
      bits.push(`${maim.healTotal} ${this.maimUnitShort(maim.healUnit)}`);
    if (maim.disfiguring) bits.push('обезобр.');
    if (maim.lethal) bits.push('смерт.');

    return bits.join(' · ');
  }

  private stateSummary(entries: CharacterStateValue[], spec: StateSpec | null, rules: Rule[]): string | null {
    if (spec === null) return null;
    const stateCode = rules.find((rule) => rule.code === entries[0]?.stateRuleCode)?.code;
    if (entries.some((entry) => entry.poison)) {
      return entries.map((entry) => this.poisonName(entry, rules)).join(', ');
    }
    if (entries.some((entry) => entry.maim)) {
      return entries.map((entry) => this.maimStateLabel(entry)).join('; ');
    }
    if (spec.value_type === 'number') {
      const values = entries.map((entry) => entry.value ?? 0);
      if (spec.aggregation === 'sum') return String(values.reduce((acc, value) => acc + value, 0));

      return values.join(', ');
    }
    if (spec.value_type === 'dimensional') {
      if (spec.aggregation === 'sum' && stateCode === ACCUMULATED_DAMAGE_STATE_CODE) {
        return entries
          .reduce(
            (total, entry) => total.add(new DimensionalNumber(entry.dimensionalValue ?? { base: 0, size: 0 })),
            new DimensionalNumber({ base: 0, size: 0 }),
          )
          .toString();
      }

      return entries
        .map((entry) => (entry.dimensionalValue ? new DimensionalNumber(entry.dimensionalValue).toString() : ''))
        .filter(Boolean)
        .join(', ');
    }

    return null;
  }

  /** Строки состояний боевой карточки: группировка записей по правилу (порядок сохранён). */
  combatStateRows(states: CharacterStateValue[], rules: Rule[]): CombatStateRow[] {
    const groups = new Map<string, { indices: number[]; entries: CharacterStateValue[] }>();
    states.forEach((state, index) => {
      const group = groups.get(state.stateRuleCode) ?? { indices: [], entries: [] };
      group.indices.push(index);
      group.entries.push(state);
      groups.set(state.stateRuleCode, group);
    });

    return Array.from(groups.entries()).map(([ruleCode, group]) => {
      const rule = rules.find((candidate) => candidate.code === ruleCode);
      const spec = rule?.type === 'state' ? (rule.spec as StateSpec) : null;

      return {
        ruleCode,
        code: rule?.code ?? '',
        name: rule?.name ?? ruleCode,
        iconCode: spec?.icon_code ?? null,
        valueType: spec?.value_type ?? 'flag',
        aggregation: spec?.aggregation ?? 'independent',
        indices: group.indices,
        poison: group.entries.some((entry) => entry.poison),
        summary: this.stateSummary(group.entries, spec, rules),
      };
    });
  }

  /**
   * Текущее Истощение участника боя (число или null). Правило ищется в ревизии игры по коду
   * 'exhaustion' (state, number, sum); суммируются записи состояния. Показатель выводится
   * только при итоге > 0 («есть истощение»).
   */
  combatExhaustion(states: CharacterStateValue[], rules: Rule[]): number | null {
    const rule = rules.find((candidate) => candidate.code === 'exhaustion' && candidate.type === 'state');
    if (!rule) return null;
    const entries = states.filter((state) => state.stateRuleCode === rule.code);
    if (entries.length === 0) return null;
    const total = entries.reduce((sum, entry) => sum + (entry.value ?? 0), 0);

    return total > 0 ? total : null;
  }

  /**
   * Суммарная сила увечий участника. Правило ищется по коду 'maim'; суммируются все записи.
   * Показывается только при итоге > 0.
   */
  combatMaim(states: CharacterStateValue[], rules: Rule[]): number | null {
    const rule = rules.find((candidate) => candidate.code === 'maim' && candidate.type === 'state');
    if (!rule) return null;
    const entries = states.filter((state) => state.stateRuleCode === rule.code);
    if (entries.length === 0) return null;
    const total = entries.reduce((sum, entry) => sum + (entry.value ?? 0), 0);

    return total > 0 ? total : null;
  }

  /** Текущие ОД и лимит (базовые пункты). Нет ресурса action-points — null. */
  combatActionPoints(version: CharacterVersion, rules: Rule[]): { current: number; max: number } | null {
    const rule = rules.find((candidate) => candidate.code === ACTION_POINTS_CODE && candidate.type === 'resource');
    if (!rule) return null;
    const resource = version.resources.find((item) => item.ruleCode === rule.code);
    if (!resource) return null;

    const live = liveActionPointsLimitService.liveActionPointsLimit(
      version,
      rules,
      stateRuntimeEffectsService.effectiveCharacteristicValues(version, rules),
    );
    const max = live ?? Math.max(0, resourceLimitBase(resource));

    return { current: Math.min(resource.current.base, max), max };
  }

  statePickerOptions(rules: Rule[]): CombatStateOption[] {
    return rules
      .filter((rule) => rule.type === 'state')
      .map((rule) => {
        const spec = rule.spec as StateSpec | undefined;

        return {
          ruleCode: rule.code,
          code: rule.code,
          name: rule.name,
          iconCode: spec?.icon_code ?? null,
          valueType: spec?.value_type ?? 'flag',
          aggregation: spec?.aggregation ?? 'independent',
        };
      });
  }

  /** Все кандидаты в быстрые броски обзора: характеристики + статы и оружия секций боя.
   *  Оружие помечается секцией («ББ:»/«ДБ:»), чтобы различать одинаковое оружие ближнего/дальнего боя. */
  quickRollRecords(overview: CharacterOverview): QuickRollRecord[] {
    const records: QuickRollRecord[] = overview.characteristics.map((characteristic) => ({
      ruleCode: characteristic.ruleCode,
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
      records.push({
        ruleCode: section.stat.ruleCode,
        name,
        value: section.stat.value,
        valueLabel: section.stat.valueLabel,
      });
      for (const weapon of section.weapons) {
        records.push({
          ruleCode: weapon.ruleCode,
          name: `${prefix}: ${weapon.shortName ?? weapon.name}`,
          value: weapon.value,
          valueLabel: weapon.valueLabel,
        });
      }
    }

    return records;
  }

  /** Резолюция макросов быстрых бросков (CD-8): ruleCode → запись кандидатов.
   *  Сохраняет порядок записей; неизвестные ruleCode отбрасываются. */
  resolveQuickRollRecords(ruleCodes: string[], records: QuickRollRecord[]): QuickRollRecord[] {
    const byId = new Map(records.map((record) => [record.ruleCode, record]));

    return ruleCodes
      .map((ruleCode) => byId.get(ruleCode))
      .filter((record): record is QuickRollRecord => record !== undefined);
  }

  poisonRuleOptions(rules: Rule[]): { ruleCode: string; name: string }[] {
    return rules.filter((rule) => rule.type === 'poison').map((rule) => ({ ruleCode: rule.code, name: rule.name }));
  }

  poisonValueFromRule(rules: Rule[], poisonRuleCode: string | null): CharacterPoisonValue {
    const rule = poisonRuleCode
      ? rules.find((item) => item.code === poisonRuleCode && item.type === 'poison')
      : undefined;
    if (!rule) {
      return { poisonRuleCode: null, strength: { base: 1, size: 0 } };
    }
    const spec = rule.spec as PoisonSpec | undefined;

    return {
      poisonRuleCode: rule.code,
      damage_type_code: spec?.damage_type_code,
      strength: spec?.default_strength ?? { base: 1, size: 0 },
      periodicity: spec?.default_periodicity,
      decay: spec?.default_decay,
    };
  }

  resolvedPoisonValue(state: CharacterStateValue, rules: Rule[]): CharacterPoisonValue {
    const fromRule = this.poisonValueFromRule(rules, state.poison?.poisonRuleCode ?? null);

    return {
      poisonRuleCode: state.poison?.poisonRuleCode ?? fromRule.poisonRuleCode,
      damage_type_code: state.poison?.damage_type_code ?? fromRule.damage_type_code,
      strength: state.poison?.strength ?? fromRule.strength,
      periodicity: state.poison?.periodicity ?? fromRule.periodicity,
      decay: state.poison?.decay ?? fromRule.decay,
    };
  }

  resolvedPoisonStrength(state: CharacterStateValue, rules: Rule[]): DimensionalNumberValue | null {
    return this.resolvedPoisonValue(state, rules).strength ?? null;
  }

  /** Значение по умолчанию для нового состояния из пикера (number → 1, dimensional → 1с0). */
  defaultStateEntry(option: CombatStateOption, rules: Rule[] = []): Omit<CharacterStateValue, 'stateRuleCode'> {
    if (option.code === POISONING_STATE_CODE) {
      const first = this.poisonRuleOptions(rules)[0];

      return { poison: this.poisonValueFromRule(rules, first?.ruleCode ?? null) };
    }
    if (option.valueType === 'number') return { value: 1 };
    if (option.valueType === 'dimensional') return { dimensionalValue: { base: 1, size: 0 } };

    return {};
  }
}
