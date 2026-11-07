import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { User } from '@/modules/Core/User/Dto/User';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { UserFieldTypeInterpreter, toUserId } from '@/modules/Core/User/Service/Field/UserFieldTypeInterpreter';

const field: FilterField = { key: 'ownerId', label: 'Владелец', type: 'user' };

function user(id: number, name: string, surname: string, login: string): User {
  return {
    id,
    name,
    surname,
    login,
    email: null,
    groups: [],
    registered: 0,
    active: true,
    bypass: false,
    permissions: [],
  };
}

describe('UserFieldTypeInterpreter', () => {
  const interpreter = new UserFieldTypeInterpreter();

  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('активен только для валидного id', () => {
    expect(interpreter.isActive(field, 2)).toBe(true);
    expect(interpreter.isActive(field, '2')).toBe(true);
    expect(interpreter.isActive(field, null)).toBe(false);
    expect(interpreter.isActive(field, '')).toBe(false);
  });

  it('сравнивает строку по ownerId', () => {
    const predicate = interpreter.predicate(field, 2);

    expect(predicate(2)).toBe(true);
    expect(predicate('2')).toBe(true);
    expect(predicate(3)).toBe(false);
  });

  it('подпись берёт из каталога пользователей', () => {
    useUserStore().rememberUsers([user(2, 'Иван', 'Петров', 'ivan')]);

    expect(interpreter.format(field, 2)).toBe('Владелец: Иван Петров');
    expect(interpreter.format(field, 9)).toBe('Владелец: #9');
  });

  it('приводит значение фильтра к id', () => {
    expect(toUserId(2)).toBe(2);
    expect(toUserId('2')).toBe(2);
    expect(toUserId('  ')).toBeNull();
    expect(toUserId(0)).toBeNull();
  });
});
