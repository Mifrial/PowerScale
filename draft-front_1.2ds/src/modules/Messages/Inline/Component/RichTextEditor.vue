<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import { ruleLinkExtension } from '@/modules/Core/UI/Service/Description/RuleLinkExtension';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import { inlineEntityExtension } from '@/modules/Messages/Inline/Service/Description/InlineEntityExtension';
import { inlineHtmlContentService } from '@/modules/Messages/Inline/Service/Instance/inlineHtmlContentService';
import InlineNodePicker from '@/modules/Messages/Inline/Component/InlineNodePicker.vue';

/** Rich-text редактор заметки: TipTap, таблицы и inline-сущности из реестра. */
const props = withDefaults(
  defineProps<{
    modelValue: string;
    tokenSources?: ITokenSource[];
    inlineContext?: Record<string, unknown>;
    disabled?: boolean;
  }>(),
  {
    tokenSources: () => [],
    inlineContext: () => ({}),
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
  save: [];
}>();

const editor = useEditor({
  content: inlineHtmlContentService.normalize(props.modelValue),
  editable: !props.disabled,
  extensions: [
    StarterKit,
    ruleLinkExtension.configure({ openOnClick: false }),
    TableKit.configure({ table: { resizable: true } }),
    inlineEntityExtension,
  ],
  editorProps: {
    handleKeyDown: (_view, event) => {
      if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        emit('save');

        return true;
      }

      return false;
    },
  },
  onUpdate: ({ editor: currentEditor }) => emit('update:modelValue', currentEditor.getHTML()),
});

function syncContext(): void {
  const currentEditor = editor.value;
  if (!currentEditor) return;
  const storage = currentEditor.storage as unknown as {
    inlineEntity?: { context: Record<string, unknown> };
  };
  if (storage.inlineEntity) storage.inlineEntity.context = props.inlineContext;
}

function insertEntity(entity: { type: string; value: string; label: string }): void {
  editor.value?.chain().focus().insertContent({ type: 'inlineEntity', attrs: entity }).run();
}

function insertTable(): void {
  editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
}

watch(
  () => props.modelValue,
  (value) => {
    const currentEditor = editor.value;
    if (!currentEditor) return;
    if (value === currentEditor.getHTML()) return;
    currentEditor.commands.setContent(inlineHtmlContentService.normalize(value), { emitUpdate: false });
  },
);

watch(
  () => props.disabled,
  (disabled) => editor.value?.setEditable(!disabled),
);

watch(
  () => [editor.value, props.inlineContext] as const,
  () => syncContext(),
  { immediate: true },
);

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
  <div class="rich-text-editor">
    <div class="rich-text-editor__toolbar">
      <v-btn
        size="small"
        icon="mdi-format-bold"
        variant="text"
        title="Жирный"
        @click="editor?.chain().focus().toggleBold().run()"
      />
      <v-btn
        size="small"
        icon="mdi-format-italic"
        variant="text"
        title="Курсив"
        @click="editor?.chain().focus().toggleItalic().run()"
      />
      <v-btn
        size="small"
        icon="mdi-format-strikethrough"
        variant="text"
        title="Зачёркнутый"
        @click="editor?.chain().focus().toggleStrike().run()"
      />
      <v-btn
        size="small"
        icon="mdi-code-tags"
        variant="text"
        title="Код"
        @click="editor?.chain().focus().toggleCode().run()"
      />
      <v-btn
        size="small"
        icon="mdi-format-header-2"
        variant="text"
        title="Заголовок"
        @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
      />
      <v-btn
        size="small"
        icon="mdi-format-list-bulleted"
        variant="text"
        title="Список"
        @click="editor?.chain().focus().toggleBulletList().run()"
      />
      <v-btn
        size="small"
        icon="mdi-format-list-numbered"
        variant="text"
        title="Нумерованный список"
        @click="editor?.chain().focus().toggleOrderedList().run()"
      />
      <v-btn
        size="small"
        icon="mdi-format-quote-close"
        variant="text"
        title="Цитата"
        @click="editor?.chain().focus().toggleBlockquote().run()"
      />
      <v-btn size="small" prepend-icon="mdi-table" variant="text" @click="insertTable">Таблица</v-btn>
      <InlineNodePicker :sources="tokenSources" :disabled="disabled" @insert="insertEntity" />
      <v-spacer />
      <v-btn
        size="small"
        icon="mdi-undo"
        variant="text"
        title="Отменить"
        @click="editor?.chain().focus().undo().run()"
      />
      <v-btn
        size="small"
        icon="mdi-redo"
        variant="text"
        title="Повторить"
        @click="editor?.chain().focus().redo().run()"
      />
    </div>
    <EditorContent :editor="editor" class="rich-text-editor__content" />
  </div>
</template>

<style scoped>
.rich-text-editor {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

.rich-text-editor__toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-wrap: wrap;
  padding: 4px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.rich-text-editor__content {
  min-height: 180px;
  padding: 12px;
}

.rich-text-editor__content :deep(.ProseMirror) {
  min-height: 156px;
  outline: none;
}

.rich-text-editor__content :deep([data-rule-code]) {
  color: rgba(var(--v-theme-on-surface), 0.72);
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}
</style>
