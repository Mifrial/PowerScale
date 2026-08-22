import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { MaybeFilterValue } from '@/modules/Core/UI/Dto/Filter/MaybeFilterValue';
import type { ActiveChip } from '@/modules/Core/UI/Dto/Filter/ActiveChip';
import { fieldTypeRegistry } from '@/modules/Core/UI/Service/Instance/fieldTypeRegistry';
import { baseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Instance/baseFieldTypeInterpreter';

export class FilterChipService {
  isActive(field: FilterField, value: MaybeFilterValue): boolean {
    return this.interpreter(field).isActive(field, value);
  }

  buildChips(fields: FilterField[], values: Record<string, MaybeFilterValue>): ActiveChip[] {
    return fields
      .filter((f) => this.isActive(f, values[f.key]))
      .map((f) => ({
        key: f.key,
        label: this.format(f, values[f.key]),
      }));
  }

  format(field: FilterField, value: MaybeFilterValue): string {
    return this.interpreter(field).format(field, value);
  }

  private interpreter(field: FilterField) {
    return fieldTypeRegistry.get(field.type)?.interpreter ?? baseFieldTypeInterpreter;
  }
}
