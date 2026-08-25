<script setup lang="ts">
import { watch, nextTick, onMounted, computed, ref } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';
import { useChatVirtualScroll } from '@/modules/Messages/Chat/Composables/useChatVirtualScroll';
import { useChatVisibilityOptions } from '@/modules/Messages/Chat/Composables/useChatVisibilityOptions';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatFoldChild, ChatVisibleRow } from '@/modules/Messages/Chat/Dto/ChatFold';
import { flattenChatFolds } from '@/modules/Messages/Chat/Utils/flattenChatFolds';
import ChatMessageRow from '@/modules/Messages/Chat/Component/ChatMessageRow.vue';
import ChatFoldChrome from '@/modules/Messages/Chat/Component/ChatFoldChrome.vue';
import ChatFoldPanel from '@/modules/Messages/Chat/Component/ChatFoldPanel.vue';

const props = defineProps<{
  // Явный чат для встроенного обсуждения; не задан — читаем глобальный активный чат.
  chatId?: number | null;
  /** Непрозрачный контекст для inline-рендереров (напр. имена правил ревизии игры). */
  rendererContext?: Record<string, unknown>;
  /** Дерево свёрток; без него лента плоская. */
  buildFolds?: (messages: ChatMessage[]) => ChatFoldChild[];
  /** Id групп, раскрытых по умолчанию (живой раунд/ход/атака). */
  liveFoldIds?: string[];
}>();

const emit = defineEmits<{
  'open-profile': [userId: number];
}>();

const store = useChatStore();
const chatUsers = useChatUsers();
const auth = useAuthStore();

const resolvedChatId = computed(() => props.chatId ?? store.activeChatId);

const chat = computed(() => store.chats.find((candidate) => candidate.id === resolvedChatId.value) ?? null);
const { allowVisibility, roleOptions, userOptions } = useChatVisibilityOptions(chat);

function canChangeVisibilityOf(message: ChatMessage): boolean {
  return allowVisibility.value && auth.userId !== null && message.userId === auth.userId && message.kind == null;
}

async function onUpdateVisibility(messageId: number, visibility?: ChatMessageVisibility): Promise<void> {
  const chatId = resolvedChatId.value;
  if (chatId != null) await store.updateMessageVisibility(chatId, messageId, visibility);
}

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

const userOverride = ref<Record<string, boolean>>({});

function isExpanded(id: string): boolean {
  if (Object.prototype.hasOwnProperty.call(userOverride.value, id)) return userOverride.value[id];

  return (props.liveFoldIds ?? []).includes(id);
}

function toggleFold(id: string): void {
  userOverride.value = { ...userOverride.value, [id]: !isExpanded(id) };
}

const rows = computed<ChatVisibleRow[]>(() => {
  const unread = firstUnread.value;
  if (!props.buildFolds) {
    return messages.value.map((message) => ({
      type: 'message',
      key: `m:${message.id}`,
      message,
      unread: unread != null && message.id === unread,
    }));
  }

  return flattenChatFolds(props.buildFolds(messages.value), isExpanded, unread);
});

const virtual = useChatVirtualScroll({
  getScrollElement: () => virtual.scrollElement.value,
  getCount: () => rows.value.length,
  getItemKey: (index: number) => rows.value[index]?.key ?? index,
  onReachTop: () => (resolvedChatId.value != null ? store.loadOlder(resolvedChatId.value) : store.loadOlderMessages()),
  hasMoreOlder: () => hasMore.value,
  loadingOlder: () => loadingOlder.value,
});

function getUser(userId: number) {
  return chatUsers.getUser(userId);
}

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

function rowAt(index: number): ChatVisibleRow | undefined {
  return rows.value[index];
}

function chromeOf(index: number) {
  const row = rows.value[index];

  return row?.type === 'chrome' ? row : null;
}

function messageRowOf(index: number) {
  const row = rows.value[index];

  return row?.type === 'message' ? row : null;
}

function panelOf(index: number) {
  const row = rows.value[index];

  return row?.type === 'panel' ? row : null;
}

onMounted(async () => {
  await nextTick();
  virtual.scrollToEnd();
  virtual.updatePinned();
});

watch(resolvedChatId, async () => {
  userOverride.value = {};
  await nextTick();
  virtual.scrollToEnd();
  virtual.updatePinned();
});

watch(
  () => rows.value.map((row) => ('expanded' in row ? `${row.key}:${row.expanded}` : row.key)).join('|'),
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
          <div v-if="rowAt(vItem.index)?.unread" class="chat-unread-divider">
            <v-divider />
            <span class="text-caption text-medium-emphasis mx-2">Новые сообщения</span>
            <v-divider />
          </div>
          <ChatFoldChrome
            v-if="chromeOf(vItem.index)"
            :summary="chromeOf(vItem.index)!.summary"
            :expanded="chromeOf(vItem.index)!.expanded"
            :tone="chromeOf(vItem.index)!.tone"
            :variant="chromeOf(vItem.index)!.variant"
            :renderer-context="props.rendererContext"
            @toggle="toggleFold(chromeOf(vItem.index)!.foldId)"
          />
          <ChatFoldPanel
            v-else-if="panelOf(vItem.index)"
            :summary="panelOf(vItem.index)!.summary"
            :expanded="panelOf(vItem.index)!.expanded"
            :renderer-context="props.rendererContext"
            @toggle="toggleFold(panelOf(vItem.index)!.foldId)"
          >
            <template v-for="message in panelOf(vItem.index)!.messages" :key="message.id">
              <div v-if="panelOf(vItem.index)!.unreadMessageId === message.id" class="chat-unread-divider">
                <v-divider />
                <span class="text-caption text-medium-emphasis mx-2">Новые сообщения</span>
                <v-divider />
              </div>
              <ChatMessageRow
                :msg="message"
                :user="getUser(message.userId)"
                :visibility-label="visibilityLabelOf(message)"
                :can-change-visibility="canChangeVisibilityOf(message)"
                :visibility-role-options="roleOptions"
                :visibility-options="userOptions"
                :renderer-context="props.rendererContext"
                @open-profile="emit('open-profile', $event)"
                @update-visibility="onUpdateVisibility"
              />
            </template>
          </ChatFoldPanel>
          <ChatMessageRow
            v-else-if="messageRowOf(vItem.index)"
            :msg="messageRowOf(vItem.index)!.message"
            :user="getUser(messageRowOf(vItem.index)!.message.userId)"
            :visibility-label="visibilityLabelOf(messageRowOf(vItem.index)!.message)"
            :can-change-visibility="canChangeVisibilityOf(messageRowOf(vItem.index)!.message)"
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
