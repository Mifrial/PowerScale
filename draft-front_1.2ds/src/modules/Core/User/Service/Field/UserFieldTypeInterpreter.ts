import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { MaybeFilterValue } from '@/modules/Core/UI/Dto/Filter/MaybeFilterValue';
import type { IFieldTypeInterpreter } from '@/modules/Core/UI/Interface/Field/IFieldTypeInterpreter';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { displayName } from '@/modules/Core/User/Utils/displayName';

/**
 * Фильтр по пользователю: значение — id, подпись — имя из каталога.
 */
export class UserFieldTypeInterpreter implements IFieldTypeInterpreter {
  isActive(_field: FilterField, value: MaybeFilterValue): boolean {
    return toUserId(value) !== null;
  }

  predicate(_field: FilterField, value: MaybeFilterValue): (rowValue: unknown) => boolean {
    const id = toUserId(value);

    return (rowValue) => id !== null && toUserId(rowValue) === id;
  }

  compare(_field: FilterField, a: unknown, b: unknown): number {
    if (a === null || a === undefined) return 1;
    if (b === null || b === undefined) return -1;

    return Number(a) - Number(b);
  }

  format(field: FilterField, value: MaybeFilterValue): string {
    const id = toUserId(value);
    if (id === null) return field.label;

    return `${field.label}: ${this.labelOf(id)}`;
  }

  private labelOf(id: number): string {
    const user = useUserStore().users.find((candidate) => candidate.id === id);
    if (!user) return `#${id}`;

    return displayName(user.name, user.surname, user.login) || `#${id}`;
  }
}

/**
 * Приводит значение фильтра к id пользователя.
 */
export function toUserId(value: unknown): number | null {
  if (typeof value === 'number') return Number.isInteger(value) && value > 0 ? value : null;
  if (typeof value === 'string' && value.trim() !== '') {
    const id = Number(value);

    return Number.isInteger(id) && id > 0 ? id : null;
  }

  return null;
}
