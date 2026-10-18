import { describe, expect, it } from 'vitest';
import { abilityParameterSpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilityParameterSpecService';

describe('AbilityParameterSpecService', () => {
  it('меняет kind scalar → dimensional, целые границы становятся парами размера 0', () => {
    const next = abilityParameterSpecService.withKind(
      { code: 'x', label: 'X', kind: 'scalar', resolution: 'purchase', default: 0, min: -3, max: 3 },
      'dimensional',
    );

    expect(next.kind).toBe('dimensional');
    expect(next.default).toEqual({ base: 0, size: 0 });
    expect(next.min).toEqual({ base: -3, size: 0 });
    expect(next.max).toEqual({ base: 3, size: 0 });
  });

  it('меняет kind dimensional → scalar и отбрасывает пары', () => {
    const next = abilityParameterSpecService.withKind(
      {
        code: 'x',
        label: 'X',
        kind: 'dimensional',
        resolution: 'purchase',
        default: { base: 3, size: 1 },
        min: { base: 3, size: -1 },
      },
      'scalar',
    );

    expect(next.kind).toBe('scalar');
    expect(next.default).toBe(0);
    expect(next.min).toBeUndefined();
    expect(next.max).toBeUndefined();
  });

  it('несовпадение kind и границ не приводит само', () => {
    const parameter = {
      code: 'x',
      label: 'X',
      kind: 'scalar' as const,
      resolution: 'purchase' as const,
      default: { base: 0, size: 0 },
      min: { base: -3, size: 0 },
      max: { base: 3, size: 0 },
    };

    expect(abilityParameterSpecService.mismatchedBoundFields(parameter)).toEqual(['default', 'min', 'max']);
    expect(abilityParameterSpecService.coerceBoundsToKind(parameter)).toEqual({
      code: 'x',
      label: 'X',
      kind: 'scalar',
      resolution: 'purchase',
      default: 0,
      min: -3,
      max: 3,
    });
  });

  it('кладёт в черновик только правила, у которых границы не того kind', () => {
    const broken = {
      id: null,
      code: 'innate-dexterity',
      type: 'ability' as const,
      name: 'Врождённая Ловкость',
      description: '',
      spaceId: 1,
      mechanics: [],
      createdAt: 1,
      spec: {
        type: 'trait' as const,
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        parameters: [
          {
            code: 'x',
            label: 'X',
            kind: 'scalar' as const,
            resolution: 'purchase' as const,
            default: { base: 0, size: 0 },
            min: { base: -3, size: 0 },
            max: { base: 3, size: 0 },
          },
        ],
      },
    };
    const ok = {
      ...broken,
      code: 'ok-trait',
      spec: {
        ...broken.spec,
        parameters: [{ code: 'x', label: 'X', kind: 'scalar' as const, resolution: 'purchase' as const, default: 0 }],
      },
    };
    const changed = abilityParameterSpecService.coerceMismatchedOnRules([broken, ok]);

    expect(changed.map((rule) => rule.code)).toEqual(['innate-dexterity']);
    expect(changed[0]?.spec).toMatchObject({
      parameters: [{ code: 'x', default: 0, min: -3, max: 3 }],
    });
  });

  it('пустой min снимает границу', () => {
    const next = abilityParameterSpecService.withScalarBound(
      { code: 'x', label: 'X', kind: 'scalar', resolution: 'purchase', default: 0, min: -3 },
      'min',
      null,
    );

    expect(next.min).toBeUndefined();
    expect(next.default).toBe(0);
  });
});
