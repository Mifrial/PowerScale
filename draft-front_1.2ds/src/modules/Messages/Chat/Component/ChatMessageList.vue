<script setup lang="ts">
import { watch, nextTick, onMounted, computed } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';
import { useChatVirtualScroll } from '@/modules/Messages/Chat/Composables/useChatVirtualScroll';
import { useChatVisibilityOptions } from '@/modules/Messages/Chat/Composables/useChatVisibilityOptions';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import ChatMessageRow from '@/modules/Messages/Chat/Component/ChatMessageRow.vue';

const props = defineProps<{
  // Явный чат для встроенного обсуждения; не задан — читаем глобальный активный чат.
  chatId?: number | null;
  /** Непрозрачный контекст для inline-рендереров (напр. имена правил ревизии игры). */
  rendererContext?: Record<string, unknown>;
}>();

const emit = defineEmits<{
  'open-profile': [userId: number];
}>();

const store = useChatStore();
const chatUsers = useChatUsers();
const auth = useAuthStore();

// Для встроенного обсуждения сообщения берутся по chatId, а не из глобального activeChatId,
// чтобы слайдер/страница мессенджера не перетирали открытый чат персонажа.
const resolvedChatId = computed(() => props.chatId ?? store.activeChatId);

// Чат + опции видимости (роли типа + участники) — для бейджа и меню «Видимость» у своих сообщений.
// Сами сообщения уже отфильтрованы на уровне моков (единственный гейт видимости).
const chat = computed(() => store.chats.find((candidate) => candidate.id === resolvedChatId.value) ?? null);
const { allowVisibility, roleOptions, userOptions } = useChatVisibilityOptions(chat);

function canChangeVisibilityOf(message: ChatMessage): boolean {
  return allowVisibility.value && auth.userId !== null && message.userId === auth.userId && message.kind == null;
}

async function onUpdateVisibility(messageId: number, visibility?: ChatMessageVisibility): Promise<void> {
  const chatId = resolvedChatId.value;
  if (chatId != null) await store.updateMessageVisibility(chatId, messageId, visibility);
}

// Сообщения уже отфильтрованы моком по видимости (скрытое не доставляется).
const messages = computed(() =>
  resolvedChatId.value != null ? store.messagesOf(resolvedChatId.value) : store.allMessages,
);
const firstUnread = computed(() =>
  resolvedChatId.value != null ? store.firstUnreadOf(resolvedChatId.value) : store.firstUnreadMessageId,
);
const hasMore = computed(() =>
  resolvedChatId.value != null ? store.hasMoreOlderOf(resolvedChatId.value) : store.hasMoreOlder,
);
const loadingOlder = computed(() =>
  resolvedChatId.value != null ? store.loadingOlderOf(resolvedChatId.value) : store.loadingOlder,
);

const virtual = useChatVirtualScroll({
  getScrollElement: () => virtual.scrollElement.value,
  getMessages: () => messages.value,
  onReachTop: () => (resolvedChatId.value != null ? store.loadOlder(resolvedChatId.value) : store.loadOlderMessages()),
  hasMoreOlder: () => hasMore.value,
  loadingOlder: () => loadingOlder.value,
});

function getUser(userId: number) {
  return chatUsers.getUser(userId);
}

/** Подпись ограничения видимости видимого сообщения («Только для ГМ», «выбрано N»). */
function visibilityLabelOf(message: ChatMessage): string | undefined {
  const visibility = message.visibility;
  if (!visibility || visibility.all !== false) return undefined;
  const parts: string[] = [];
  if (visibility.forRole) {
    const codes = typeof visibility.forRole === 'string' ? [visibility.forRole] : visibility.forRole;
    parts.push(...codes.map((code) => roleOptions.value.find((role) => role.code === code)?.label ?? code));
  }
  if (visibility.forUsers?.length) parts.push(`выбрано ${visibility.forUsers.length}`);

  return parts.length > 0 ? parts.join(', ') : undefined;
}

onMounted(async () => {
  await nextTick();
  virtual.scrollToEnd();
  virtual.updatePinned();
});

watch(resolvedChatId, async () => {
  await nextTick();
  virtual.scrollToEnd();
  virtual.updatePinned();
});

watch(
  () => messages.value.length,
  async () => {
    await nextTick();
    if (virtual.pinnedToEnd.value) {
      virtual.scrollToEnd();
    }
    virtual.updatePinned();
  },
);
</script>

<template>
  <div class="chat-messages" :ref="virtual.registerElement" @scroll="virtual.onScroll">
    <div v-if="loadingOlder" class="d-flex justify-center pa-3">
      <v-progress-circular indeterminate width="2" size="20" color="primary" />
    </div>
    <div v-if="!hasMore && messages.length > 0" class="text-center text-caption text-medium-emphasis pa-2">
      Начало переписки
    </div>

    <div class="virtual-viewport">
      <div class="virtual-content" :style="{ height: `${virtual.virtualizer.value.getTotalSize()}px` }">
        <div
          v-for="vItem in virtual.virtualizer.value.getVirtualItems()"
          :key="String(vItem.key)"
          :data-index="vItem.index"
          :ref="virtual.measureElement"
          class="virtual-item"
          :style="{ transform: `translateY(${vItem.start}px)` }"
        >
          <div v-if="firstUnread === messages[vItem.index]?.id" class="chat-unread-divider">
            <v-divider />
            <span class="text-caption text-medium-emphasis mx-2">Новые сообщения</span>
            <v-divider />
          </div>
          <ChatMessageRow
            v-if="messages[vItem.index]"
            :msg="messages[vItem.index]"
            :user="getUser(messages[vItem.index].userId)"
            :visibility-label="visibilityLabelOf(messages[vItem.index])"
            :can-change-visibility="canChangeVisibilityOf(messages[vItem.index])"
            :visibility-role-options="roleOptions"
            :visibility-options="userOptions"
            :renderer-context="props.rendererContext"
            @open-profile="emit('open-profile', $event)"
            @update-visibility="onUpdateVisibility"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chat-messages {
  flex: 1;
  min-height: 0;
  height: 1px;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding-top: 4px;
}

.virtual-viewport {
  position: relative;
}

.virtual-content {
  position: relative;
  width: 100%;
}

.virtual-item {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  padding-bottom: 12px;
}

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
