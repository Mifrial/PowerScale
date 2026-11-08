<script setup lang="ts">
import { useRouter } from 'vue-router';
import type { NoteHostGroup } from '@/modules/Messages/Notes/Dto/NoteHostGroup';
import { getNoteHost } from '@/modules/Messages/Notes/init';

defineProps<{
  groups: NoteHostGroup[];
  selectedKey?: string | null;
  loading?: boolean;
  error?: string | null;
}>();

const emit = defineEmits<{
  select: [group: NoteHostGroup];
  retry: [];
}>();

const router = useRouter();

function keyOf(group: NoteHostGroup): string {
  return `${group.hostType}:${group.hostId}`;
}

function labelOf(group: NoteHostGroup): string {
  return getNoteHost(group.hostType)?.label ?? group.hostType;
}

function iconOf(group: NoteHostGroup): string {
  return getNoteHost(group.hostType)?.icon ?? 'mdi-note-text-outline';
}

function dateOf(unix: number): string {
  return new Date(unix * 1000).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

function openHost(group: NoteHostGroup): void {
  const route = getNoteHost(group.hostType)?.route?.(group.hostId);
  if (route) void router.push(route);
}
</script>

<template>
  <div class="note-host-sidebar">
    <div class="note-host-sidebar__header text-subtitle-2">Сущности</div>
    <div v-if="loading" class="d-flex justify-center pa-4">
      <v-progress-circular indeterminate width="2" size="24" color="primary" />
    </div>
    <div v-else-if="error" class="pa-3">
      <div class="text-error text-body-2 mb-2">{{ error }}</div>
      <v-btn variant="tonal" color="primary" size="small" @click="emit('retry')">Попробовать снова</v-btn>
    </div>
    <v-list v-else-if="groups.length" density="compact" class="py-0">
      <v-list-item
        v-for="group in groups"
        :key="keyOf(group)"
        :class="{ 'note-host-sidebar__item--selected': keyOf(group) === selectedKey }"
        class="note-host-sidebar__item"
        @click="emit('select', group)"
      >
        <template #prepend>
          <v-icon size="small">{{ iconOf(group) }}</v-icon>
        </template>
        <v-list-item-title class="text-body-2 text-truncate">
          {{ labelOf(group) }} #{{ group.hostId }}
        </v-list-item-title>
        <v-list-item-subtitle class="text-caption">
          {{ group.count }} заметок · {{ dateOf(group.lastUpdatedAt) }}
        </v-list-item-subtitle>
        <template #append>
          <v-btn
            v-if="getNoteHost(group.hostType)?.route"
            icon="mdi-open-in-new"
            size="x-small"
            variant="text"
            title="Открыть сущность"
            @click.stop="openHost(group)"
          />
        </template>
      </v-list-item>
    </v-list>
    <div v-else class="text-body-2 text-medium-emphasis pa-4 text-center">Здесь появятся сущности с заметками</div>
  </div>
</template>

<style scoped>
.note-host-sidebar {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.note-host-sidebar__header {
  padding: 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.note-host-sidebar__item {
  cursor: pointer;
}

.note-host-sidebar__item--selected {
  background: rgba(var(--v-theme-primary), 0.12);
}
</style>
