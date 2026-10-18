import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { AbilityCostKind } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityCostKind';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { ZoneId } from '@/modules/Roleplay/Rule/Dto/Ability/ZoneId';

/** Черновик цены зоны: виды, таблицы ОР со строковыми ключами. */
export class ZoneCostSpecService {
  constructor(private readonly emptyTable: Record<string, number>) {}

  createEmpty(kind: AbilityCostKind): AbilityCost {
    switch (kind) {
      case 'array':
        return { kind: 'array', levels_cost: [0] };
      case 'progression':
        return { kind: 'progression', max_level: 1, base_cost: 0, step: 0 };
      case 'automatic':
        return { kind: 'automatic' };
      case 'parameter':
        return { kind: 'parameter', parameter_code: '', per_unit: 1 };
      case 'parameter_table':
        return { kind: 'parameter_table', parameter_code: '', costs: { ...this.emptyTable } };
      case 'parameter_sum_tables':
        return { kind: 'parameter_sum_tables', max_level: 1, tables: {} };
    }
  }

  replaceKind(_current: AbilityCost | undefined, kind: AbilityCostKind): AbilityCost {
    return this.createEmpty(kind);
  }

  coerceZones(zones: Partial<Record<ZoneId, AbilityCost>>): Partial<Record<ZoneId, AbilityCost>> {
    const next: Partial<Record<ZoneId, AbilityCost>> = {};
    for (const [zone, cost] of Object.entries(zones)) {
      if (cost) {
        next[zone] = this.coerceCost(cost);
      }
    }

    return next;
  }

  coerceCost(cost: AbilityCost): AbilityCost {
    if (cost.kind === 'parameter_table') {
      return { ...cost, costs: this.coerceTable(cost.costs) };
    }
    if (cost.kind === 'parameter_sum_tables') {
      const tables: Record<string, Record<string, number>> = {};
      for (const [code, table] of Object.entries(cost.tables ?? {})) {
        tables[code] = this.coerceTable(table);
      }

      return { ...cost, tables };
    }

    return cost;
  }

  coerceTable(table: unknown): Record<string, number> {
    if (Array.isArray(table)) {
      const values: Record<string, number> = {};
      table.forEach((cost, index) => {
        values[String(index)] = this.asInt(cost);
      });

      return Object.keys(values).length > 0 ? values : { ...this.emptyTable };
    }
    if (!table || typeof table !== 'object') {
      return { ...this.emptyTable };
    }

    const values: Record<string, number> = {};
    for (const [key, cost] of Object.entries(table as Record<string, unknown>)) {
      values[String(key)] = this.asInt(cost);
    }

    return Object.keys(values).length > 0 ? values : { ...this.emptyTable };
  }

  tableKeys(table: Record<string, number>): string[] {
    return Object.keys(table).sort((left, right) => Number(left) - Number(right));
  }

  patchTableKey(table: Record<string, number>, fromKey: string, toKey: string): Record<string, number> {
    const key = toKey.trim() === '' ? fromKey : toKey;
    const next: Record<string, number> = {};
    for (const [current, cost] of Object.entries(table)) {
      if (current === fromKey) {
        next[key] = table[fromKey] ?? 0;
      } else if (current !== key) {
        next[current] = cost;
      }
    }

    return next;
  }

  patchTableCost(table: Record<string, number>, key: string, cost: number): Record<string, number> {
    return { ...table, [key]: this.asInt(cost) };
  }

  addTableRow(table: Record<string, number>): Record<string, number> {
    const keys = this.tableKeys(table);
    const last = keys.length === 0 ? -1 : Number(keys[keys.length - 1]);
    const nextKey = String(Number.isFinite(last) ? last + 1 : keys.length);

    return { ...table, [nextKey]: 0 };
  }

  removeTableRow(table: Record<string, number>, key: string): Record<string, number> {
    const next = { ...table };
    delete next[key];

    return Object.keys(next).length > 0 ? next : { ...this.emptyTable };
  }

  addSumTable(
    tables: Record<string, Record<string, number>>,
    parameterCode: string,
  ): Record<string, Record<string, number>> {
    const code = parameterCode.trim();
    if (code === '' || tables[code]) {
      return tables;
    }

    return { ...tables, [code]: { ...this.emptyTable } };
  }

  removeSumTable(
    tables: Record<string, Record<string, number>>,
    parameterCode: string,
  ): Record<string, Record<string, number>> {
    const next = { ...tables };
    delete next[parameterCode];

    return next;
  }

  renameSumTable(
    tables: Record<string, Record<string, number>>,
    fromCode: string,
    toCode: string,
  ): Record<string, Record<string, number>> {
    const code = toCode.trim();
    if (code === '' || code === fromCode) {
      return tables;
    }
    const next: Record<string, Record<string, number>> = {};
    for (const [current, table] of Object.entries(tables)) {
      if (current === fromCode) {
        next[code] = table;
      } else if (current !== code) {
        next[current] = table;
      }
    }

    return next;
  }

  replaceSumTable(
    tables: Record<string, Record<string, number>>,
    parameterCode: string,
    table: Record<string, number>,
  ): Record<string, Record<string, number>> {
    return { ...tables, [parameterCode]: this.coerceTable(table) };
  }

  parameterItems(parameters: AbilityParameter[]): { title: string; value: string }[] {
    return parameters.map((parameter) => ({
      title: parameter.label ? `${parameter.label} (${parameter.code})` : parameter.code,
      value: parameter.code,
    }));
  }

  purchaseParameterCodes(parameters: AbilityParameter[]): string[] {
    return parameters.filter((parameter) => parameter.resolution === 'purchase').map((parameter) => parameter.code);
  }

  unusedPurchaseCode(parameters: AbilityParameter[], tables: Record<string, Record<string, number>>): string {
    return this.purchaseParameterCodes(parameters).find((code) => tables[code] === undefined) ?? '';
  }

  private asInt(value: unknown): number {
    const n = Number(value);

    return Number.isFinite(n) ? Math.trunc(n) : 0;
  }
}
