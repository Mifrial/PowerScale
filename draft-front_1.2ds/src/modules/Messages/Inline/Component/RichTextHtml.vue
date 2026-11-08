<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import { ruleLinkExtension } from '@/modules/Core/UI/Service/Description/RuleLinkExtension';
import { inlineEntityExtension } from '@/modules/Messages/Inline/Service/Description/InlineEntityExtension';
import { inlineHtmlContentService } from '@/modules/Messages/Inline/Service/Instance/inlineHtmlContentService';

/** Только чтение rich-text: тот же HTML и те же inline-рендереры, что в редакторе. */
const props = withDefaults(
  defineProps<{
    html: string;
    inlineContext?: Record<string, unknown>;
  }>(),
  {
    inlineContext: () => ({}),
  },
);

const editor = useEditor({
  content: inlineHtmlContentService.sanitize(props.html),
  editable: false,
  extensions: [
    StarterKit,
    ruleLinkExtension.configure({ openOnClick: false }),
    TableKit.configure({ table: { resizable: false } }),
    inlineEntityExtension,
  ],
});

function syncContext(): void {
  const currentEditor = editor.value;
  if (!currentEditor) return;
  const storage = currentEditor.storage as unknown as {
    inlineEntity?: { context: Record<string, unknown> };
  };
  if (storage.inlineEntity) storage.inlineEntity.context = props.inlineContext;
}

watch(
  () => props.html,
  (html) => {
    editor.value?.commands.setContent(inlineHtmlContentService.sanitize(html), { emitUpdate: false });
  },
);

watch(
  () => [editor.value, props.inlineContext] as const,
  () => syncContext(),
  { immediate: true },
);

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
  <div class="rich-text-html">
    <EditorContent :editor="editor" />
  </div>
</template>

<style scoped>
.rich-text-html :deep(.ProseMirror) {
  outline: none;
}

.rich-text-html :deep([data-rule-code]) {
  color: rgba(var(--v-theme-on-surface), 0.72);
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}
</style>
