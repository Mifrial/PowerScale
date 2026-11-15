import Link from '@tiptap/extension-link';

/** Inline-ссылка на статью кодекса: `a[data-lore-code]`. */
export const loreLinkExtension = Link.extend({
  name: 'loreLink',
  parseHTML() {
    return [{ tag: 'a[data-lore-code]' }];
  },
  addAttributes() {
    const linkAttributes = { ...this.parent?.() };
    delete linkAttributes.href;
    delete linkAttributes.target;
    delete linkAttributes.rel;

    return {
      ...linkAttributes,
      loreCode: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-lore-code'),
        renderHTML: (attributes) => (attributes.loreCode ? { 'data-lore-code': attributes.loreCode } : {}),
      },
    };
  },
});
