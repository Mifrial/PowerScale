<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { debounce } from '@/modules/Core/UI/Utils/debounce';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import { getNoteApi } from '@/modules/Messages/Notes/init';
import NoteCreateDialog from '@/modules/Messages/Notes/Component/NoteCreateDialog.vue';
import NoteEditor from '@/modules/Messages/Notes/Component/NoteEditor.vue';
import NoteList from '@/modules/Messages/Notes/Component/NoteList.vue';

const props = withDefaults(
  defineProps<{
    hostType: string;
    hostId: number;
    title?: string;
    inlineContext?: Record<string, unknown>;
    tokenSources?: ITokenSource[];
  }>(),
  {
    title: '',
    inlineContext: () => ({}),
    tokenSources: () => [],
  },
);

const { currentUser } = useCurrentUser();
const notes = ref<Note[]>([]);
const selectedId = ref<number | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);
const search = ref('');
const createOpen = ref(false);
const editorRef = ref<{ isDirty?: () => boolean; reset?: () => void } | null>(null);

const selectedNote = computed(() => notes.value.find((note) => note.id === selectedId.value) ?? null);

async function load(): Promise<void> {
  const authorId = currentUser.value?.id;
  if (authorId === undefined) {
    notes.value = [];

    return;
  }
  loading.value = true;
  error.value = null;
  try {
    const page = await getNoteApi().findPage({
      hostType: props.hostType,
      hostId: props.hostId,
      authorId,
      state: 'active',
      q: search.value.trim() || undefined,
      limit: 100,
      offset: 0,
    });
    notes.value = page.items;
    if (selectedId.value !== null && !page.items.some((note) => note.id === selectedId.value)) {
      selectedId.value = null;
    }
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить заметки';
  } finally {
    loading.value = false;
  }
}

const reload = debounce(() => void load(), 300);

function select(note: Note): void {
  if (note.id === selectedId.value) return;
  if (editorRef.value?.isDirty?.() && !window.confirm('Отменить несохранённые правки?')) return;
  selectedId.value = note.id;
}

async function create(title: string): Promise<void> {
  try {
    const note = await getNoteApi().create({
      hostType: props.hostType,
      hostId: props.hostId,
      title,
      bodyHtml: '',
    });
    await load();
    selectedId.value = note.id;
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось создать заметку';
  }
}

async function save(payload: { title: string; bodyHtml: string }): Promise<void> {
  const note = selectedNote.value;
  if (!note) return;
  saving.value = true;
  saveError.value = null;
  try {
    const updated = await getNoteApi().update(note.id, payload);
    notes.value = notes.value.map((item) => (item.id === updated.id ? updated : item));
  } catch (caught) {
    saveError.value = caught instanceof Error ? caught.message : 'Не удалось сохранить заметку';
  } finally {
    saving.value = false;
  }
}

async function remove(note: Note): Promise<void> {
  try {
    await getNoteApi().delete(note.id);
    selectedId.value = null;
    await load();
  } catch (caught) {
    saveError.value = caught instanceof Error ? caught.message : 'Не удалось удалить заметку';
  }
}

async function pin(note: Note, pinned: boolean): Promise<void> {
  try {
    await getNoteApi().pin(note.id, pinned);
    await load();
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось закрепить заметку';
  }
}

async function reorder(noteIds: number[]): Promise<void> {
  const previous = notes.value;
  const byId = new Map(notes.value.map((note) => [note.id, note]));
  notes.value = noteIds
    .map((id, sortOrder) => {
      const note = byId.get(id);

      return note ? { ...note, sortOrder } : null;
    })
    .filter((note): note is Note => note !== null);
  try {
    await getNoteApi().reorder(props.hostType, props.hostId, noteIds);
  } catch (caught) {
    notes.value = previous;
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить порядок';
  }
}

watch(
  () => [props.hostType, props.hostId] as const,
  () => {
    selectedId.value = null;
    void load();
  },
);

watch(search, () => reload());

onMounted(() => void load());

function isDirty(): boolean {
  return editorRef.value?.isDirty?.() === true;
}

function discard(): void {
  editorRef.value?.reset?.();
}

defineExpose({ isDirty, discard });
</script>

<template>
  <div class="notes-workspace">
    <div class="notes-workspace__list">
      <div class="pa-2">
        <v-text-field
          v-model="search"
          density="compact"
          hide-details
          label="Поиск"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          clearable
        />
      </div>
      <NoteList
        :notes="notes"
        :selected-id="selectedId"
        :loading="loading"
        :error="error"
        can-create
        draggable
        class="notes-workspace__note-list"
        @select="select"
        @create="createOpen = true"
        @pin="pin"
        @reorder="reorder"
        @retry="load"
      />
    </div>
    <div class="notes-workspace__editor">
      <NoteEditor
        ref="editorRef"
        :note="selectedNote"
        :inline-context="inlineContext"
        :token-sources="tokenSources"
        :saving="saving"
        :error="saveError"
        @save="save"
        @delete="remove"
        @close="selectedId = null"
      />
    </div>

    <NoteCreateDialog v-model="createOpen" @create="create" />
  </div>
</template>

<style scoped>
.notes-workspace {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  min-height: 0;
  height: 100%;
}

.notes-workspace__list {
  display: flex;
  flex-direction: column;
  min-height: 0;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.notes-workspace__note-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.notes-workspace__editor {
  min-height: 0;
  overflow: hidden;
}
</style>
