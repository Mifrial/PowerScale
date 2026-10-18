import { describe, expect, it } from 'vitest';
import { zoneCostSpecService } from '@/modules/Roleplay/Rule/Service/Instance/zoneCostSpecService';

describe('ZoneCostSpecService', () => {
  it('массив costs поднимает в объект со строковыми ключами, включая 0', () => {
    expect(zoneCostSpecService.coerceTable([0, 2, 4])).toEqual({ '0': 0, '1': 2, '2': 4 });
  });

  it('coerceZones чинит parameter_sum_tables', () => {
    const next = zoneCostSpecService.coerceZones({
      or: {
        kind: 'parameter_sum_tables',
        max_level: 9,
        tables: {
          strength: [0, 2, 4] as unknown as Record<string, number>,
        },
      },
    });

    expect(next.or).toMatchObject({
      kind: 'parameter_sum_tables',
      max_level: 9,
      tables: { strength: { '0': 0, '1': 2, '2': 4 } },
    });
  });

  it('смена kind не тащит чужие поля', () => {
    const next = zoneCostSpecService.replaceKind(
      { kind: 'parameter_sum_tables', max_level: 9, tables: { strength: { '0': 0 } } },
      'array',
    );

    expect(next).toEqual({ kind: 'array', levels_cost: [0] });
  });

  it('переименование ключа таблицы не схлопывает в список', () => {
    const table = zoneCostSpecService.patchTableKey({ '0': 0, '1': 2 }, '1', '2');

    expect(table).toEqual({ '0': 0, '2': 2 });
    expect(Array.isArray(table)).toBe(false);
  });
});
