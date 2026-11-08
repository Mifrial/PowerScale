<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import NotesWorkspace from '@/modules/Messages/Notes/Component/NotesWorkspace.vue';

const open = defineModel<boolean>('open', { default: false });

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

const workspaceRef = ref<InstanceType<typeof NotesWorkspace> | null>(null);

const panelOpen = computed({
  get: () => open.value,
  set: (value: boolean) => {
    if (!value && workspaceRef.value?.isDirty() && !window.confirm('Закрыть заметки без сохранения?')) return;
    if (!value) workspaceRef.value?.discard();
    open.value = value;
  },
});
</script>

<template>
  <SlidePanel v-model="panelOpen" width="calc(100vw - 120px)" max-width="1200px" body-flex :close-on-backdrop="false">
    <template #header>
      <div class="notes-slider__header">
        <v-icon size="small">mdi-note-multiple-outline</v-icon>
        <span class="text-subtitle-1 text-truncate">{{ props.title || 'Заметки' }}</span>
      </div>
    </template>
    <NotesWorkspace
      ref="workspaceRef"
      :host-type="hostType"
      :host-id="hostId"
      :inline-context="inlineContext"
      :token-sources="tokenSources"
      class="notes-slider__body"
    />
  </SlidePanel>
</template>

<style scoped>
.notes-slider__header {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding: 10px 12px;
}

.notes-slider__body {
  flex: 1;
  min-width: 0;
  min-height: 0;
}
</style>
