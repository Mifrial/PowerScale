<script setup lang="ts">
import { NodeViewContent, NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3';

const props = defineProps(nodeViewProps);

function updateTitle(event: Event): void {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) return;
  props.updateAttributes({ title: target.value });
}

function updateDifficulty(event: Event): void {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) return;
  props.updateAttributes({ difficulty: target.value });
}
</script>

<template>
  <node-view-wrapper class="description-expanded-block" as="div">
    <div class="description-expanded-block__header" contenteditable="false">
      <input class="description-expanded-block__title" :value="node.attrs.title" @input="updateTitle" />
      <input class="description-expanded-block__difficulty" :value="node.attrs.difficulty" @input="updateDifficulty" />
    </div>
    <node-view-content class="description-expanded-block__body" />
  </node-view-wrapper>
</template>

<style scoped>
.description-expanded-block {
  margin: 8px 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

.description-expanded-block__header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 12px;
}

.description-expanded-block__title,
.description-expanded-block__difficulty {
  flex: 1 1 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
}

.description-expanded-block__difficulty {
  text-align: right;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.description-expanded-block__body {
  padding: 0 12px 8px;
}
</style>
