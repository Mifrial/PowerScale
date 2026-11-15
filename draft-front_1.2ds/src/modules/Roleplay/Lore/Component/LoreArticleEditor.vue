<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { loreLinkExtension } from '@/modules/Roleplay/Lore/Service/Description/LoreLinkExtension';
import { ruleLinkExtension } from '@/modules/Core/UI/Service/Description/RuleLinkExtension';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    articles: LoreArticleSummary[];
    rules?: Rule[];
    disabled?: boolean;
  }>(),
  {
    rules: () => [],
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
  'create-article': [selectionText: string];
}>();

const loreSearch = ref('');
const ruleSearch = ref('');

const filteredArticles = computed(() => filterLore(props.articles, loreSearch.value));
const filteredRules = computed(() => filterRules(props.rules, ruleSearch.value));

const editor = useEditor({
  content: props.modelValue,
  editable: !props.disabled,
  extensions: [
    StarterKit,
    ruleLinkExtension.configure({ openOnClick: false }),
    loreLinkExtension.configure({ openOnClick: false }),
    TableKit.configure({ table: { resizable: true } }),
  ],
  onUpdate: ({ editor: currentEditor }) => emit('update:modelValue', currentEditor.getHTML()),
});

function filterLore(articles: LoreArticleSummary[], query: string): LoreArticleSummary[] {
  const needle = query.trim().toLocaleLowerCase();
  const sorted = [...articles].sort((left, right) => left.title.localeCompare(right.title));
  if (!needle) return sorted.slice(0, 20);

  return sorted
    .filter((article) => `${article.title} ${article.code}`.toLocaleLowerCase().includes(needle))
    .slice(0, 20);
}

function filterRules(rules: Rule[], query: string): Rule[] {
  const needle = query.trim().toLocaleLowerCase();
  const sorted = [...rules].sort((left, right) => left.name.localeCompare(right.name));
  if (!needle) return sorted.slice(0, 20);

  return sorted.filter((rule) => `${rule.name} ${rule.code}`.toLocaleLowerCase().includes(needle)).slice(0, 20);
}

function escapeHtml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function insertLoreLink(code: string, title: string): void {
  const label = escapeHtml(title.trim() || code);
  editor.value
    ?.chain()
    .focus()
    .insertContent(`<a data-lore-code="${escapeHtml(code)}">${label}</a> `)
    .run();
}

function insertRuleLink(rule: Rule): void {
  editor.value
    ?.chain()
    .focus()
    .insertContent(`<a data-rule-code="${escapeHtml(rule.code)}">${escapeHtml(rule.name)}</a> `)
    .run();
}

function createFromSelection(): void {
  const currentEditor = editor.value;
  if (!currentEditor) return;
  const { from, to } = currentEditor.state.selection;
  const selectionText = currentEditor.state.doc.textBetween(from, to, ' ').trim();
  emit('create-article', selectionText);
}

function insertTable(): void {
  editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
}

function selectedText(): string {
  const currentEditor = editor.value;
  if (!currentEditor) return '';
  const { from, to } = currentEditor.state.selection;

  return currentEditor.state.doc.textBetween(from, to, ' ').trim();
}

defineExpose({ insertLoreLink, selectedText });

watch(
  () => props.modelValue,
  (value) => {
    const currentEditor = editor.value;
    if (!currentEditor) return;
    if (value === currentEditor.getHTML()) return;
    currentEditor.commands.setContent(value, { emitUpdate: false });
  },
);

watch(
  () => props.disabled,
  (disabled) => editor.value?.setEditable(!disabled),
);

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
  <div class="lore-editor">
    <div class="lore-editor__toolbar">
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
        icon="mdi-format-header-2"
        variant="text"
        title="Заголовок 2"
        @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
      />
      <v-btn
        size="small"
        icon="mdi-format-header-3"
        variant="text"
        title="Заголовок 3"
        @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
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
          <v-btn v-bind="menuProps" size="small" prepend-icon="mdi-book-open-page-variant" variant="text">
            Статья
          </v-btn>
        </template>
        <v-card min-width="340" @click.stop>
          <v-text-field
            v-model="loreSearch"
            autofocus
            density="compact"
            hide-details
            label="Поиск статьи"
            class="ma-2"
          />
          <v-list v-if="filteredArticles.length" density="compact" max-height="320" class="overflow-y-auto">
            <v-list-item
              v-for="article in filteredArticles"
              :key="article.code"
              :title="article.title"
              :subtitle="article.code"
              @click="insertLoreLink(article.code, article.title)"
            />
          </v-list>
          <div v-else class="text-caption text-medium-emphasis pa-3">Статьи не найдены</div>
          <v-divider />
          <v-list-item prepend-icon="mdi-plus" title="Создать статью из выделения" @click="createFromSelection" />
        </v-card>
      </v-menu>
      <v-menu v-if="props.rules.length > 0" :close-on-content-click="false">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" size="small" prepend-icon="mdi-link-variant" variant="text">Правило</v-btn>
        </template>
        <v-card min-width="340" @click.stop>
          <v-text-field
            v-model="ruleSearch"
            autofocus
            density="compact"
            hide-details
            label="Поиск правила"
            class="ma-2"
          />
          <v-list v-if="filteredRules.length" density="compact" max-height="320" class="overflow-y-auto">
            <v-list-item
              v-for="rule in filteredRules"
              :key="rule.code"
              :title="rule.name"
              :subtitle="rule.code"
              @click="insertRuleLink(rule)"
            />
          </v-list>
          <div v-else class="text-caption text-medium-emphasis pa-3">Правила не найдены</div>
        </v-card>
      </v-menu>
      <v-menu v-else :close-on-content-click="true">
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" size="small" prepend-icon="mdi-link-variant" variant="text" disabled>
            Правило
          </v-btn>
        </template>
        <v-card max-width="320">
          <v-card-text class="text-body-2">
            Чтобы вставлять ссылки на правила, укажите пространство правил в настройках мира.
          </v-card-text>
        </v-card>
      </v-menu>
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
      <v-btn
        size="small"
        icon="mdi-format-clear"
        variant="text"
        title="Очистить формат"
        @click="editor?.chain().focus().unsetAllMarks().run()"
      />
    </div>
    <EditorContent :editor="editor" class="lore-editor__content" />
  </div>
</template>

<style scoped>
.lore-editor {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

.lore-editor__toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-wrap: wrap;
  padding: 4px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.lore-editor__content {
  min-height: 280px;
  padding: 12px;
}

.lore-editor__content :deep(.ProseMirror) {
  min-height: 256px;
  outline: none;
}

.lore-editor__content :deep([data-lore-code]),
.lore-editor__content :deep([data-rule-code]) {
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}

.lore-editor__content :deep(table) {
  border-collapse: collapse;
}

.lore-editor__content :deep(td),
.lore-editor__content :deep(th) {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 4px 8px;
}
</style>
