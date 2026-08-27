import Link from '@tiptap/extension-link';

export const ruleLinkExtension = Link.extend({
  name: 'ruleLink',
  parseHTML() {
    return [{ tag: 'a[data-rule-code]' }];
  },
  addAttributes() {
    const linkAttributes = { ...this.parent?.() };
    delete linkAttributes.href;
    delete linkAttributes.target;
    delete linkAttributes.rel;

    return {
      ...linkAttributes,
      ruleCode: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-rule-code'),
        renderHTML: (attributes) => (attributes.ruleCode ? { 'data-rule-code': attributes.ruleCode } : {}),
      },
    };
  },
});
