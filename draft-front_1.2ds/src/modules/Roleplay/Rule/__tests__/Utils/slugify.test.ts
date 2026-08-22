import { describe, it, expect } from 'vitest';
import { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify';

describe('slugify: транслитерация в латинский код правила', () => {
  it('кириллица транслитерируется в латиницу', () => {
    expect(slugify('Лаваш')).toBe('lavash');
    expect(slugify('Лавалава')).toBe('lavalava');
  });

  it('кириллица с пробелами и спецсимволами', () => {
    expect(slugify('Поймать момент!')).toBe('poymat-moment');
    expect(slugify('Штурмовик')).toBe('shturmovik');
    expect(slugify('Щит и меч')).toBe('shchit-i-mech');
  });

  it('латиница сохраняется без изменений', () => {
    expect(slugify('Double Strike')).toBe('double-strike');
    expect(slugify('rule-6-and-1')).toBe('rule-6-and-1');
  });

  it('допускает дефис, подчёркивание и цифры 0-9', () => {
    expect(slugify('Вторая_фаза 3')).toBe('vtoraya_faza-3');
  });

  it('пустой результат заменяется на "rule"', () => {
    expect(slugify('!!!')).toBe('rule');
  });
});
