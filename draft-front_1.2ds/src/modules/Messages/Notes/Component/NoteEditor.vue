<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import { RichTextEditor, RichTextHtml, inlineHtmlContentService } from '@/modules/Messages/Inline/init';

const props = withDefaults(
  defineProps<{
    note: Note | null;
    inlineContext?: Record<string, unknown>;
    tokenSources?: ITokenSource[];
    saving?: boolean;
    error?: string | null;
  }>(),
  {
    inlineContext: () => ({}),
    tokenSources: () => [],
    saving: false,
    error: null,
  },
);

const emit = defineEmits<{
  save: [payload: { title: string; bodyHtml: string }];
  delete: [note: Note];
  close: [];
}>();

const title = ref('');
const bodyHtml = ref('');
const validationError = ref('');

const dirty = computed(() => {
  const note = props.note;

  return note !== null && (title.value !== note.title || bodyHtml.value !== note.bodyHtml);
});

function isDirty(): boolean {
  return dirty.value;
}

const canEdit = computed(() => props.note?.canEdit === true);

function reset(): void {
  title.value = props.note?.title ?? '';
  bodyHtml.value = props.note?.bodyHtml ?? '';
  validationError.value = '';
}

watch(
  () => [props.note?.id, props.note?.updatedAt] as const,
  () => reset(),
  { immediate: true },
);

function save(): void {
  const value = title.value.trim();
  if (!value) {
    validationError.value = 'Заголовок обязателен';

    return;
  }
  validationError.value = '';
  emit('save', { title: value, bodyHtml: inlineHtmlContentService.sanitize(bodyHtml.value) });
}

function onDelete(): void {
  if (!props.note) return;
  if (!window.confirm('Удалить заметку?')) return;
  emit('delete', props.note);
}

defineExpose({ isDirty, reset });
</script>

<template>
  <div class="note-editor">
    <template v-if="note">
      <div class="note-editor__header">
        <v-text-field
          v-if="canEdit"
          v-model="title"
          density="compact"
          hide-details
          label="Заголовок"
          maxlength="255"
          :error-messages="validationError"
          class="note-editor__title"
        />
        <div v-else class="text-subtitle-1 font-weight-medium">{{ note.title }}</div>
        <v-spacer />
        <v-btn
          v-if="canEdit"
          color="primary"
          variant="flat"
          size="small"
          :loading="saving"
          :disabled="!dirty"
          @click="save"
        >
          Сохранить
        </v-btn>
        <v-btn
          v-if="note.canDelete"
          icon="mdi-delete-outline"
          size="small"
          variant="text"
          title="Удалить"
          @click="onDelete"
        />
        <v-btn icon="mdi-close" size="small" variant="text" title="Закрыть" @click="emit('close')" />
      </div>

      <div v-if="error" class="text-error text-body-2 pa-2">{{ error }}</div>
      <div v-if="dirty" class="text-caption text-medium-emphasis px-3">Есть несохранённые правки</div>

      <div class="note-editor__body">
        <RichTextEditor
          v-if="canEdit"
          v-model="bodyHtml"
          :inline-context="inlineContext"
          :token-sources="tokenSources"
          :disabled="saving"
          @save="save"
        />
        <RichTextHtml v-else :html="note.bodyHtml" :inline-context="inlineContext" />
      </div>
    </template>
    <div v-else class="note-editor__empty text-body-2 text-medium-emphasis">Выберите заметку или создайте новую</div>
  </div>
</template>

<style scoped>
.note-editor {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
}

.note-editor__header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.note-editor__title {
  max-width: 420px;
}

.note-editor__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 12px;
}

.note-editor__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: 24px;
  text-align: center;
}
</style>
