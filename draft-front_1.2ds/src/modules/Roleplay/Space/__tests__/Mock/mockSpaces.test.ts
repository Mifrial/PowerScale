import { describe, it, expect } from 'vitest';
import {
  fetchRevision,
  fetchRevisions,
  commitDraft,
  generateRevisionRules,
} from '@/modules/Roleplay/Space/Mock/mockSpaces';
import { DT_PAY_SR_VS_RELIABILITY_CODE } from '@/modules/Roleplay/Rule/init';
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

  it('срез ревизии держит типы урона и хук РУ vs надёжность', () => {
    const codes = new Set(generateRevisionRules(2, 12).map((rule) => rule.code));
    expect(codes.has('piercing')).toBe(true);
    expect(codes.has(DT_PAY_SR_VS_RELIABILITY_CODE)).toBe(true);
  });

  it('удаление — маркер новой ревизии, старый срез из кеша не меняется', async () => {
    const before = await fetchRevision(2, 12);
    const gone = before.rules.find((rule) => rule.type === 'simple') ?? before.rules[0];
    expect(gone).toBeDefined();
    const after = await commitDraft(2, [], undefined, [gone.code]);
    expect(after.revision).toBeGreaterThan(before.revision);
    expect(after.rules.some((rule) => rule.code === gone.code)).toBe(false);
    const oldAgain = await fetchRevision(2, before.revision);
    expect(oldAgain.rules.some((rule) => rule.code === gone.code)).toBe(true);
  });

  it('createSpace без снимка — ревизия 0 и пустой срез', async () => {
    const { createSpace } = await import('@/modules/Roleplay/Space/Mock/mockSpaces');
    const space = await createSpace({
      name: 'Из файла',
      description: '',
    });
    expect(space.revision).toBe(0);
    const slice = await fetchRevision(space.id, 0);
    expect(slice.rules).toEqual([]);
    expect(await fetchRevisions(space.id)).toEqual([]);
  });

  it('первая публикация на пустом пространстве даёт v1 из черновика, не каталог', async () => {
    const { createSpace } = await import('@/modules/Roleplay/Space/Mock/mockSpaces');
    const space = await createSpace({ name: 'Пустое', description: '' });
    const published = await commitDraft(space.id, [
      {
        id: 'imported-from-file',
        code: 'from-file',
        type: 'simple',
        name: 'Из файла',
        description: '',
        spaceId: space.id,
        createdAt: '2026-01-01T00:00:00Z',
      },
    ]);
    expect(published.revision).toBe(1);
    expect(published.rules.map((rule) => rule.code)).toEqual(['from-file']);
    const meta = await fetchRevisions(space.id);
    expect(meta.map((item) => item.revision)).toEqual([1]);
  });
});
