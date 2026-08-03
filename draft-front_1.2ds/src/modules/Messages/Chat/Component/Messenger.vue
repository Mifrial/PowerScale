<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { computed } from 'vue'
import { useChatStore } from '@/modules/Messages/Chat/Store/chat'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers'
import { usePermissions } from '@/modules/Messages/Chat/Composables/usePermissions'
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec'
import { DateTime } from '@/modules/Core/Engine/Value/DateTime'
import { getContentRenderer } from '@/modules/Messages/Chat/init'
import ChatList from '@/modules/Messages/Chat/Component/ChatList.vue'
import ChatInput from '@/modules/Messages/Chat/Component/ChatInput.vue'
import UserProfileSlider from '@/modules/Messages/Chat/Component/UserProfileSlider.vue'

const store = useChatStore()
const auth = useAuthStore()
const permissions = usePermissions()
const chatUsers = useChatUsers()

const rollRenderer = getContentRenderer('roll')

const canWrite = computed(() => permissions.canInChat(store.activeChat, 'chat.write'))

const messagesRef = ref<HTMLElement | null>(null)
const userSliderOpen = ref(false)
const userSliderUserId = ref<number | null>(null)

function openUserProfile(userId: number) {
  userSliderUserId.value = userId
  userSliderOpen.value = true
}

function getUser(userId: number) {
  return chatUsers.getUser(userId)
}

let scrollPending = false

function onMessagesScroll() {
  if (!messagesRef.value || scrollPending) return
  scrollPending = true
  requestAnimationFrame(() => {
    scrollPending = false
    const el = messagesRef.value
    if (!el) return
    store.setAutoScroll(el.scrollTop + el.clientHeight >= el.scrollHeight - 40)

    if (el.scrollTop <= 80 && store.hasMoreOlder && !store.loadingOlder) {
      const prevHeight = el.scrollHeight
      store.loadOlderMessages().then(() => {
        nextTick(() => {
          if (messagesRef.value) {
            messagesRef.value.scrollTop = messagesRef.value.scrollHeight - prevHeight
          }
        })
      })
    }
  })
}

async function handleSend(text: string, rolls: DiceRollSpec[]) {
  await store.sendMessage(text, rolls)
  await nextTick()
  if (messagesRef.value) {
    messagesRef.value.scrollTop = messagesRef.value.scrollHeight
  }
}

watch(() => store.allMessages.length, async () => {
  if (!messagesRef.value) return
  await nextTick()
  if (store.autoScroll) {
    messagesRef.value.scrollTop = messagesRef.value.scrollHeight
  }
})

watch(() => store.activeChatId, async (chatId) => {
  store.setAutoScroll(true)
  if (chatId != null) {
    const chat = store.chats.find(c => c.id === chatId)
    const memberIds = chat?.members?.map(m => m.userId) || []
    if (memberIds.length) {
      await chatUsers.ensureUsers(memberIds)
    }
  }
})

onMounted(async () => {
  if (store.chats.length === 0) {
    await store.fetchChats()
  }
  store.startSync()
  // preload all users for member resolution
  const allMemberIds = new Set(store.chats.flatMap(c => c.members?.map(m => m.userId) || []))
  if (allMemberIds.size) {
    await chatUsers.ensureUsers([...allMemberIds])
  }
})

onUnmounted(() => {
  store.activeChatId = null
  store.stopSync()
})
</script>

<template>
  <div class="messenger">
    <slot name="tabs" />

    <div class="chat-body">
      <ChatList
        :chats="store.currentTabChats"
        :active-chat-id="store.activeChatId"
        @select-chat="store.openChat"
        @open-profile="openUserProfile"
      />

      <div class="chat-main">
        <template v-if="store.activeChat">
          <div class="chat-header border-b">
            <span class="font-weight-medium text-body-1">{{ store.activeChat.name }}</span>
          </div>

          <div ref="messagesRef" class="chat-messages" @scroll="onMessagesScroll">
            <div v-if="store.loadingOlder" class="d-flex justify-center pa-3">
              <v-progress-circular indeterminate width="2" size="20" color="primary" />
            </div>
            <div v-if="!store.hasMoreOlder && store.allMessages.length > 0" class="text-center text-caption text-medium-emphasis pa-2">
              Начало переписки
            </div>
            <template v-for="msg in store.renderedMessages" :key="msg.id">
              <div v-if="msg.id === store.firstUnreadMessageId" class="chat-unread-divider">
                <v-divider />
                <span class="text-caption text-medium-emphasis mx-2">Новые сообщения</span>
                <v-divider />
              </div>
              <div class="chat-message" :class="{ own: msg.userId === auth.userId }">
                <div class="chat-msg-header">
                  <v-avatar
                    v-if="getUser(msg.userId)"
                    :color="msg.userId === auth.userId ? 'primary' : 'secondary'"
                    size="28"
                    class="chat-msg-avatar"
                    style="cursor: pointer;"
                    @click="openUserProfile(msg.userId)"
                  >
                    <span class="text-caption font-weight-medium text-white">{{ chatUsers.initials(getUser(msg.userId)) }}</span>
                  </v-avatar>
                  <span
                    class="font-weight-medium text-caption chat-msg-author"
                    style="cursor: pointer;"
                    @click="openUserProfile(msg.userId)"
                  >{{ chatUsers.displayName(getUser(msg.userId)) || msg.username }}</span>
                  <span class="text-caption text-disabled">{{ DateTime.formatTime(msg.createdAt) }}</span>
                </div>
                <div v-if="msg.content" class="chat-msg-text">{{ msg.content }}</div>
                <div v-if="msg.rolls.length && rollRenderer" class="chat-rolls">
                  <component :is="rollRenderer" v-for="(roll, ri) in msg.rolls" :key="ri" :roll="roll" :index="ri" />
                </div>
              </div>
            </template>
          </div>

          <ChatInput :sending="store.sending" :disabled="!canWrite" @send="handleSend" />
          <div v-if="!canWrite && store.activeChat?.visibility === 'public'" class="text-caption text-medium-emphasis text-center pa-1 border-t">
            Чат доступен только для чтения
          </div>
        </template>
        <div v-else class="chat-main-empty">
          <span class="text-medium-emphasis text-body-2">Выберите чат</span>
        </div>
      </div>
    </div>

    <UserProfileSlider v-model:open="userSliderOpen" :user-id="userSliderUserId" />
  </div>
</template>

<style scoped>
.messenger {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.chat-body {
  display: flex;
  flex: 1;
  min-height: 0;
}

.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
}

.chat-main-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.chat-header {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  flex-shrink: 0;
}

.chat-messages {
  flex: 1;
  min-height: 0;
  height: 1px;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 12px 16px;
}

.chat-message {
  margin-bottom: 12px;
}

.chat-unread-divider {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.chat-unread-divider :deep(.v-divider) {
  flex: 1;
}
.chat-message.own {
  text-align: right;
}
.chat-message.own .chat-msg-text {
  background: rgb(var(--v-theme-primaryLight));
  border-radius: 12px 12px 4px 12px;
  display: inline-block;
  text-align: left;
}

.chat-msg-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}
.chat-message.own .chat-msg-header {
  flex-direction: row-reverse;
}

.chat-msg-avatar {
  flex-shrink: 0;
}
.chat-msg-author {
  flex-shrink: 0;
}

.chat-msg-text {
  padding: 8px 12px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 12px 12px 12px 4px;
  display: inline-block;
  max-width: 80%;
  white-space: pre-wrap;
}

.chat-rolls {
  margin-top: 6px;
}

.text-disabled {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
}
</style>
