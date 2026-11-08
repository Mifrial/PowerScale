import { describe, expect, it } from 'vitest';
import { generateHTML, generateJSON } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { inlineEntityExtension } from '@/modules/Messages/Inline/Service/Description/InlineEntityExtension';

const extensions = [StarterKit, inlineEntityExtension];

describe('inlineEntityExtension', () => {
  it('рендерит inline-сущность в span с data-атрибутами и подписью', () => {
    const html = generateHTML(
      {
        type: 'doc',
        content: [
          {
            type: 'paragraph',
            content: [
              { type: 'text', text: 'См. ' },
              { type: 'inlineEntity', attrs: { type: 'character', value: '3', label: 'Гаррик' } },
            ],
          },
        ],
      },
      extensions,
    );

    expect(html).toContain('data-inline-type="character"');
    expect(html).toContain('data-inline-value="3"');
    expect(html).toContain('data-inline-label="Гаррик"');
    expect(html).toContain('>Гаррик</span>');
  });

  it('разбирает span[data-inline-type] обратно в узел', () => {
    const json = generateJSON(
      '<p>См. <span data-inline-type="character" data-inline-value="3" data-inline-label="Гаррик">Гаррик</span></p>',
      extensions,
    );

    expect(json.content?.[0]?.content?.[1]).toMatchObject({
      type: 'inlineEntity',
      attrs: { type: 'character', value: '3', label: 'Гаррик' },
    });
  });
});
