import { describe, expect, it } from 'vitest';
import { mechanicPayloadInspectorService } from '@/modules/Roleplay/Rule/Service/Instance/mechanicPayloadInspectorService';

const service = mechanicPayloadInspectorService;

describe('MechanicPayloadInspectorService', () => {
  it('round-trip сохраняет известный payload и лишнее вложенное поле', () => {
    const payload = {
      type: 'roll',
      data: { diceCount: 3 },
      futureField: { nested: [1] },
    };
    expect(service.parse(service.format(payload))).toEqual(payload);
    expect(service.isKnownType(payload)).toBe(true);
  });

  it('неизвестный type остаётся и не считается известным', () => {
    const payload = { type: 'future_mechanic', extra: { a: 1 } };
    expect(service.parse(service.format(payload))).toEqual(payload);
    expect(service.isKnownType(payload)).toBe(false);
  });

  it('считает absent только null, undefined и пустой массив', () => {
    expect(service.isAbsent(null)).toBe(true);
    expect(service.isAbsent(undefined)).toBe(true);
    expect(service.isAbsent([])).toBe(true);
    expect(service.isAbsent({})).toBe(false);
    expect(service.isKnownType({})).toBe(false);
  });

  it('отклоняет пустую строку, примитив и битый JSON', () => {
    expect(() => service.parse('')).toThrow();
    expect(() => service.parse('   ')).toThrow();
    expect(() => service.parse('1')).toThrow();
    expect(() => service.parse('"roll"')).toThrow();
    expect(() => service.parse('null')).toThrow();
    expect(() => service.parse('{')).toThrow();
  });

  it('пустой массив не подменяет на null', () => {
    expect(service.parse('[]')).toEqual([]);
  });
});
