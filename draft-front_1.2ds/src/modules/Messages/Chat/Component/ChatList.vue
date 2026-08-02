<template>
  <div class="chat-list">
    <div
      v-for="c in chats"
      :key="c.id"
      class="chat-list-item"
      :class="{ active: c.id === activeChatId }"
      @click="$emit('select-chat', c.id)"
    >
      <div class="chat-list-avatar">
        <template v-if="c.type === 'private'">
          <v-avatar
            v-if="otherMember(c)"
            :color="chatAvatarColor(otherMember(c)!.id)"
            size="36"
            style="cursor: pointer;"
            @click.stop="openProfile(otherMember(c)!.id)"
          >
            <span class="text-body-2 font-weight-medium text-white">{{ chatUsers.initials(otherMember(c)!) }}</span>
          </v-avatar>
        </template>
        <template v-else>
          <v-avatar :color="groupColor(c)" size="36">
            <span class="text-body-2 font-weight-medium text-white">{{ groupInitials(c) }}</span>
          </v-avatar>
        </template>
      </div>
      <div class="chat-list-body">
        <div class="d-flex align-center">
          <span class="chat-list-name text-truncate">{{ chatName(c) }}</span>
          <span class="chat-list-time ml-auto">{{ DateTime.formatRelative(c.lastMessageAt) }}</span>
        </div>
        <div v-if="c.lastMessage" class="chat-list-preview text-truncate">{{ c.lastMessage }}</div>
      </div>
      <v-badge v-if="c.unreadCount" inline :content="c.unreadCount" color="error" size="x-small" class="ml-1" />
    </div>
    <div v-if="!chats.length" class="pa-3 text-center text-medium-emphasis text-caption">
      Нет чатов
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat'
import type { User } from '@/modules/Core/User/Dto/User'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers'
import { DateTime } from '@/modules/Core/Engine/Value/DateTime'

defineProps<{
  chats: Chat[]
  activeChatId: number | null
}>()

const emit = defineEmits<{
  'select-chat': [id: number]
  'open-profile': [userId: number]
}>()

const auth = useAuthStore()
const chatUsers = useChatUsers()

const avatarColors = ['primary', 'secondary', 'success', 'warning', 'info', 'error', 'accent', 'indigo', 'orange', 'teal']

function chatAvatarColor(userId: number): string {
  return avatarColors[userId % avatarColors.length]
}

function groupColor(chat: Chat): string {
  return avatarColors[chat.id % avatarColors.length]
}

function groupInitials(chat: Chat): string {
  const words = chat.name.replace(/^Обсуждение:\s*/i, '').split(/\s+/)
  if (words.length === 1) return words[0][0]?.toUpperCase() || '?'
  return (words[0][0] + words[1][0]).toUpperCase()
}

function otherMember(chat: Chat): User | undefined {
  const other = chat.members?.find(m => m.userId !== auth.userId)
  if (!other) return
  return chatUsers.getUser(other.userId)
}

function chatName(chat: Chat): string {
  if (chat.type === 'private') {
    const other = otherMember(chat)
    return other ? chatUsers.displayName(other) : chat.name
  }
  return chat.name
}

function openProfile(userId: number) {
  emit('open-profile', userId)
}
</script>

<style scoped>
.chat-list {
  width: 280px;
  flex-shrink: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  border-right: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}

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
