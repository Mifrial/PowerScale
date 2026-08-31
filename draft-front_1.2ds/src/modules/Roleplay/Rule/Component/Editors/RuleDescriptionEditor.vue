<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { descriptionMarkExtension } from '@/modules/Core/UI/Service/Description/DescriptionMarkExtension';
import { ruleLinkExtension } from '@/modules/Core/UI/Service/Description/RuleLinkExtension';

const props = defineProps<{
  modelValue: string;
  rules: Rule[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const ruleSearch = ref('');
const filteredRules = computed(() => {
  const query = ruleSearch.value.trim().toLocaleLowerCase();
  if (!query) return [];

  return props.rules.filter((rule) => `${rule.name} ${rule.code}`.toLocaleLowerCase().includes(query)).slice(0, 20);
});

function escapeHtml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function initialContent(value: string): string {
  if (/<[a-z][\s\S]*>/i.test(value)) return value;

  return value
    .split('\n')
    .map((paragraph) => `<p>${escapeHtml(paragraph)}</p>`)
    .join('');
}

const editor = useEditor({
  content: initialContent(props.modelValue),
  extensions: [
    StarterKit,
    ruleLinkExtension.configure({ openOnClick: false }),
    descriptionMarkExtension,
    TableKit.configure({ table: { resizable: true } }),
  ],
  editorProps: {
    handleDOMEvents: {
      click: (_view, event) => {
        const target = event.target;
        if (target instanceof Element && target.closest('[data-rule-code]')) {
          event.preventDefault();
        }

        return false;
      },
    },
  },
  onUpdate: ({ editor: currentEditor }) => emit('update:modelValue', currentEditor.getHTML()),
});

function toggleExample(): void {
  editor.value?.chain().focus().toggleMark('descriptionMark', { variant: 'example' }).run();
}

function toggleFlavor(): void {
  editor.value?.chain().focus().toggleMark('descriptionMark', { variant: 'flavor' }).run();
}

function insertRule(rule: Rule): void {
  editor.value
    ?.chain()
    .focus()
    .insertContent(`<a data-rule-code="${rule.code}">${escapeHtml(rule.name)}</a>`)
    .run();
  ruleSearch.value = '';
}

function insertTable(): void {
  editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
}

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
  <div class="description-editor">
    <div class="description-editor__toolbar">
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
      <v-divider vertical />
      <v-menu :close-on-content-click="false">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" size="small" prepend-icon="mdi-link-variant" variant="text">Правило</v-btn>
        </template>
        <v-card min-width="320" @click.stop>
          <v-text-field
            v-model="ruleSearch"
            autofocus
            density="compact"
            hide-details
            label="Поиск правила"
            placeholder="Название или код"
            class="ma-2"
          />
          <v-list v-if="filteredRules.length" density="compact" max-height="320" class="overflow-y-auto">
            <v-list-item v-for="rule in filteredRules" :key="rule.code" :title="rule.name" @click="insertRule(rule)" />
          </v-list>
          <div v-else class="text-caption text-medium-emphasis px-4 pb-3">
            {{ ruleSearch ? 'Правила не найдены' : 'Введите название или код' }}
          </div>
        </v-card>
      </v-menu>
      <v-btn size="small" prepend-icon="mdi-format-quote-close" variant="text" @click="toggleExample">Пример</v-btn>
      <v-btn size="small" prepend-icon="mdi-format-quote-open" variant="text" @click="toggleFlavor">Flavor</v-btn>
      <v-btn size="small" prepend-icon="mdi-table" variant="text" @click="insertTable">Таблица</v-btn>
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
    <EditorContent :editor="editor" class="description-editor__content" />
  </div>
</template>

<style scoped>
.description-editor {
  margin-top: 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

.description-editor__toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-wrap: wrap;
  padding: 4px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.description-editor__content {
  min-height: 180px;
  padding: 12px;
}

.description-editor__content :deep(.ProseMirror) {
  min-height: 156px;
  outline: none;
}

.description-editor__content :deep(.description-example) {
  color: rgba(var(--v-theme-on-surface), 0.58);
}

.description-editor__content :deep(.description-flavor) {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-style: italic;
}

.description-editor__content :deep([data-rule-code]) {
  color: rgba(var(--v-theme-on-surface), 0.72);
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}

.description-editor__content :deep(table) {
  border-collapse: collapse;
  width: 100%;
}

.description-editor__content :deep(th),
.description-editor__content :deep(td) {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  min-width: 80px;
  padding: 4px 8px;
}
</style>
