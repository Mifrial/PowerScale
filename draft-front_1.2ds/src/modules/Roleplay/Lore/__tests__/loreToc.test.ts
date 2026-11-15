import { describe, expect, it } from 'vitest';
import { loreTocService } from '@/modules/Roleplay/Lore/Service/Instance/loreTocService';

describe('loreTocService', () => {
  it('собирает заголовки и назначает уникальные id', () => {
    const root = document.createElement('div');
    root.innerHTML = '<h2>Причины</h2><p>Текст</p><h3>Итог</h3><h2>Причины</h2>';

    const toc = loreTocService.build(root);

    expect(toc).toHaveLength(3);
    expect(toc[0]).toEqual({ id: 'причины', level: 2, title: 'Причины' });
    expect(toc[2]?.id).toBe('причины-2');
    expect(root.querySelector('h2')?.id).toBe('причины');
  });

  it('пустой корень даёт пустое оглавление', () => {
    expect(loreTocService.build(null)).toEqual([]);
  });
});
