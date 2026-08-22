<script setup lang="ts">
import { computed } from 'vue';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { User } from '@/modules/Core/User/Dto/User';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';
import { DateTime } from '@/modules/Core/Engine/Value/DateTime';
import { avatarColors } from '@/modules/Messages/Chat/Constant/avatarColors';
import { inlineContentService } from '@/modules/Messages/Chat/Service/Instance/inlineContentService';

const props = defineProps<{
  chat: Chat;
  activeChatId: number | null;
}>();

const emit = defineEmits<{
  'select-chat': [id: number];
  'open-profile': [userId: number];
}>();

const auth = useAuthStore();
const chatUsers = useChatUsers();

const otherMember = computed<User | undefined>(() => {
  const other = props.chat.members?.find((m) => m.userId !== auth.userId);
  if (!other) return undefined;

  return chatUsers.getUser(other.userId);
});

const name = computed(() => {
  if (props.chat.type === 'private') {
    return otherMember.value ? chatUsers.displayName(otherMember.value) : props.chat.name;
  }

  return props.chat.name;
});

function chatAvatarColor(userId: number): string {
  return avatarColors[userId % avatarColors.length];
}

const groupColor = computed(() => avatarColors[props.chat.id % avatarColors.length]);

const groupInitials = computed(() => {
  const words = props.chat.name.replace(/^Обсуждение:\s*/i, '').split(/\s+/);
  if (words.length === 1) return words[0][0]?.toUpperCase() || '?';

  return (words[0][0] + words[1][0]).toUpperCase();
});
</script>

<template>
  <div
    class="chat-list-item"
    :class="{ active: props.chat.id === props.activeChatId }"
    @click="emit('select-chat', props.chat.id)"
  >
    <div class="chat-list-avatar">
      <template v-if="props.chat.type === 'private'">
        <v-avatar
          v-if="otherMember"
          :color="chatAvatarColor(otherMember.id)"
          size="36"
          style="cursor: pointer"
          @click.stop="emit('open-profile', otherMember.id)"
        >
          <span class="text-body-2 font-weight-medium text-white">{{ chatUsers.initials(otherMember) }}</span>
        </v-avatar>
      </template>
      <template v-else>
        <v-avatar :color="groupColor" size="36">
          <span class="text-body-2 font-weight-medium text-white">{{ groupInitials }}</span>
        </v-avatar>
      </template>
    </div>
    <div class="chat-list-body">
      <div class="d-flex align-center">
        <span class="chat-list-name text-truncate">{{ name }}</span>
        <span class="chat-list-time ml-auto">{{ DateTime.formatRelative(props.chat.lastMessageAt) }}</span>
      </div>
      <div v-if="props.chat.lastMessage" class="chat-list-preview text-truncate">
        {{ inlineContentService.toText(props.chat.lastMessage) }}
      </div>
    </div>
    <v-badge
      v-if="props.chat.unreadCount"
      inline
      :content="props.chat.unreadCount"
      color="error"
      size="x-small"
      class="ml-1"
    />
  </div>
</template>

<style scoped>
.chat-list-item {
  display: flex;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  transition: background 0.15s ease;
}
.chat-list-item:last-child {
  border-bottom: none;
}
.chat-list-item:hover {
  background: rgb(var(--v-theme-primaryLight));
}
.chat-list-item.active {
  background: rgb(var(--v-theme-primaryLight));
}

.chat-list-avatar {
  display: flex;
  align-items: flex-start;
  padding-top: 2px;
}

.chat-list-body {
  flex: 1;
  min-width: 0;
}

.chat-list-name {
  font-size: 14px;
  font-weight: 500;
}

.chat-list-time {
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  white-space: nowrap;
  flex-shrink: 0;
  margin-left: 8px;
}

.chat-list-preview {
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-medium-opacity));
  margin-top: 2px;
}
</style>
