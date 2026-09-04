import { describe, expect, it } from 'vitest';
import { initials } from '@/modules/Core/User/Utils/initials';

describe('initials', () => {
  it('имя и фамилия — две буквы', () => {
    expect(initials('Иван', 'Петров')).toBe('ИП');
  });

  it('член группы без фамилии — имя и login той же утилитой', () => {
    expect(initials('Mo', 'mo')).toBe('MM');
  });

  it('пустые части — знак вопроса', () => {
    expect(initials('', '')).toBe('?');
    expect(initials()).toBe('?');
  });
});
