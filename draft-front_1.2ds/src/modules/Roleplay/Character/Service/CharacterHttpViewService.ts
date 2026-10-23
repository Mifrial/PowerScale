import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacteristicValue } from '@/modules/Roleplay/Character/Dto/CharacteristicValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { CharacteristicPurchase } from '@/modules/Roleplay/Character/Dto/Editor/CharacteristicPurchase';
import type { ResourceLimitBonus } from '@/modules/Roleplay/Character/Dto/ResourceLimitBonus';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';
import type { CharacterWound } from '@/modules/Roleplay/Character/Dto/CharacterWound';
import type { CharacterWoundHolder } from '@/modules/Roleplay/Character/Dto/CharacterWoundHolder';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterBuildService } from '@/modules/Roleplay/Character/Service/CharacterBuildService';
import type { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';

/**
 * Плоский JSON PHP character.get / character.getList / game.getSheets → DTO наброска.
 */
export class CharacterHttpViewService {
  constructor(
    private readonly builds: CharacterBuildService,
    private readonly editor: CharacterEditorService,
  ) {}

  detailOf(payload: unknown): CharacterDetail {
    const record = this.recordOf(payload);
    if (this.hasNestedCharacter(record)) {
      return payload as CharacterDetail;
    }
    const character = this.listItemOf(record);
    const choices = this.recordOf(record.choices);
    const sheet = this.recordOf(record.sheet);

    return {
      character,
      version: this.versionOf({
        name: character.name,
        spaceCode: character.spaceCode,
        rulesRevision: character.rulesRevision,
        choices,
        sheet,
      }),
      actualVersion: this.intOf(record.actualVersion, 1),
      discussionChatId: null,
      previousVersion: null,
      ownerNotes: typeof record.ownerNotes === 'string' ? record.ownerNotes : undefined,
    };
  }

  migratedDetailOf(payload: unknown): CharacterDetail {
    const record = this.recordOf(payload);
    if (record.kind === 'conflicts') {
      throw new CharacterApiError('CHARACTER_INVALID', this.conflictMessagesOf(record.problems));
    }

    return this.detailOf(payload);
  }

  listItemOf(row: unknown): Character {
    const record = this.recordOf(row);
    const active = record.active === true;
    const status = record.status === 'draft' || record.status === 'ready' ? record.status : active ? 'ready' : 'draft';
    const choices = this.recordOf(record.choices);

    return {
      id: this.intOf(record.id),
      name: this.stringOf(record.name) || this.stringOf(choices.name),
      status,
      active,
      ownerId: this.intOf(record.ownerId),
      ownerName: this.stringOf(record.ownerName),
      raceId: null,
      raceLabel: null,
      gameId: null,
      gameName: null,
      spaceId: this.intOf(record.spaceId),
      spaceCode: this.stringOf(record.spaceCode),
      rulesRevision: this.intOf(record.revision ?? record.rulesRevision),
      shortDescription: this.nullableStringOf(choices.shortDescription ?? record.shortDescription),
      currentPoints: { os: 0, ol: 0, or: null },
      visibility: [],
      discussionChatId: null,
    };
  }

  versionOf(input: {
    name: string;
    spaceCode: string;
    rulesRevision: number;
    choices: Record<string, unknown>;
    sheet: Record<string, unknown>;
  }): CharacterVersion {
    const limits = this.recordOf(input.choices.limits);
    const purchases = this.purchasesOf(input.choices.characteristicPurchases, input.sheet.characteristicPurchases);

    return {
      name: this.stringOf(input.choices.name) || input.name,
      shortDescription: this.nullableStringOf(input.choices.shortDescription),
      fullDescription: this.nullableStringOf(input.choices.fullDescription),
      spaceCode: input.spaceCode,
      rulesRevision: this.intOf(input.choices.revision, input.rulesRevision),
      raceRuleCode: this.nullableStringOf(input.choices.raceCode ?? input.choices.raceRuleCode ?? input.choices.race),
      characteristics:
        this.liveCharacteristicsOf(input.sheet.characteristics) ??
        this.characteristicsOf(input.sheet.characteristicPurchases, purchases),
      characteristicPurchases: purchases,
      resources: this.resourcesOf(input.sheet.resources),
      abilities: this.abilitiesOf(input.choices.abilities),
      points: {
        osSpent: this.intOf(input.sheet.characteristicPurchaseOs),
        olSpent: 0,
        olTotal: 0,
        orSpent: 0,
        orTotal: typeof limits.or === 'number' ? limits.or : null,
      },
      money: this.intOf(input.sheet.money),
      ageYears: typeof input.choices.ageYears === 'number' ? input.choices.ageYears : null,
      inventory: this.inventoryOf(input.choices.inventory),
      states: this.statesFromSheet(input.sheet.states),
      senses: this.sensesOf(input.sheet.senses),
      customRules: Array.isArray(input.choices.customRules)
        ? (input.choices.customRules as CharacterVersion['customRules'])
        : [],
      budgets: {
        osTotal: typeof limits.os === 'number' ? limits.os : null,
        moneyBudget: typeof limits.money === 'number' ? limits.money : null,
      },
    };
  }

  /**
   * Снимок membership PHP: `{ name, rulesRevision, choices, sheet }`, не CharacterVersion.
   */
  versionFromStored(raw: unknown): CharacterVersion | null {
    if (raw === null || raw === undefined || typeof raw !== 'object' || Array.isArray(raw)) return null;
    const record = raw as Record<string, unknown>;
    if (Array.isArray(record.characteristics)) return raw as CharacterVersion;

    return this.versionOf({
      name: this.stringOf(record.name),
      spaceCode: this.stringOf(record.spaceCode),
      rulesRevision: this.intOf(record.rulesRevision ?? record.revision),
      choices: this.recordOf(record.choices),
      sheet: this.recordOf(record.sheet),
    });
  }

  /**
   * Mock-режим досчитывает лист редактором. PHP-режим отдаёт уже посчитанный sheet.
   */
  materialize(
    version: CharacterVersion,
    spaceId: number,
    rules: Rule[],
    keywords: readonly Keyword[] = [],
  ): CharacterVersion {
    if (rules.length === 0) return version;

    return this.editor.toVersion(
      this.builds.fromVersion(version, spaceId, rules),
      rules,
      {
        osTotal: version.budgets?.osTotal ?? null,
        orTotal: version.points.orTotal,
        moneyBudget: version.budgets?.moneyBudget ?? null,
      },
      [...keywords],
    );
  }

  /**
   * Материализация листа без сброса текущего значения ресурсов (ОД в бою / PHP current).
   */
  materializeKeepingRuntime(
    version: CharacterVersion,
    spaceId: number,
    rules: Rule[],
    keywords: readonly Keyword[] = [],
  ): CharacterVersion {
    if (rules.length === 0) return version;

    try {
      const built = this.materialize(version, spaceId, rules, keywords);

      return {
        ...built,
        states: version.states,
        resources: this.withStoredCurrent(built.resources, version.resources),
      };
    } catch {
      return version;
    }
  }

  private withStoredCurrent(built: ResourceValue[], stored: ResourceValue[]): ResourceValue[] {
    const currentByCode = new Map(stored.map((row) => [row.ruleCode, row.current]));

    return built.map((row) => {
      const current = currentByCode.get(row.ruleCode);

      return current === undefined ? row : { ...row, current };
    });
  }

  private hasNestedCharacter(record: Record<string, unknown>): boolean {
    return record.character !== null && typeof record.character === 'object' && 'version' in record;
  }

  private purchasesOf(choicesRaw: unknown, sheetRaw: unknown): CharacteristicPurchase[] {
    const byCode = new Map<string, CharacteristicPurchase>();
    for (const row of [...this.purchaseRows(sheetRaw), ...this.purchaseRows(choicesRaw)]) {
      byCode.set(row.characteristicCode, row);
    }

    return [...byCode.values()];
  }

  private purchaseRows(raw: unknown): CharacteristicPurchase[] {
    if (!Array.isArray(raw)) return [];

    return raw.flatMap((row) => {
      const record = this.recordOf(row);
      const characteristicCode = this.stringOf(record.characteristicCode);
      const cost = this.intOf(record.cost);
      if (!characteristicCode) return [];

      return [{ characteristicCode, cost }];
    });
  }

  private liveCharacteristicsOf(raw: unknown): CharacteristicValue[] | null {
    if (!Array.isArray(raw)) return null;

    return raw.flatMap((row) => {
      const record = this.recordOf(row);
      const code = this.stringOf(record.characteristicCode || record.ruleCode);
      if (!code) return [];
      const modifiers = Array.isArray(record.modifiers)
        ? record.modifiers.flatMap((entry) => {
            const item = this.recordOf(entry);
            const source = this.stringOf(item.sourceRuleCode);

            return [
              {
                sourceRuleCode: source || null,
                sourceLabel: null,
                delta: this.intOf(item.delta),
                target: code,
                scope: null,
              },
            ];
          })
        : [];

      return [
        {
          ruleCode: code,
          base: this.dimOf(record.base ?? record.value),
          modifiers,
          ...(record.value === undefined ? {} : { value: this.dimOf(record.value) }),
          ...(typeof record.delta === 'number' ? { delta: record.delta } : {}),
        },
      ];
    });
  }

  private characteristicsOf(sheetRaw: unknown, purchases: CharacteristicPurchase[]): CharacteristicValue[] {
    const values = new Map<string, CharacteristicValue>();
    if (Array.isArray(sheetRaw)) {
      for (const row of sheetRaw) {
        const record = this.recordOf(row);
        const code = this.stringOf(record.characteristicCode);
        if (!code || record.value === undefined) continue;
        values.set(code, { ruleCode: code, base: this.dimOf(record.value), modifiers: [] });
      }
    }
    for (const purchase of purchases) {
      if (!values.has(purchase.characteristicCode)) {
        values.set(purchase.characteristicCode, {
          ruleCode: purchase.characteristicCode,
          base: { base: 0, size: 0 },
          modifiers: [],
        });
      }
    }

    return [...values.values()];
  }

  private resourcesOf(raw: unknown): ResourceValue[] {
    if (!Array.isArray(raw)) return [];

    return raw.flatMap((row) => {
      const record = this.recordOf(row);
      const code = this.stringOf(record.ruleCode);
      if (!code) return [];
      const current = this.dimOf(record.current);
      const bonuses = this.resourceBonusesOf(record.bonuses);

      return [{ ruleCode: code, current, base: this.dimOf(record.base ?? record.current), bonuses }];
    });
  }

  private resourceBonusesOf(raw: unknown): ResourceLimitBonus[] {
    if (!Array.isArray(raw)) return [];

    return raw.flatMap((row) => {
      const record = this.recordOf(row);
      const delta = this.intOf(record.delta);
      if (delta === 0) return [];

      return [
        {
          sourceRuleCode: this.nullableStringOf(record.sourceRuleCode),
          sourceLabel: this.nullableStringOf(record.sourceLabel),
          delta,
        },
      ];
    });
  }

  private abilitiesOf(raw: unknown): CharacterAbility[] {
    if (!Array.isArray(raw)) return [];

    return raw.flatMap((row) => {
      const record = this.recordOf(row);
      const ruleCode = this.stringOf(record.ruleCode);
      if (!ruleCode) return [];

      const ability: CharacterAbility = { ruleCode, level: this.intOf(record.level, 1) };
      const domain = this.stringOf(record.domain);
      const domainCode = this.stringOf(record.domainCode);
      if (domain) ability.domain = domain;
      if (domainCode) ability.domainCode = domainCode;
      if (typeof record.zone === 'string') ability.zone = record.zone;
      if (record.gifted === true) ability.gifted = true;
      const parameters = this.parametersOf(record.parameters);
      if (parameters) ability.parameters = parameters;

      return [ability];
    });
  }

  private inventoryOf(raw: unknown): InventoryItem[] {
    if (!Array.isArray(raw)) return [];

    return raw.flatMap((row) => {
      const record = this.recordOf(row);
      const custom = this.recordOf(record.custom);
      const id = this.intOf(record.id);
      if (id <= 0) return [];

      return [
        {
          id,
          ruleCode: this.nullableStringOf(record.ruleCode),
          quantity: this.intOf(record.quantity, 1),
          equipped: record.equipped === true,
          name: this.nullableStringOf(custom.name),
          description: this.nullableStringOf(custom.description),
          modifierRuleCodes: Array.isArray(record.modifiers)
            ? record.modifiers.filter((code): code is string => typeof code === 'string')
            : undefined,
        },
      ];
    });
  }

  private parametersOf(raw: unknown): CharacterAbility['parameters'] | undefined {
    if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) return undefined;
    const parameters: Record<string, DimensionalNumberValue | number> = {};
    for (const [code, value] of Object.entries(this.recordOf(raw))) {
      if (typeof value === 'number') parameters[code] = value;
      else if (value !== null && typeof value === 'object') parameters[code] = this.dimOf(value);
    }

    return Object.keys(parameters).length > 0 ? parameters : undefined;
  }

  sensesOf(raw: unknown): CharacterSenseValue[] {
    if (!Array.isArray(raw)) return [];
    const statuses = new Set<SenseStatus>(['precise', 'imprecise', 'vague', 'absent']);
    const senses: CharacterSenseValue[] = [];
    for (const row of raw) {
      if (row === null || typeof row !== 'object' || Array.isArray(row)) continue;
      const record = row as Record<string, unknown>;
      const status = this.stringOf(record.status);
      if (!statuses.has(status as SenseStatus) || status === 'absent') continue;
      const radius = this.dimOf(record.radius);
      senses.push({
        ruleCode: this.stringOf(record.ruleCode),
        value: this.intOf(record.value),
        modifiers: Array.isArray(record.modifiers)
          ? record.modifiers.flatMap((modifier) => {
              if (modifier === null || typeof modifier !== 'object') return [];
              const entry = modifier as Record<string, unknown>;

              return [
                {
                  sourceRuleCode: this.stringOf(entry.sourceRuleCode),
                  sourceLabel: null,
                  delta: this.intOf(entry.delta),
                  target: this.stringOf(record.ruleCode),
                  scope: null,
                },
              ];
            })
          : [],
        status: status as SenseStatus,
        radius,
        ...(typeof record.treatAsGoodDownTo === 'string'
          ? { treatAsGoodDownTo: record.treatAsGoodDownTo as CharacterSenseValue['treatAsGoodDownTo'] }
          : {}),
      });
    }

    return senses;
  }

  statesFromSheet(raw: unknown): CharacterStateValue[] {
    return Array.isArray(raw) ? raw.map((row) => this.stateFromSheet(row)) : [];
  }

  private stateFromSheet(row: unknown): CharacterStateValue {
    const record = this.recordOf(row);
    const nested = this.objectOf(record.wound);
    const sidecar = nested ?? record;
    const wound = this.woundFromSheet(sidecar, nested !== null);
    const id = this.stateIdOf(record.id);

    return {
      ...(id !== undefined ? { id } : {}),
      stateRuleCode: this.stringOf(record.stateRuleCode),
      ...(typeof record.value === 'number' ? { value: record.value } : {}),
      ...this.dimensionalStateValue(record),
      ...(record.poison && typeof record.poison === 'object'
        ? { poison: record.poison as CharacterStateValue['poison'] }
        : {}),
      ...(typeof record.dotTurnsLeft === 'number' ? { dotTurnsLeft: record.dotTurnsLeft } : {}),
      ...(typeof record.boundSustainId === 'string' ? { boundSustainId: record.boundSustainId } : {}),
      ...(typeof record.boundSourceKey === 'string' ? { boundSourceKey: record.boundSourceKey } : {}),
      ...(record.maim && typeof record.maim === 'object' ? { maim: record.maim as CharacterStateValue['maim'] } : {}),
      ...(wound ? { wound } : {}),
    };
  }

  private dimensionalStateValue(
    record: Record<string, unknown>,
  ): { dimensionalValue: CharacterStateValue['dimensionalValue'] } | Record<string, never> {
    const explicit = record.dimensionalValue;
    const nested = record.value;
    const source = explicit && typeof explicit === 'object' ? explicit : nested;
    if (source === null || typeof source !== 'object' || Array.isArray(source)) return {};
    const pair = source as Record<string, unknown>;
    if (typeof pair.base !== 'number' || typeof pair.size !== 'number') return {};

    return { dimensionalValue: { base: pair.base, size: pair.size } };
  }

  private woundFromSheet(sidecar: Record<string, unknown>, nested: boolean): CharacterWound | undefined {
    const hasSidecar =
      nested ||
      'bandage' in sidecar ||
      'clotting' in sidecar ||
      'internal' in sidecar ||
      'aided' in sidecar ||
      'heldBy' in sidecar;
    if (!hasSidecar) return undefined;

    return {
      bandage: typeof sidecar.bandage === 'number' ? sidecar.bandage : 0,
      clotting: typeof sidecar.clotting === 'number' ? sidecar.clotting : 0,
      internal: sidecar.internal === true,
      aided: sidecar.aided === true,
      heldBy: this.heldByFromSheet(sidecar.heldBy),
    };
  }

  private heldByFromSheet(raw: unknown): CharacterWound['heldBy'] {
    if (raw === null || raw === undefined || raw === '') return null;
    if (typeof raw === 'string') return raw;
    const record = this.objectOf(raw);
    if (!record) return null;
    const kind = record.kind;
    const id = record.id;
    if ((kind === 'character' || kind === 'npc') && typeof id === 'number' && id >= 1) {
      return { kind, id } satisfies CharacterWoundHolder;
    }

    return null;
  }

  private objectOf(value: unknown): Record<string, unknown> | null {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return null;

    return value as Record<string, unknown>;
  }

  private stateIdOf(value: unknown): number | undefined {
    if (typeof value === 'number' && Number.isInteger(value) && value >= 1) return value;
    if (typeof value === 'string' && /^\d+$/.test(value)) {
      const id = Number(value);
      if (id >= 1) return id;
    }

    return undefined;
  }

  private dimOf(raw: unknown): DimensionalNumberValue {
    if (typeof raw === 'number') return { base: raw, size: 0 };
    const record = this.recordOf(raw);

    return { base: this.intOf(record.base), size: this.intOf(record.size) };
  }

  private recordOf(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' ? (value as Record<string, unknown>) : {};
  }

  private intOf(value: unknown, fallback = 0): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
  }

  private stringOf(value: unknown): string {
    return typeof value === 'string' ? value : '';
  }

  private nullableStringOf(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
  }

  private conflictMessagesOf(raw: unknown): string {
    if (!Array.isArray(raw)) return 'Character migration has conflicts';
    const messages = raw
      .map((row) => this.recordOf(row))
      .map((row) => this.stringOf(row.message))
      .filter((message) => message !== '');

    return messages.join('; ') || 'Character migration has conflicts';
  }
}
