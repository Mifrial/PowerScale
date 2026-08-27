import { describe, expect, it } from 'vitest';
import { fetchRevision } from '@/modules/Roleplay/Space/Mock/mockSpaces';

describe('mockSpaces: мутации ревизий (демо миграции)', () => {
  it('«Ночное зрение» есть на ревизии 6 и удалена на ревизии 12', async () => {
    const rev6 = await fetchRevision(2, 6);
    const rev12 = await fetchRevision(2, 12);
    const nightVision6 = rev6.rules.find((rule) => rule.code === 'night-vision');
    const nightVision12 = rev12.rules.find((rule) => rule.code === 'night-vision');
    expect(nightVision6).toBeDefined();
    expect(nightVision12).toBeUndefined();
  });

  it('ревизия игры (actual, 12) включает правила механик броска (Бросок, 6-и-1, преимущества)', async () => {
    const rev12 = await fetchRevision(2, 12);
    const codes = new Set(rev12.rules.map((rule) => rule.code));
    for (const code of ['roll', 'rule-6-and-1', 'advantages']) {
      expect(codes.has(code), `code ${code}`).toBe(true);
    }
  });
});
