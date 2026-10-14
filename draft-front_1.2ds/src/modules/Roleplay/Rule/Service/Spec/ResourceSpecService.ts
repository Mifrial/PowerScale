import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';

type ResourceDocument = ResourceSpec & { initial_value?: DimensionalNumberValue | number | null };

/** Пустая и нормализованная спека ресурса. */
export class ResourceSpecService {
  createEmpty(): ResourceSpec {
    return {
      is_dimensional: true,
      auto_add: false,
      check_token: false,
      turn_refill: false,
      limit: { base: { base: 3, size: 0 }, adjustments: [] },
    };
  }

  fromRuleSpec(value: RuleSpec | null): ResourceSpec {
    if (!value) return this.createEmpty();
    const spec = value as ResourceDocument;
    const base: DimensionalNumberValue | number | undefined =
      spec.limit?.base ?? (spec.initial_value as DimensionalNumberValue | number | null | undefined) ?? undefined;

    return {
      is_dimensional: spec.is_dimensional ?? true,
      auto_add: spec.auto_add ?? false,
      check_token: spec.check_token ?? false,
      turn_refill: spec.turn_refill ?? false,
      limit: {
        base: base ?? (spec.is_dimensional ? { base: 3, size: 0 } : 0),
        adjustments: spec.limit?.adjustments ?? [],
      },
    };
  }
}
