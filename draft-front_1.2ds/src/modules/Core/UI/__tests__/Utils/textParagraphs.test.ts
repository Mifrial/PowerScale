import { describe, it, expect } from 'vitest';
import { splitParagraphs } from '@/modules/Core/UI/Utils/textParagraphs';

describe('splitParagraphs', () => {
  it('разбивает по переводам строк и тримит фрагменты', () => {
    expect(splitParagraphs('Первая строка\nВторая строка')).toEqual(['Первая строка', 'Вторая строка']);
    expect(splitParagraphs('  С отступами  \n\n  и пустыми  ')).toEqual(['С отступами', 'и пустыми']);
  });

  it('схлопывает несколько переводов подряд и отбрасывает пустые фрагменты', () => {
    expect(splitParagraphs('a\n\n\nb\n\n')).toEqual(['a', 'b']);
  });

  it('пустой/пробельный текст даёт пустой массив', () => {
    expect(splitParagraphs('')).toEqual([]);
    expect(splitParagraphs('   \n  ')).toEqual([]);
  });
});
