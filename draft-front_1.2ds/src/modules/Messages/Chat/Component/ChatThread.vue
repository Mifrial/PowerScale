<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { usePermissions } from '@/modules/Messages/Chat/Composables/usePermissions';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatSpeakerOption } from '@/modules/Messages/Chat/Dto/ChatSpeakerOption';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';
import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';
import { useChatVisibilityOptions } from '@/modules/Messages/Chat/Composables/useChatVisibilityOptions';
import ChatMessageList from '@/modules/Messages/Chat/Component/ChatMessageList.vue';
import ChatInput from '@/modules/Messages/Chat/Component/ChatInput.vue';
import ChatSyncStatusBanner from '@/modules/Messages/Chat/Component/ChatSyncStatusBanner.vue';
import ChatReadAckBanner from '@/modules/Messages/Chat/Component/ChatReadAckBanner.vue';

/**
 * Встраиваемый чат по id (обсуждение персонажа/игры): читает/шлёт сообщения по своему chatId,
 * изолированно от глобального активного чата мессенджера (loadChat/sendMessage(..., chatId),
 * не трогая activeChatId/selectedTab). Паттерн D7.
 */
const props = withDefaults(
  defineProps<{
    chatId: number | null;
    emptyLabel?: string;
    /** «От лица кого» писать (игровой чат): опции для селектора в ChatInput. */
    speakers?: ChatSpeakerOption[];
    defaultSpeaker?: string | null;
    /** Активный источник речи (авто-переключение селектора, напр. на персонажа текущего хода инициативы). */
    activeSpeakerKey?: string | null;
    /** Действие вместо иконки у селектора «от лица кого» (игровой чат — открыть карточку). */
    speakerAction?: { icon: string; title: string; disabled?: boolean; onClick: () => void } | null;
    /** Data-срез для inline-рендереров (подписи токенов и ключи среза). */
    rendererContext?: ChatInlineRendererContext | null;
    /** Клик по сущности в ленте (персонаж/НПС) — колбэк донора, не в data-срезе. */
    openEntity?: (ref: string) => void;
    /** Источники токенов «Вставить ссылку» (игровой чат резолвит правила из ревизии). Пусто — глобальные. */
    tokenSources?: ITokenSource[];
    /** Трансформация вложений перед отправкой (дефолты броска из ревизии игры). */
    processAttachments?: (attachments: ChatAttachment[]) => ChatAttachment[];
    /** Штамп свёртки на исходящие сообщения (игровой чат — текущий ход). */
    messageThread?: ChatThreadRef | null;
    buildFolds?: (messages: ChatMessage[]) => ChatFoldChild[];
    liveFoldIds?: string[];
  }>(),
  {
    emptyLabel: 'Чат доступен в мессенджере',
  },
);

const store = useChatStore();
const permissions = usePermissions();
const chatUsers = useChatUsers();

const emit = defineEmits<{
  'update:active-speaker-key': [key: string | null];
}>();

const chat = computed(() => store.chats.find((c) => c.id === props.chatId) ?? null);
const canWrite = computed(() => permissions.canInChat(chat.value, 'chat.write'));
const loading = ref(false);

// Опции видимости сообщений выводятся из данных чата (роли типа + участники).
const { allowVisibility, roleOptions, userOptions } = useChatVisibilityOptions(chat);

let attachToken = 0;

async function attachChat(chatId: number): Promise<void> {
  const token = ++attachToken;
  loading.value = true;
  try {
    if (store.chats.length === 0) {
      await store.fetchChats();
    }
    if (token !== attachToken) return;

    const loadedChat = store.chats.find((c) => c.id === chatId);
    const memberIds = loadedChat?.members?.map((m) => m.userId) ?? [];
    if (memberIds.length) {
      await chatUsers.ensureUsers(memberIds);
    }
    if (token !== attachToken) return;

    await store.loadChat(chatId);
  } finally {
    if (token === attachToken) loading.value = false;
  }
}

watch(
  () => props.chatId,
  (chatId) => {
    if (chatId !== null) {
      store.startSync();
      void attachChat(chatId);
    }
  },
  { immediate: true },
);

onUnmounted(() => {
  attachToken++;
  store.stopSync();
});

async function handleSend(
  text: string,
  attachments: ChatAttachment[],
  speaker?: ChatSpeaker,
  visibility?: ChatMessageVisibility,
): Promise<boolean> {
  if (props.chatId === null) return false;

  return store.sendMessage(text, attachments, props.chatId, speaker, visibility, props.messageThread ?? undefined);
}

function retryAttach(): void {
  if (props.chatId !== null) void attachChat(props.chatId);
}
</script>

<template>
  <!-- Один корень: async-обёртка из Chat init иначе глотает class/height (фрагмент). -->
  <div class="chat-thread-root">
    <v-card v-if="chatId === null" class="chat-thread-empty">
      <v-card-text class="text-medium-emphasis text-center pa-8">{{ emptyLabel }}</v-card-text>
    </v-card>

    <v-card v-else-if="store.chatsError" class="chat-thread-empty">
      <v-card-text class="text-center pa-8">
        <div class="text-error text-body-2 mb-4">{{ store.chatsError }}</div>
        <v-btn variant="tonal" color="primary" size="small" @click="store.fetchChats()"> Попробовать снова </v-btn>
      </v-card-text>
    </v-card>

    <div v-else-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <div v-else-if="store.chatError" class="chat-thread-error">
      <div class="text-error text-body-2 mb-2">{{ store.chatError }}</div>
      <v-btn variant="tonal" color="primary" size="small" @click="retryAttach"> Попробовать снова </v-btn>
    </div>

    <v-card v-else class="chat-thread" border>
      <ChatSyncStatusBanner />
      <ChatReadAckBanner :chat-id="chatId" />
      <ChatMessageList
        :chat-id="chatId"
        :renderer-context="rendererContext"
        :open-entity="openEntity"
        :build-folds="buildFolds"
        :live-fold-ids="liveFoldIds"
      />
      <ChatInput
        :sending="store.sending"
        :disabled="!canWrite"
        :action-error="store.actionError"
        :speakers="speakers"
        :default-speaker="defaultSpeaker"
        :active-speaker-key="activeSpeakerKey"
        :speaker-action="speakerAction"
        :token-sources="tokenSources"
        :process-attachments="processAttachments"
        :allow-visibility="allowVisibility"
        :visibility-role-options="roleOptions"
        :visibility-options="userOptions"
        :send="handleSend"
        @update:active-speaker-key="emit('update:active-speaker-key', $event)"
      />
      <div
        v-if="!canWrite && chat?.visibility === 'public'"
        class="text-caption text-medium-emphasis text-center pa-1 border-t"
      >
        Чат доступен только для чтения
      </div>
    </v-card>
  </div>
</template>

<style scoped>
.chat-thread-root {
  display: flex;
  flex-direction: column;
  width: 100%;
  min-width: 0;
  height: calc(100vh - var(--v-layout-top) - var(--chat-thread-chrome, 160px));
  min-height: 360px;
  overflow: hidden;
}
.chat-thread {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  width: 100%;
  min-width: 0;
  min-height: 0;
  height: 100%;
  overflow: hidden;
}
.chat-thread-empty {
  border: 1px dashed rgba(var(--v-theme-divider), var(--v-border-opacity));
}
.chat-thread-error {
  padding: 24px;
  text-align: center;
}
</style>
