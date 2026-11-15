import { describe, expect, it } from 'vitest';
import { parameterLimitName } from '@/modules/Roleplay/Rule/Utils/parameterLimitName';

describe('parameterLimitName', () => {
  it('скалярный потолок печатается числом', () => {
    expect(parameterLimitName('Сопротивление магии X', { x: { base: 3, size: 0 } })).toBe(
      'Сопротивление магии до 3',
    );
  });

  it('размерный потолок печатается парой, а не целым', () => {
    expect(parameterLimitName('Кристалл X', { x: { base: 4, size: -1 } })).toBe('Кристалл до 4↓');
    expect(parameterLimitName('Кристалл X', { x: { base: 5, size: 0 } })).toBe('Кристалл до 5');
  });

  it('имя без X — потолок в скобках', () => {
    expect(parameterLimitName('Кристалл', { x: { base: 4, size: -1 } })).toBe('Кристалл (до 4↓)');
  });
});
