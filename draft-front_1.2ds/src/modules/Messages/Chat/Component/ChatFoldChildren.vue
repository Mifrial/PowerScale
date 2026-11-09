<script setup lang="ts">
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';
import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';
import type { User } from '@/modules/Core/User/Dto/User';
import ChatMessageRow from '@/modules/Messages/Chat/Component/ChatMessageRow.vue';
import ChatFoldChrome from '@/modules/Messages/Chat/Component/ChatFoldChrome.vue';
import ChatFoldPanel from '@/modules/Messages/Chat/Component/ChatFoldPanel.vue';

defineOptions({ name: 'ChatFoldChildren' });

const props = defineProps<{
  nodes: ChatFoldChild[];
  isExpanded: (id: string) => boolean;
  getUser: (userId: number) => User | undefined;
  visibilityLabelOf: (message: ChatMessage) => string | undefined;
  canChangeVisibilityOf: (message: ChatMessage) => boolean;
  roleOptions: { code: string; label: string }[];
  userOptions: { userId: number; name: string }[];
  rendererContext?: ChatInlineRendererContext | null;
  openEntity?: (ref: string) => void;
  firstUnreadId?: number | null;
}>();

const emit = defineEmits<{
  toggle: [id: string];
  'open-profile': [userId: number];
  'update-visibility': [messageId: number, visibility?: ChatMessageVisibility];
}>();

function nodeKey(node: ChatFoldChild): string {
  return node.type === 'message' ? `m:${node.message.id}` : `f:${node.fold.id}`;
}

function forwardVisibility(messageId: number, visibility?: ChatMessageVisibility): void {
  emit('update-visibility', messageId, visibility);
}
</script>

<template>
  <template v-for="node in props.nodes" :key="nodeKey(node)">
    <template v-if="node.type === 'message'">
      <div v-if="props.firstUnreadId === node.message.id" class="chat-unread-divider">
        <v-divider />
        <span class="text-caption text-medium-emphasis mx-2">Новые сообщения</span>
        <v-divider />
      </div>
      <ChatMessageRow
        :msg="node.message"
        :user="props.getUser(node.message.userId)"
        :visibility-label="props.visibilityLabelOf(node.message)"
        :can-change-visibility="props.canChangeVisibilityOf(node.message)"
        :visibility-role-options="props.roleOptions"
        :visibility-options="props.userOptions"
        :renderer-context="props.rendererContext"
        :open-entity="props.openEntity"
        @open-profile="emit('open-profile', $event)"
        @update-visibility="forwardVisibility"
      />
    </template>
    <ChatFoldPanel
      v-else-if="node.fold.chrome === 'end'"
      :summary="node.fold.summary"
      :expanded="props.isExpanded(node.fold.id)"
      :renderer-context="props.rendererContext"
      :open-entity="props.openEntity"
      @toggle="emit('toggle', node.fold.id)"
    >
      <ChatFoldChildren
        :nodes="node.fold.children"
        :is-expanded="props.isExpanded"
        :get-user="props.getUser"
        :visibility-label-of="props.visibilityLabelOf"
        :can-change-visibility-of="props.canChangeVisibilityOf"
        :role-options="props.roleOptions"
        :user-options="props.userOptions"
        :renderer-context="props.rendererContext"
        :open-entity="props.openEntity"
        :first-unread-id="props.firstUnreadId"
        @toggle="emit('toggle', $event)"
        @open-profile="emit('open-profile', $event)"
        @update-visibility="forwardVisibility"
      />
    </ChatFoldPanel>
    <template v-else>
      <ChatFoldChrome
        :summary="node.fold.summary"
        :expanded="props.isExpanded(node.fold.id)"
        :tone="node.fold.tone"
        :variant="node.fold.variant"
        :renderer-context="props.rendererContext"
        :open-entity="props.openEntity"
        @toggle="emit('toggle', node.fold.id)"
      />
      <ChatFoldChildren
        v-if="props.isExpanded(node.fold.id)"
        :nodes="node.fold.children"
        :is-expanded="props.isExpanded"
        :get-user="props.getUser"
        :visibility-label-of="props.visibilityLabelOf"
        :can-change-visibility-of="props.canChangeVisibilityOf"
        :role-options="props.roleOptions"
        :user-options="props.userOptions"
        :renderer-context="props.rendererContext"
        :open-entity="props.openEntity"
        :first-unread-id="props.firstUnreadId"
        @toggle="emit('toggle', $event)"
        @open-profile="emit('open-profile', $event)"
        @update-visibility="forwardVisibility"
      />
    </template>
  </template>
</template>

<style scoped>
.chat-unread-divider {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 12px 16px;
}
.chat-unread-divider :deep(.v-divider) {
  flex: 1;
}
</style>
