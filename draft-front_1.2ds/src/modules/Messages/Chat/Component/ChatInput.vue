<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatSpeakerOption } from '@/modules/Messages/Chat/Dto/ChatSpeakerOption';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import { getCommandHandlers, getToolbarExtensions, getAttachmentProcessor } from '@/modules/Messages/Chat/init';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import InlineTokenPicker from '@/modules/Messages/Chat/Component/InlineTokenPicker.vue';
import ChatVisibilityMenu from '@/modules/Messages/Chat/Component/ChatVisibilityMenu.vue';

const props = withDefaults(
  defineProps<{
    sending: boolean;
    disabled?: boolean;
    actionError?: string;
    send: (
      text: string,
      attachments: ChatAttachment[],
      speaker?: ChatSpeaker,
      visibility?: ChatMessageVisibility,
    ) => Promise<boolean>;
    /** «От лица кого» писать (игровой чат). Пусто — селектор скрыт (обычные чаты). */
    speakers?: ChatSpeakerOption[];
    defaultSpeaker?: string | null;
    /** Активный источник речи (авто-переключение селектора, напр. персонаж текущего хода инициативы). */
    activeSpeakerKey?: string | null;
    /** Действие вместо иконки у селектора «от лица кого» (игровой чат — открыть карточку). */
    speakerAction?: { icon: string; title: string; disabled?: boolean; onClick: () => void } | null;
    /** Источники токенов «Вставить ссылку» (игровой чат — правила из ревизии). Пусто — глобальные. */
    tokenSources?: ITokenSource[];
    /** Трансформация вложений перед отправкой (игровой чат резолвит дефолты броска из ревизии). */
    processAttachments?: (attachments: ChatAttachment[]) => ChatAttachment[];
    /** Разрешить выбор видимости сообщения (игровой чат). */
    allowVisibility?: boolean;
    /** Роли для видимости «выбранной роли» (значения задаёт домен: Только ГМ). */
    visibilityRoleOptions?: { code: string; label: string }[];
    /** Участники для видимости «выбранным» (мультиселект). */
    visibilityOptions?: { userId: number; name: string }[];
  }>(),
  {
    disabled: false,
    visibilityRoleOptions: () => [],
    visibilityOptions: () => [],
  },
);

const messageText = ref('');
const pendingAttachments = ref<ChatAttachment[]>([]);
const textAreaRef = ref<{ $el: HTMLElement } | null>(null);

const toolbarExtensions = getToolbarExtensions();

// Расширения тулбара: 'bar' — над полем ввода, 'actions' — в колонку действий (у кнопки прикрепления).
const barExtensions = computed(() => toolbarExtensions.filter((ext) => ext.placement !== 'actions'));
const actionsExtensions = computed(() => toolbarExtensions.filter((ext) => ext.placement === 'actions'));

// Видимость отправляемого сообщения (игровой чат): «Всем» / роль / выбранным участникам.
const visibilityModel = ref<ChatMessageVisibility | undefined>(undefined);

// Все пути отправки проходят через doSend — единая точка: speaker из селектора,
// видимость и трансформация вложений (дефолты броска из ревизии игры) перед props.send.
function doSend(text: string, attachments: ChatAttachment[]): Promise<boolean> {
  const processed = props.processAttachments ? props.processAttachments(attachments) : attachments;

  return props.send(text, processed, selectedSpeaker.value?.speaker, visibilityModel.value);
}

const toolbarContext = computed(() => ({
  attachments: pendingAttachments.value,
  addAttachment,
  removeAttachment,
  send: (text: string, attachments: ChatAttachment[]) => doSend(text, attachments),
  disabled: props.disabled ?? false,
}));

const canSend = computed(() => messageText.value.trim().length > 0 || pendingAttachments.value.length > 0);

const commandError = ref('');

// Селектор «от лица кого» (игровой чат). Сброс при смене набора опций — например,
// выбор игроком своего персонажа после одобрения (опции приходят от хоста).
const selectedSpeakerKey = ref<string | null>(null);

const emit = defineEmits<{
  /** Текущий источник речи (для хоста: авто-подстановка + ручной выбор). */
  'update:active-speaker-key': [key: string | null];
}>();

watch(selectedSpeakerKey, (key) => emit('update:active-speaker-key', key));

const speakers = computed(() => props.speakers ?? []);

const selectedSpeaker = computed<ChatSpeakerOption | null>(
  () => speakers.value.find((option) => option.key === selectedSpeakerKey.value) ?? null,
);

watch(
  speakers,
  () => {
    if (speakers.value.some((option) => option.key === selectedSpeakerKey.value)) return;
    const fallback = props.defaultSpeaker ?? speakers.value[0]?.key ?? null;
    selectedSpeakerKey.value = fallback;
  },
  { immediate: true },
);

// Авто-переключение на активный источник речи (персонаж текущего хода инициативы):
// только если ключ доступен в списке опций пользователя; ручной выбор не сбрасывается.
watch(
  () => props.activeSpeakerKey,
  (key) => {
    if (key !== null && key !== undefined && speakers.value.some((option) => option.key === key)) {
      selectedSpeakerKey.value = key;
    }
  },
);

watch(messageText, () => {
  commandError.value = '';
});

function addAttachment(attachment: ChatAttachment) {
  pendingAttachments.value.push(attachment);
}

function removeAttachment(index: number) {
  pendingAttachments.value.splice(index, 1);
}

function attachmentLabel(attachment: ChatAttachment): string {
  return getAttachmentProcessor(attachment.type)?.describe?.(attachment.payload) ?? attachment.type;
}

async function handleSend() {
  if (!canSend.value) return;
  if (speakers.value.length && !selectedSpeaker.value) return;
  const text = messageText.value.trim();
  if (text.startsWith('/')) {
    for (const handler of getCommandHandlers()) {
      const parsed = handler.parse(text);
      if (parsed) {
        if (await doSend(parsed.content, parsed.attachments)) {
          messageText.value = '';
          pendingAttachments.value = [];
        }

        return;
      }
    }
    commandError.value = 'Неизвестная команда';

    return;
  }
  if (await doSend(text, pendingAttachments.value)) {
    messageText.value = '';
    pendingAttachments.value = [];
    visibilityModel.value = undefined;
  }
}
</script>

<template>
  <div class="chat-input-area">
    <template v-for="ext in barExtensions" :key="ext.id">
      <component :is="ext.component" v-bind="toolbarContext" />
    </template>

    <div v-if="!disabled && pendingAttachments.length" class="roll-bar">
      <div v-for="(att, ai) in pendingAttachments" :key="ai" class="roll-chip">
        <v-icon icon="mdi-dice-d6" size="14" />
        {{ attachmentLabel(att) }}
        <v-icon icon="mdi-close" size="14" class="ml-1 roll-chip-remove" @click="removeAttachment(ai)" />
      </div>
    </div>

    <div v-if="actionError" class="text-caption text-error px-1 pb-1">{{ actionError }}</div>
    <div v-if="commandError" class="text-caption text-error px-1 pb-1">{{ commandError }}</div>

    <div class="chat-input-wrapper">
      <v-textarea
        ref="textAreaRef"
        v-model="messageText"
        placeholder="Напишите сообщение..."
        hide-details
        auto-grow
        rows="1"
        max-height="210"
        variant="outlined"
        class="chat-input"
        :disabled="disabled"
        @keydown.enter.exact.prevent="handleSend"
      />
      <div v-if="!disabled" class="chat-input-controls">
        <div v-if="speakers.length" class="speaker-bar">
          <button
            v-if="speakerAction"
            type="button"
            class="speaker-bar__action"
            :title="speakerAction.title"
            :disabled="speakerAction.disabled"
            @click="speakerAction.onClick"
          >
            <v-icon :icon="speakerAction.icon" size="16" />
          </button>
          <v-icon v-else icon="mdi-account-voice" size="16" class="speaker-bar__icon" />
          <v-select
            v-model="selectedSpeakerKey"
            :items="speakers"
            item-title="label"
            item-value="key"
            density="compact"
            variant="outlined"
            hide-details
            class="speaker-select"
            aria-label="От лица кого"
          />
        </div>
        <div class="chat-input-actions">
          <template v-for="ext in actionsExtensions" :key="ext.id">
            <component :is="ext.component" v-bind="toolbarContext" />
          </template>
          <InlineTokenPicker
            v-model="messageText"
            :sources="tokenSources ?? []"
            :target-ref="textAreaRef"
            :disabled="disabled"
          />

          <ChatVisibilityMenu
            v-if="allowVisibility"
            v-model="visibilityModel"
            :role-options="visibilityRoleOptions"
            :user-options="visibilityOptions"
          />

          <v-btn
            icon
            variant="tonal"
            size="x-small"
            :loading="sending"
            :disabled="!canSend"
            aria-label="Отправить"
            @click="handleSend"
          >
            <v-icon>mdi-send</v-icon>
          </v-btn>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chat-input-area {
  flex-shrink: 0;
  padding: 8px 12px 12px;
}

.roll-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 4px 0;
}

.roll-chip {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgb(var(--v-theme-primaryLight));
  border-radius: 16px;
  padding: 2px 10px;
  font-size: 12px;
}

.roll-chip-remove {
  cursor: pointer;
  opacity: 0.5;
}
.roll-chip-remove:hover {
  opacity: 1;
}

.chat-input-wrapper {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.38);
  border-radius: 4px;
}
.chat-input-wrapper:focus-within {
  border-color: rgb(var(--v-theme-on-surface));
}

.chat-input-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 2px 16px 4px;
}

.speaker-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1 1 auto;
  min-width: 0;
}
.speaker-bar__icon {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  flex-shrink: 0;
}
.speaker-bar__action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-shrink: 0;
  color: rgb(var(--v-theme-on-surface));
}
.speaker-bar__action:hover {
  color: rgb(var(--v-theme-primary));
}
.speaker-bar__action:disabled {
  cursor: default;
  opacity: 0.4;
}
.speaker-select {
  flex: 1;
  min-width: 160px;
  max-width: 320px;
}
.speaker-select :deep(.v-field) {
  --v-input-control-height: 32px;
  --v-input-padding-top: 4px;
  --v-field-padding-bottom: 4px;
}
.speaker-select :deep(.v-field:not(.v-field--focused) .v-field__outline) {
  --v-field-border-opacity: 0.2;
}
.speaker-select :deep(.v-field.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 0.5;
  --v-field-border-width: 1px;
}
.chat-input-actions {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.chat-input-actions :deep(.v-btn:hover) {
  background: rgba(var(--v-theme-primary), 0.15);
  color: rgb(var(--v-theme-primary));
}

.chat-input {
  flex: 1;
}
.chat-input :deep(.v-field__outline) {
  display: none;
}

.text-disabled {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
}
</style>
