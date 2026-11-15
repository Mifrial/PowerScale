import { describe, expect, it } from 'vitest';
import { isLoreCode, loreSlug } from '@/modules/Roleplay/Lore/Utils/loreSlug';

describe('loreSlug', () => {
  it('транслитерирует русское название', () => {
    expect(loreSlug('Падение империи')).toBe('padenie-imperii');
  });

  it('убирает лишние разделители и приводит к нижнему регистру', () => {
    expect(loreSlug('  Старая   Столица! ')).toBe('staraya-stolitsa');
  });

  it('проверяет формат кода', () => {
    expect(isLoreCode('old-capital_2')).toBe(true);
    expect(isLoreCode('Old Capital')).toBe(false);
    expect(isLoreCode('-bad')).toBe(false);
  });
});
