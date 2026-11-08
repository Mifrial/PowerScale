import { Node } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';
import InlineEntityView from '@/modules/Messages/Inline/Component/InlineEntityView.vue';

/** Inline-сущность заметки: чип с типом, значением и подписью в HTML. */
export const inlineEntityExtension = Node.create({
  name: 'inlineEntity',
  group: 'inline',
  inline: true,
  atom: true,
  selectable: true,

  addAttributes() {
    return {
      type: {
        default: '',
        parseHTML: (element) => element.getAttribute('data-inline-type') ?? '',
        renderHTML: (attributes) => ({ 'data-inline-type': attributes.type }),
      },
      value: {
        default: '',
        parseHTML: (element) => element.getAttribute('data-inline-value') ?? '',
        renderHTML: (attributes) => ({ 'data-inline-value': attributes.value }),
      },
      label: {
        default: '',
        parseHTML: (element) => element.getAttribute('data-inline-label') ?? element.textContent ?? '',
        renderHTML: (attributes) => ({ 'data-inline-label': attributes.label }),
      },
    };
  },

  parseHTML() {
    return [{ tag: 'span[data-inline-type]' }];
  },

  renderHTML({ node }) {
    const label = String(node.attrs.label || node.attrs.value || '');

    return [
      'span',
      {
        'data-inline-type': String(node.attrs.type ?? ''),
        'data-inline-value': String(node.attrs.value ?? ''),
        'data-inline-label': label,
      },
      label,
    ];
  },

  addStorage() {
    return {
      context: {} as Record<string, unknown>,
    };
  },

  addNodeView() {
    return VueNodeViewRenderer(InlineEntityView);
  },
});
