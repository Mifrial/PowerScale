import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mockLoreApi } from '@/modules/Roleplay/Lore/Mock/mockLoreApi';
import { LoreInlineTokenSourceService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineTokenSourceService';
import { LoreInlineValueService } from '@/modules/Roleplay/Lore/Service/Inline/LoreInlineValueService';

const valueService = new LoreInlineValueService();

function buildSource() {
  return new LoreInlineTokenSourceService(() => mockLoreApi, valueService).build();
}

describe('LoreInlineTokenSourceService', () => {
  beforeEach(() => vi.restoreAllMocks());

  it('lists active worlds as scopes', async () => {
    const source = buildSource();
    const scopes = await source.loadScopes?.();

    expect(scopes).toEqual([
      { value: 'austerland', label: 'Аустерленд' },
      { value: 'diary', label: 'Личный дневник' },
    ]);
    expect(source.scopeLabel).toBe('Мир');
  });

  it('searches published articles of the chosen world', async () => {
    const source = buildSource();
    const options = await source.search('old-capital', 'austerland');

    expect(options).toEqual([{ value: 'austerland/old-capital', label: 'Старая столица' }]);
  });

  it('does not search without a scope', async () => {
    const source = buildSource();

    await expect(source.search('')).resolves.toEqual([]);
  });

  it('does not offer drafts', async () => {
    const source = buildSource();
    const options = await source.search('', 'austerland');

    expect(options.map((option) => option.value)).not.toContain('austerland/draft-idea');
    expect(options).toHaveLength(3);
  });
});
