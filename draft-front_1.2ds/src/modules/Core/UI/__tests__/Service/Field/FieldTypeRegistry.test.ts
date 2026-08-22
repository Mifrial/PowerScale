import { describe, it, expect } from 'vitest';
import { BaseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Field/BaseFieldTypeInterpreter';
import { FieldTypeRegistry } from '@/modules/Core/UI/Service/Field/FieldTypeRegistry';

const interpreter = new BaseFieldTypeInterpreter();

describe('FieldTypeRegistry', () => {
  it('незарегистрированный тип → undefined', () => {
    const registry = new FieldTypeRegistry();
    expect(registry.get('string')).toBeUndefined();
  });

  it('register → get возвращает дескриптор', () => {
    const registry = new FieldTypeRegistry();
    registry.register('string', { interpreter });
    expect(registry.get('string')).toEqual({ interpreter });
  });

  it('повторный register перезаписывает', () => {
    const registry = new FieldTypeRegistry();
    const other = new BaseFieldTypeInterpreter();
    registry.register('string', { interpreter });
    registry.register('string', { interpreter: other });
    expect(registry.get('string')?.interpreter).toBe(other);
  });
});
