<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { debounce } from '@/modules/Core/UI/Utils/debounce';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import type { NoteFilter } from '@/modules/Messages/Notes/Dto/NoteFilter';
import type { NoteHostGroup } from '@/modules/Messages/Notes/Dto/NoteHostGroup';
import { getNoteApi, getNoteHost, getNoteHosts } from '@/modules/Messages/Notes/init';
import NoteEditor from '@/modules/Messages/Notes/Component/NoteEditor.vue';
import NoteFilterBar from '@/modules/Messages/Notes/Component/NoteFilterBar.vue';
import NoteHostSidebar from '@/modules/Messages/Notes/Component/NoteHostSidebar.vue';
import NoteList from '@/modules/Messages/Notes/Component/NoteList.vue';

const { currentUser } = useCurrentUser();
const filters = ref<NoteFilter>({ state: 'active', limit: 100, offset: 0 });
const hosts = ref<NoteHostGroup[]>([]);
const hostsLoading = ref(false);
const hostsError = ref<string | null>(null);
const selectedHostKey = ref<string | null>(null);
const notes = ref<Note[]>([]);
const notesLoading = ref(false);
const notesError = ref<string | null>(null);
const selectedNoteId = ref<number | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);
const editorContext = ref<Record<string, unknown>>({});
const editorSources = ref<ITokenSource[]>([]);
const contextLoading = ref(false);
const editorRef = ref<{ isDirty?: () => boolean; reset?: () => void } | null>(null);

const bypass = computed(() => currentUser.value?.bypass === true);
const hostTypes = computed(() => getNoteHosts());
const selectedHost = computed(() => hosts.value.find((host) => keyOf(host) === selectedHostKey.value) ?? null);
const selectedNote = computed(() => notes.value.find((note) => note.id === selectedNoteId.value) ?? null);

function keyOf(group: NoteHostGroup): string {
  return `${group.hostType}:${group.hostId}`;
}

async function loadHosts(): Promise<void> {
  hostsLoading.value = true;
  hostsError.value = null;
  try {
    const result = await getNoteApi().findHosts({
      q: filters.value.q,
      hostType: filters.value.hostType,
      authorId: bypass.value ? filters.value.authorId : undefined,
      state: filters.value.state,
      limit: 200,
    });
    hosts.value = result;
    if (!result.some((host) => keyOf(host) === selectedHostKey.value)) {
      selectedHostKey.value = result[0] ? keyOf(result[0]) : null;
      selectedNoteId.value = null;
    }
    await loadNotes();
  } catch (caught) {
    hostsError.value = caught instanceof Error ? caught.message : 'Не удалось загрузить сущности';
  } finally {
    hostsLoading.value = false;
  }
}

async function loadNotes(): Promise<void> {
  const host = selectedHost.value;
  if (!host) {
    notes.value = [];

    return;
  }
  notesLoading.value = true;
  notesError.value = null;
  try {
    const page = await getNoteApi().findPage({
      hostType: host.hostType,
      hostId: host.hostId,
      authorId: bypass.value ? filters.value.authorId : undefined,
      q: filters.value.q,
      state: filters.value.state,
      limit: 100,
      offset: 0,
    });
    notes.value = page.items;
    if (selectedNoteId.value !== null && !page.items.some((note) => note.id === selectedNoteId.value)) {
      selectedNoteId.value = null;
    }
  } catch (caught) {
    notesError.value = caught instanceof Error ? caught.message : 'Не удалось загрузить заметки';
  } finally {
    notesLoading.value = false;
  }
}

const reload = debounce(() => void loadHosts(), 300);

watch(
  filters,
  () => {
    selectedNoteId.value = null;
    selectedHostKey.value = null;
    reload();
  },
  { deep: true },
);

function selectHost(group: NoteHostGroup): void {
  if (keyOf(group) === selectedHostKey.value) return;
  if (editorRef.value?.isDirty?.() && !window.confirm('Отменить несохранённые правки?')) return;
  selectedHostKey.value = keyOf(group);
  selectedNoteId.value = null;
  void loadNotes();
}

async function resolveContext(note: Note): Promise<void> {
  editorContext.value = {};
  editorSources.value = [];
  const host = getNoteHost(note.hostType);
  if (!host?.resolveEditorContext) return;
  contextLoading.value = true;
  try {
    const context = await host.resolveEditorContext(note.hostId);
    if (selectedNoteId.value !== note.id) return;
    editorContext.value = context.inlineContext;
    editorSources.value = context.tokenSources;
  } catch {
    if (selectedNoteId.value === note.id) {
      editorContext.value = {};
      editorSources.value = [];
    }
  } finally {
    contextLoading.value = false;
  }
}

function openNote(note: Note): void {
  if (note.id === selectedNoteId.value) return;
  if (editorRef.value?.isDirty?.() && !window.confirm('Отменить несохранённые правки?')) return;
  selectedNoteId.value = note.id;
  void resolveContext(note);
}

function closeNote(): void {
  if (editorRef.value?.isDirty?.() && !window.confirm('Закрыть заметку без сохранения?')) return;
  editorRef.value?.reset?.();
  selectedNoteId.value = null;
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
    selectedNoteId.value = null;
    await loadNotes();
    await loadHosts();
  } catch (caught) {
    saveError.value = caught instanceof Error ? caught.message : 'Не удалось удалить заметку';
  }
}

async function pin(note: Note, pinned: boolean): Promise<void> {
  try {
    await getNoteApi().pin(note.id, pinned);
    await loadNotes();
  } catch (caught) {
    notesError.value = caught instanceof Error ? caught.message : 'Не удалось закрепить заметку';
  }
}

onMounted(() => void loadHosts());
</script>

<template>
  <div class="notes-page">
    <NoteFilterBar v-model="filters" :hosts="hostTypes" :bypass="bypass" />

    <v-alert v-if="bypass" type="info" variant="tonal" density="compact" class="ma-2">
      Вы читаете чужие заметки: редактирование и удаление доступны только их авторам.
    </v-alert>

    <div v-if="selectedNote" class="notes-page__work">
      <div class="notes-page__note-list">
        <div class="notes-page__back">
          <v-btn icon="mdi-arrow-left" size="small" variant="text" title="К списку сущностей" @click="closeNote" />
          <span class="text-body-2 text-truncate">{{ selectedNote.title }}</span>
        </div>
        <NoteList
          :notes="notes"
          :selected-id="selectedNoteId"
          :loading="notesLoading"
          :error="notesError"
          class="notes-page__list-body"
          @select="openNote"
          @pin="pin"
          @retry="loadNotes"
        />
      </div>
      <div class="notes-page__editor">
        <NoteEditor
          ref="editorRef"
          :note="selectedNote"
          :inline-context="editorContext"
          :token-sources="editorSources"
          :saving="saving || contextLoading"
          :error="saveError"
          @save="save"
          @delete="remove"
          @close="closeNote"
        />
      </div>
    </div>

    <div v-else class="notes-page__work">
      <NoteHostSidebar
        :groups="hosts"
        :selected-key="selectedHostKey"
        :loading="hostsLoading"
        :error="hostsError"
        class="notes-page__hosts"
        @select="selectHost"
        @retry="loadHosts"
      />
      <div class="notes-page__list">
        <div class="notes-page__hint text-caption text-medium-emphasis pa-2">
          Заметки создаются на странице сущности; здесь можно открыть, изменить, закрепить и удалить.
        </div>
        <NoteList
          :notes="notes"
          :selected-id="selectedNoteId"
          :loading="notesLoading"
          :error="notesError"
          class="notes-page__list-body"
          @select="openNote"
          @pin="pin"
          @retry="loadNotes"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.notes-page {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
}

.notes-page__work {
  display: flex;
  min-height: 0;
  flex: 1;
}

.notes-page__hosts {
  flex: 0 0 280px;
}

.notes-page__list,
.notes-page__note-list {
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.notes-page__list {
  flex: 1;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.notes-page__note-list {
  flex: 0 0 260px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.notes-page__list-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.notes-page__editor {
  flex: 1;
  min-width: 0;
  min-height: 0;
}

.notes-page__back {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
