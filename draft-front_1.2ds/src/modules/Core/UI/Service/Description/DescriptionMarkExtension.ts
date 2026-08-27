import { Mark } from '@tiptap/core';

export const descriptionMarkExtension = Mark.create({
  name: 'descriptionMark',
  excludes: '',
  addAttributes() {
    return {
      variant: {
        default: 'example',
        parseHTML: (element) => element.getAttribute('data-description-mark') ?? 'example',
        renderHTML: (attributes) => ({ 'data-description-mark': attributes.variant }),
      },
    };
  },
  parseHTML() {
    return [
      { tag: 'span.description-example', getAttrs: () => ({ variant: 'example' }) },
      { tag: 'span.description-flavor', getAttrs: () => ({ variant: 'flavor' }) },
    ];
  },
  renderHTML({ mark, HTMLAttributes }) {
    const className = mark.attrs.variant === 'flavor' ? 'description-flavor' : 'description-example';

    return ['span', { ...HTMLAttributes, class: className }, 0];
  },
});
