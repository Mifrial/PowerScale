import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { MaybeFilterValue } from '@/modules/Core/UI/Dto/Filter/MaybeFilterValue';
import type { IFieldTypeInterpreter } from '@/modules/Core/UI/Interface/Field/IFieldTypeInterpreter';
import { BaseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Field/BaseFieldTypeInterpreter';

export class TreeSelectFieldTypeInterpreter implements IFieldTypeInterpreter {
  private readonly fallback = new BaseFieldTypeInterpreter();

  isActive(field: FilterField, value: MaybeFilterValue): boolean {
    return this.fallback.isActive(field, value);
  }

  predicate(field: FilterField, value: MaybeFilterValue): (rowValue: unknown) => boolean {
    const selectedCode = typeof value === 'string' ? value : null;
    if (!selectedCode) return () => false;
    const descendants = new Set<string>([selectedCode]);
    let changed = true;
    while (changed) {
      changed = false;
      for (const option of field.treeOptions ?? []) {
        if (
          option.parentValue !== null &&
          option.parentValue !== undefined &&
          descendants.has(String(option.parentValue)) &&
          !descendants.has(String(option.value))
        ) {
          descendants.add(String(option.value));
          changed = true;
        }
      }
    }

    return (rowValue) => rowValue !== null && rowValue !== undefined && descendants.has(String(rowValue));
  }

  compare(field: FilterField, left: unknown, right: unknown): number {
    return this.fallback.compare(field, left, right);
  }

  format(field: FilterField, value: MaybeFilterValue): string {
    const option = (field.treeOptions ?? []).find((item) => item.value === value);

    return option ? `${field.label}: ${option.path}` : this.fallback.format(field, value);
  }
}
