import { describe, it, expect } from 'vitest';
import { fetchRevision, commitDraft } from '@/modules/Roleplay/Space/Mock/mockSpaces';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

describe('mockSpaces: публикация черновика собирает ревизию из каталога', () => {
  it('новая ревизия = все правила предыдущей + закоммиченное правило', async () => {
    const before = await fetchRevision(2, 12);
    const draftRule: Rule = {
      id: 'draft-1',
      code: 'lavash',
      type: 'item',
      name: 'Лаваш',
      description: 'Лавалава',
      spaceId: 2,
      createdAt: new Date().toISOString(),
    };

    const after = await commitDraft(2, [draftRule]);

    expect(after.revision).toBe(13);
    const afterCodes = new Set(after.rules.map((r) => r.code));

    for (const rule of before.rules) {
      expect(afterCodes.has(rule.code), `правило ${rule.code} потеряно`).toBe(true);
    }
    const lavash = after.rules.find((r) => r.code === 'lavash');
    expect(lavash).toBeDefined();
    expect(lavash?.id).toMatch(/^rule-\d+$/);
    expect(lavash?.id).not.toBe('draft-1');
  });

  it('правило из черновика сохраняет id при повторном коммите (перезапись по code)', async () => {
    const first = await commitDraft(2, [
      {
        id: 'draft-2',
        code: 'lavash-2',
        type: 'item',
        name: 'Лаваш 2',
        description: '',
        spaceId: 2,
        createdAt: new Date().toISOString(),
      },
    ]);
    const firstLavash = first.rules.find((r) => r.code === 'lavash-2');
    const firstId = firstLavash?.id;

    const second = await commitDraft(2, [
      {
        id: firstId ?? 'draft-2',
        code: 'lavash-2',
        type: 'item',
        name: 'Лаваш 2 (обновлён)',
        description: 'новое описание',
        spaceId: 2,
        createdAt: new Date().toISOString(),
      },
    ]);
    const secondLavash = second.rules.find((r) => r.code === 'lavash-2');
    expect(secondLavash?.id).toBe(firstId);
    expect(secondLavash?.name).toBe('Лаваш 2 (обновлён)');
    expect(secondLavash?.description).toBe('новое описание');
  });
});
