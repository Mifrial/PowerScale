import { describe, it, expect } from 'vitest';
import { BaseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Field/BaseFieldTypeInterpreter';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

const interpreter = new BaseFieldTypeInterpreter();

function field(type: string, options?: FilterField['options']): FilterField {
  return { key: 'value', label: 'Значение', type, options };
}

describe('BaseFieldTypeInterpreter.isActive', () => {
  it('string: пустое значение не активно', () => {
    expect(interpreter.isActive(field('string'), '')).toBe(false);
    expect(interpreter.isActive(field('string'), 'x')).toBe(true);
  });
  it('select: false — валидное значение опции', () => {
    const f = field('select', [
      { label: 'Активен', value: true },
      { label: 'Неактивен', value: false },
    ]);
    expect(interpreter.isActive(f, false)).toBe(true);
  });
  it('boolean: активно только true', () => {
    expect(interpreter.isActive(field('boolean'), true)).toBe(true);
    expect(interpreter.isActive(field('boolean'), false)).toBe(false);
  });
  it('обёрнутые значения (equals/from/interval)', () => {
    expect(interpreter.isActive(field('string'), { mode: 'contains', value: 'x' })).toBe(true);
    expect(interpreter.isActive(field('datetime'), { mode: 'from', from: '2024-01-01' })).toBe(true);
    expect(interpreter.isActive(field('number'), { mode: 'interval', from: 1, to: 2 })).toBe(true);
  });
});

describe('BaseFieldTypeInterpreter.predicate', () => {
  it('string contains/equals', () => {
    const contains = interpreter.predicate(field('string'), { mode: 'contains', value: 'an' });
    expect(contains('banana')).toBe(true);
    expect(contains('apple')).toBe(false);

    const equals = interpreter.predicate(field('string'), { mode: 'equals', value: 'apple' });
    expect(equals('apple')).toBe(true);
    expect(equals('pineapple')).toBe(false);
  });

  it('number equals/from/interval', () => {
    const equals = interpreter.predicate(field('number'), { mode: 'equals', value: 5 });
    expect(equals(5)).toBe(true);
    expect(equals(6)).toBe(false);

    const from = interpreter.predicate(field('number'), { mode: 'from', from: 5 });
    expect(from(5)).toBe(true);
    expect(from(4)).toBe(false);

    const interval = interpreter.predicate(field('number'), { mode: 'interval', from: 5, to: 10 });
    expect(interval(7)).toBe(true);
    expect(interval(11)).toBe(false);
  });

  it('select/active: равенство по значению опции', () => {
    const f = field('select', [
      { label: 'Активен', value: true },
      { label: 'Неактивен', value: false },
    ]);
    const onlyActive = interpreter.predicate(f, true);
    expect(onlyActive(true)).toBe(true);
    expect(onlyActive(false)).toBe(false);
  });

  it('datetime from/equals (по дню)', () => {
    const from = interpreter.predicate(field('datetime'), { mode: 'from', from: '2024-01-01T00:00:00Z' });
    expect(from('2024-06-01T00:00:00Z')).toBe(true);
    expect(from('2023-01-01T00:00:00Z')).toBe(false);

    const equals = interpreter.predicate(field('datetime'), { mode: 'equals', value: '2024-01-01T12:00:00Z' });
    expect(equals('2024-01-01T18:00:00Z')).toBe(true);
    expect(equals('2024-01-02T12:00:00Z')).toBe(false);
  });
});

describe('BaseFieldTypeInterpreter.compare', () => {
  it('number по величине', () => {
    expect(interpreter.compare(field('number'), 5, 3)).toBeGreaterThan(0);
    expect(interpreter.compare(field('number'), 3, 5)).toBeLessThan(0);
  });
  it('datetime по timestamp', () => {
    expect(interpreter.compare(field('datetime'), '2024-01-02T00:00:00Z', '2024-01-01T00:00:00Z')).toBeGreaterThan(0);
  });
  it('string по алфавиту', () => {
    expect(interpreter.compare(field('string'), 'b', 'a')).toBeGreaterThan(0);
  });
  it('boolean: false раньше true', () => {
    expect(interpreter.compare(field('boolean'), false, true)).toBeLessThan(0);
  });
});

describe('BaseFieldTypeInterpreter.format', () => {
  it('select: подставляет label опции', () => {
    const f = field('select', [
      { label: 'Активен', value: true },
      { label: 'Неактивен', value: false },
    ]);
    expect(interpreter.format(f, true)).toBe('Значение: Активен');
  });
  it('string contains', () => {
    expect(interpreter.format(field('string'), { mode: 'contains', value: 'x' })).toBe('Значение: содержит "x"');
  });
});
