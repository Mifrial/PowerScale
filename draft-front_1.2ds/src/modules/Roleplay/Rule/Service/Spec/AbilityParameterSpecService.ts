import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { ParameterValueKind } from '@/modules/Roleplay/Rule/Enum/Ability/ParameterValueKind';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Пустая строка параметра и смена kind / границ min-max-default.
 */
export class AbilityParameterSpecService {
  createEmpty(): AbilityParameter {
    return { code: '', label: '', kind: 'scalar', resolution: 'purchase', default: 0 };
  }

  withKind(parameter: AbilityParameter, kind: ParameterValueKind): AbilityParameter {
    if (kind === parameter.kind) {
      return { ...parameter, kind };
    }

    if (kind === 'scalar') {
      return {
        ...parameter,
        kind,
        default: this.toScalar(parameter.default) ?? 0,
        min: this.toScalar(parameter.min),
        max: this.toScalar(parameter.max),
      };
    }

    return {
      ...parameter,
      kind,
      default: this.toDimensional(parameter.default) ?? { base: 0, size: 0 },
      min: this.toDimensional(parameter.min),
      max: this.toDimensional(parameter.max),
    };
  }

  withScalarBound(parameter: AbilityParameter, field: 'default' | 'min' | 'max', value: number | null): AbilityParameter {
    if (field === 'default') {
      return { ...parameter, default: value ?? 0 };
    }

    return this.withOptional(parameter, field, value);
  }

  mismatchedBoundFields(parameter: AbilityParameter): ('default' | 'min' | 'max')[] {
    const fields: ('default' | 'min' | 'max')[] = [];
    for (const field of ['default', 'min', 'max'] as const) {
      const value = parameter[field];
      if (value === undefined) continue;
      const isPair = typeof value === 'object';
      if (parameter.kind === 'scalar' && isPair) fields.push(field);
      if (parameter.kind === 'dimensional' && typeof value === 'number') fields.push(field);
    }

    return fields;
  }

  coerceBoundsToKind(parameter: AbilityParameter): AbilityParameter {
    if (parameter.kind === 'scalar') {
      return {
        ...parameter,
        default: this.scalarBase(parameter.default) ?? 0,
        min: this.scalarBase(parameter.min),
        max: this.scalarBase(parameter.max),
      };
    }

    return {
      ...parameter,
      default: this.toDimensional(parameter.default) ?? { base: 0, size: 0 },
      min: this.toDimensional(parameter.min),
      max: this.toDimensional(parameter.max),
    };
  }

  /** Явно приводит границы параметров во всех способностях; нетронутые правила не возвращает. */
  coerceMismatchedOnRules(rules: Rule[]): Rule[] {
    const changed: Rule[] = [];
    for (const rule of rules) {
      if (rule.type !== 'ability' || !this.hasParameters(rule.spec)) continue;
      let dirty = false;
      const parameters = rule.spec.parameters.map((parameter) => {
        if (this.mismatchedBoundFields(parameter).length === 0) {
          return parameter;
        }
        dirty = true;

        return this.coerceBoundsToKind(parameter);
      });
      if (dirty) {
        changed.push({ ...rule, spec: { ...rule.spec, parameters } });
      }
    }

    return changed;
  }

  withDescription(parameter: AbilityParameter, description: string): AbilityParameter {
    const next = { ...parameter };
    if (description === '') {
      delete next.description;
    } else {
      next.description = description;
    }

    return next;
  }

  withLinked(
    parameter: AbilityParameter,
    linked: AbilityParameter['linked'] | null,
  ): AbilityParameter {
    const next = { ...parameter };
    if (linked === null) {
      delete next.linked;
    } else {
      next.linked = linked;
    }

    return next;
  }

  withDimensionalBound(
    parameter: AbilityParameter,
    field: 'default' | 'min' | 'max',
    value: DimensionalNumberValue | null,
  ): AbilityParameter {
    if (field === 'default') {
      return { ...parameter, default: value ?? { base: 0, size: 0 } };
    }

    return this.withOptional(parameter, field, value);
  }

  private withOptional(
    parameter: AbilityParameter,
    field: 'min' | 'max',
    value: number | DimensionalNumberValue | null,
  ): AbilityParameter {
    const next = { ...parameter };
    if (value === null) {
      delete next[field];
    } else {
      next[field] = value;
    }

    return next;
  }

  private hasParameters(spec: Rule['spec']): spec is AbilitySpec & { parameters: AbilityParameter[] } {
    return spec !== null && typeof spec === 'object' && 'parameters' in spec && Array.isArray(spec.parameters);
  }

  private scalarBase(value: AbilityParameter['default'] | undefined): number | undefined {
    if (typeof value === 'number' && Number.isInteger(value)) {
      return value;
    }
    if (value !== undefined && typeof value === 'object' && Number.isInteger(value.base)) {
      return value.base;
    }

    return undefined;
  }

  private toScalar(value: AbilityParameter['default'] | undefined): number | undefined {
    return typeof value === 'number' ? value : undefined;
  }

  private toDimensional(value: AbilityParameter['default'] | undefined): DimensionalNumberValue | undefined {
    if (value !== undefined && typeof value === 'object') {
      return value;
    }

    return typeof value === 'number' ? { base: value, size: 0 } : undefined;
  }
}
