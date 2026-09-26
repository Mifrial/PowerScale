import { Node, mergeAttributes } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';
import DescriptionExpandedBlockView from '@/modules/Core/UI/Component/DescriptionExpandedBlockView.vue';

/** Разворачиваемый блок описания: шапка с названием и сложностью, тело из абзацев. */
export const descriptionExpandedBlockExtension = Node.create({
  name: 'descriptionExpandedBlock',
  group: 'block',
  content: 'block+',
  defining: true,

  addAttributes() {
    return {
      title: {
        default: 'Вопрос',
        parseHTML: () => undefined,
        renderHTML: () => ({}),
      },
      difficulty: {
        default: 'Базовая Сложность',
        parseHTML: () => undefined,
        renderHTML: () => ({}),
      },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'details.description-expanded-block',
        getAttrs: (element) => {
          if (!(element instanceof HTMLElement)) return false;

          return {
            title: element.querySelector('.description-expanded-block__title')?.textContent?.trim() || 'Вопрос',
            difficulty:
              element.querySelector('.description-expanded-block__difficulty')?.textContent?.trim() ||
              'Базовая Сложность',
          };
        },
        contentElement: (element) => element.querySelector('.description-expanded-block__body') ?? element,
      },
    ];
  },

  renderHTML({ node }) {
    return [
      'details',
      mergeAttributes({ class: 'description-expanded-block' }),
      [
        'summary',
        { class: 'description-expanded-block__header' },
        ['span', { class: 'description-expanded-block__title' }, node.attrs.title as string],
        ['span', { class: 'description-expanded-block__difficulty' }, node.attrs.difficulty as string],
      ],
      ['div', { class: 'description-expanded-block__body' }, 0],
    ];
  },

  addNodeView() {
    return VueNodeViewRenderer(DescriptionExpandedBlockView);
  },
});
