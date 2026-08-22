import { describe, it, expect } from 'vitest';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { FieldTypeRegistry } from '@/modules/Core/UI/Service/Field/FieldTypeRegistry';
import type { IFieldTypeInterpreter } from '@/modules/Core/UI/Interface/Field/IFieldTypeInterpreter';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

const magnitude = (raw: unknown): number => DimensionalNumber.from(raw as { base: number; size: number }).toNumber();

const dimensionalNumberInterpreter: IFieldTypeInterpreter = {
  isActive: (_field, value) => value !== undefined && value !== null && value !== '',
  predicate: (_field, value) => {
    const threshold = (value as { mode?: string; from?: number }).from ?? 0;

    return (rowValue) => magnitude(rowValue) >= threshold;
  },
  compare: (_field, a, b) => magnitude(a) - magnitude(b),
  format: (_field, value) => `Значение: ${String(value)}`,
};

const strengthField: FilterField = { key: 'strength', label: 'Сила', type: 'dimensionalNumber' };

describe('Расширяемость: модуль регистрирует свой тип поля', () => {
  it('до регистрации тип неизвестен', () => {
    const registry = new FieldTypeRegistry();
    expect(registry.get('dimensionalNumber')).toBeUndefined();
  });

  it('после регистрации дескриптор резолвится и фильтрует по величине', () => {
    const registry = new FieldTypeRegistry();
    registry.register('dimensionalNumber', { interpreter: dimensionalNumberInterpreter });

    const descriptor = registry.get('dimensionalNumber');
    expect(descriptor?.interpreter).toBe(dimensionalNumberInterpreter);

    const predicate = descriptor!.interpreter.predicate(strengthField, { mode: 'from', from: 10 });
    expect(predicate({ base: 3, size: 2 })).toBe(true);
    expect(predicate({ base: 2, size: 2 })).toBe(false);
  });

  it('сортировка по величине, а не по строке', () => {
    expect(dimensionalNumberInterpreter.compare(strengthField, { base: 3, size: 2 }, { base: 2, size: 2 })).toBe(4);
  });
});
