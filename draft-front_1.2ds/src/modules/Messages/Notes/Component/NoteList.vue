<script setup lang="ts">
import { ref } from 'vue';
import type { Note } from '@/modules/Messages/Notes/Dto/Note';
import type { NoteDropSide } from '@/modules/Messages/Notes/Enum/NoteDropSide';
import { noteOrderService } from '@/modules/Messages/Notes/Service/Instance/noteOrderService';

const props = withDefaults(
  defineProps<{
    notes: Note[];
    selectedId?: number | null;
    loading?: boolean;
    error?: string | null;
    canCreate?: boolean;
    draggable?: boolean;
    emptyText?: string;
  }>(),
  {
    selectedId: null,
    loading: false,
    error: null,
    canCreate: false,
    draggable: false,
    emptyText: 'Заметок пока нет',
  },
);

const emit = defineEmits<{
  select: [note: Note];
  create: [];
  pin: [note: Note, pinned: boolean];
  reorder: [noteIds: number[]];
  retry: [];
}>();

const draggedId = ref<number | null>(null);
const dropTargetId = ref<number | null>(null);
const dropSide = ref<NoteDropSide | null>(null);

function dateOf(unix: number): string {
  return new Date(unix * 1000).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

function resetDrag(): void {
  draggedId.value = null;
  dropTargetId.value = null;
  dropSide.value = null;
}

function onDragStart(event: DragEvent, note: Note): void {
  if (!props.draggable || note.pinned) {
    event.preventDefault();

    return;
  }
  draggedId.value = note.id;
  dropTargetId.value = null;
  dropSide.value = null;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(note.id));
    event.dataTransfer.setDragImage(dragGhost(note.title), 12, 16);
  }
}

/**
 * Компактный ghost строки: браузер снимает его вместо всей панели.
 */
function dragGhost(title: string): HTMLElement {
  const ghost = document.createElement('div');
  ghost.className = 'note-list__ghost';
  ghost.textContent = title;
  ghost.style.cssText = `
    position: fixed;
    top: -1000px;
    left: -1000px;
    max-width: 240px;
    overflow: hidden;
    padding: 6px 10px;
    border: 1px solid rgb(var(--v-theme-primary));
    border-radius: 6px;
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 4px 12px rgba(var(--v-theme-scrim), var(--v-shadow-md-opacity));
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-size: 0.875rem;
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
    pointer-events: none;
  `;
  document.body.appendChild(ghost);
  requestAnimationFrame(() => ghost.remove());

  return ghost;
}

function onDragOver(event: DragEvent, note: Note): void {
  if (!props.draggable || draggedId.value === null) return;
  event.preventDefault();
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
  const row = event.currentTarget as HTMLElement;
  const rect = row.getBoundingClientRect();
  dropTargetId.value = note.id;
  dropSide.value = event.clientY - rect.top < rect.height / 2 ? 'before' : 'after';
}

function onDragLeave(event: DragEvent, note: Note): void {
  const next = event.relatedTarget;
  if (next instanceof Node && (event.currentTarget as HTMLElement).contains(next)) return;
  if (dropTargetId.value === note.id) {
    dropTargetId.value = null;
    dropSide.value = null;
  }
}

function onDrop(event: DragEvent, target: Note): void {
  event.preventDefault();
  const dragged = draggedId.value;
  const side = dropSide.value ?? 'before';
  resetDrag();
  if (!props.draggable || dragged === null) return;
  const noteIds = noteOrderService.move(props.notes, dragged, target.id, side);
  if (noteIds === null) return;
  emit('reorder', noteIds);
}
</script>

<template>
  <div class="note-list">
    <div class="note-list__header">
      <span class="text-subtitle-2">Заметки</span>
      <v-spacer />
      <v-btn
        v-if="canCreate"
        icon="mdi-plus"
        size="small"
        variant="text"
        title="Новая заметка"
        @click="emit('create')"
      />
    </div>

    <div v-if="loading" class="d-flex justify-center pa-4">
      <v-progress-circular indeterminate width="2" size="24" color="primary" />
    </div>
    <div v-else-if="error" class="pa-3">
      <div class="text-error text-body-2 mb-2">{{ error }}</div>
      <v-btn variant="tonal" color="primary" size="small" @click="emit('retry')">Попробовать снова</v-btn>
    </div>
    <v-list v-else-if="notes.length" density="compact" class="py-0">
      <v-list-item
        v-for="note in notes"
        :key="note.id"
        :class="{
          'note-list__item--selected': note.id === selectedId,
          'note-list__item--drop-before': note.id === dropTargetId && dropSide === 'before',
          'note-list__item--drop-after': note.id === dropTargetId && dropSide === 'after',
        }"
        class="note-list__item"
        @click="emit('select', note)"
        @dragover="onDragOver($event, note)"
        @dragleave="onDragLeave($event, note)"
        @drop="onDrop($event, note)"
      >
        <template #prepend>
          <v-icon v-if="note.pinned" size="small" color="primary" title="Закреплена: всегда сверху">mdi-pin</v-icon>
          <span
            v-else-if="draggable"
            class="note-list__grip text-medium-emphasis"
            draggable="true"
            title="Перетащить"
            @dragstart="onDragStart($event, note)"
            @dragend="resetDrag"
          >
            <v-icon size="small">mdi-drag-vertical</v-icon>
          </span>
        </template>
        <v-list-item-title class="text-body-2 text-truncate">{{ note.title }}</v-list-item-title>
        <v-list-item-subtitle class="text-caption">
          {{ dateOf(note.updatedAt) }} · {{ note.bodyText || 'Пустая заметка' }}
        </v-list-item-subtitle>
        <template #append>
          <v-btn
            v-if="note.canEdit"
            :icon="note.pinned ? 'mdi-pin-off' : 'mdi-pin-outline'"
            size="x-small"
            variant="text"
            :title="note.pinned ? 'Открепить' : 'Закрепить'"
            @click.stop="emit('pin', note, !note.pinned)"
          />
        </template>
      </v-list-item>
    </v-list>
    <div v-else class="text-body-2 text-medium-emphasis pa-4 text-center">{{ emptyText }}</div>
  </div>
</template>

<style scoped>
.note-list {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
}

.note-list__header {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.note-list__item {
  cursor: pointer;
  user-select: none;
}

.note-list__item--selected {
  background: rgba(var(--v-theme-primary), 0.12);
}

.note-list__grip {
  display: inline-flex;
  align-items: center;
  cursor: grab;
}

.note-list__grip:active {
  cursor: grabbing;
}

.note-list__item--drop-before {
  box-shadow: inset 0 2px 0 rgb(var(--v-theme-primary));
}

.note-list__item--drop-after {
  box-shadow: inset 0 -2px 0 rgb(var(--v-theme-primary));
}
</style>
