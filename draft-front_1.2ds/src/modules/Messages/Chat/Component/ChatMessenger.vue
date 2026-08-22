<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { computed } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';
import { usePermissions } from '@/modules/Messages/Chat/Composables/usePermissions';
import { resolveChatRules } from '@/modules/Messages/Chat/init';
import { useChatVisibilityOptions } from '@/modules/Messages/Chat/Composables/useChatVisibilityOptions';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import ChatList from '@/modules/Messages/Chat/Component/ChatList.vue';
import ChatMessageList from '@/modules/Messages/Chat/Component/ChatMessageList.vue';
import ChatInput from '@/modules/Messages/Chat/Component/ChatInput.vue';
import UserProfileSlider from '@/modules/Core/User/Component/UserProfileSlider.vue';

const store = useChatStore();
const permissions = usePermissions();
const chatUsers = useChatUsers();

// Контекст правил активного чата (чипы/ссылки/броски): резолвится провайдерами доменов
// (игра/персонаж — их ревизия, обычные чаты — «актуальные правила»). Host-агностично.
const rulesContext = ref<ChatRulesContext | null>(null);

const canWrite = computed(() => permissions.canInChat(store.activeChat, 'chat.write'));

// Опции видимости сообщений выводятся из данных активного чата (роли типа + участники).
const { allowVisibility, roleOptions, userOptions } = useChatVisibilityOptions(computed(() => store.activeChat));

const userSliderOpen = ref(false);
const userSliderUserId = ref<number | null>(null);

function openUserProfile(userId: number) {
  userSliderUserId.value = userId;
  userSliderOpen.value = true;
}

function retryOpenChat() {
  if (store.activeChat) store.openChat(store.activeChat.id);
}

async function handleSend(
  text: string,
  attachments: ChatAttachment[],
  _speaker?: ChatSpeaker,
  visibility?: ChatMessageVisibility,
): Promise<boolean> {
  return store.sendMessage(text, attachments, undefined, _speaker, visibility);
}

watch(
  () => store.activeChatId,
  async (chatId) => {
    store.setAutoScroll(true);
    if (chatId != null) {
      const chat = store.chats.find((c) => c.id === chatId);
      const memberIds = chat?.members?.map((m) => m.userId) || [];
      if (memberIds.length) {
        await chatUsers.ensureUsers(memberIds);
      }
      try {
        rulesContext.value = chat ? await resolveChatRules(chat.type, chat.id) : null;
      } catch {
        rulesContext.value = null;
      }
    } else {
      rulesContext.value = null;
    }
  },
);

onMounted(async () => {
  if (store.chats.length === 0) {
    await store.fetchChats();
  }
  store.startSync();
  // preload all users for member resolution
  const allMemberIds = new Set(store.chats.flatMap((c) => c.members?.map((m) => m.userId) || []));
  if (allMemberIds.size) {
    await chatUsers.ensureUsers([...allMemberIds]);
  }
});

onUnmounted(() => {
  store.activeChatId = null;
  store.stopSync();
});
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
        <div v-if="store.chatsError" class="chat-error pa-4">
          <div class="text-error text-body-2 mb-2">{{ store.chatsError }}</div>
          <v-btn variant="tonal" color="primary" size="small" @click="store.fetchChats()"> Попробовать снова </v-btn>
        </div>

        <template v-else-if="store.activeChat">
          <div class="chat-header border-b">
            <span class="font-weight-medium text-body-1">{{ store.activeChat.name }}</span>
          </div>

          <div v-if="store.chatError" class="chat-error pa-4">
            <div class="text-error text-body-2 mb-2">{{ store.chatError }}</div>
            <v-btn variant="tonal" color="primary" size="small" @click="retryOpenChat"> Попробовать снова </v-btn>
          </div>
          <ChatMessageList
            v-else
            :renderer-context="
              rulesContext
                ? {
                    ruleNames: rulesContext.ruleNames,
                    spaceId: rulesContext.spaceId,
                    rulesRevision: rulesContext.rulesRevision,
                  }
                : undefined
            "
            @open-profile="openUserProfile"
          />

          <ChatInput
            :sending="store.sending"
            :disabled="!canWrite"
            :action-error="store.actionError"
            :token-sources="rulesContext?.tokenSources"
            :process-attachments="rulesContext?.processAttachments"
            :allow-visibility="allowVisibility"
            :visibility-role-options="roleOptions"
            :visibility-options="userOptions"
            :send="handleSend"
          />
          <div
            v-if="!canWrite && store.activeChat?.visibility === 'public'"
            class="text-caption text-medium-emphasis text-center pa-1 border-t"
          >
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

.chat-error {
  flex-shrink: 0;
}
</style>
