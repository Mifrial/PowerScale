<script setup lang="ts">
import { computed } from 'vue';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { User } from '@/modules/Core/User/Dto/User';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import { DateTime } from '@/modules/Core/Engine/Value/DateTime';
import { getContentRenderer, getContentRendererEntry, getInlineRenderer } from '@/modules/Messages/Chat/init';
import { inlineContentService } from '@/modules/Messages/Chat/Service/Instance/inlineContentService';
import { initials } from '@/modules/Core/User/Utils/initials';
import { displayName } from '@/modules/Core/User/Utils/displayName';
import ChatVisibilityMenu from '@/modules/Messages/Chat/Component/ChatVisibilityMenu.vue';

const props = defineProps<{
  msg: ChatMessage;
  user?: User;
  /** Подпись ограничения видимости (для зрителя, которому сообщение видно). */
  visibilityLabel?: string;
  /** Разрешить менять видимость (только отправитель своего сообщения). */
  canChangeVisibility?: boolean;
  visibilityRoleOptions?: { code: string; label: string }[];
  visibilityOptions?: { userId: number; name: string }[];
  /** Непрозрачный контекст для inline-рендереров (напр. имена правил ревизии игры). */
  rendererContext?: Record<string, unknown>;
}>();

const emit = defineEmits<{
  'open-profile': [userId: number];
  'update-visibility': [messageId: number, visibility?: ChatMessageVisibility];
}>();

const segments = computed(() => inlineContentService.parse(props.msg.content));

const inlineRenderers = computed(() =>
  segments.value.map((seg) => (seg.kind === 'text' ? null : getInlineRenderer(seg.type))),
);

const metaAttachments = computed(() =>
  props.msg.attachments
    .map((attachment, index) => ({ attachment, index, renderer: getContentRendererEntry(attachment.type) }))
    .filter((item) => item.renderer?.layout === 'meta'),
);

const blockAttachments = computed(() =>
  props.msg.attachments
    .map((attachment, index) => ({ attachment, index, renderer: getContentRenderer(attachment.type) }))
    .filter((item) => getContentRendererEntry(item.attachment.type)?.layout !== 'meta'),
);

const speaker = computed(() => props.msg.speaker ?? null);

/** Имя игрока-отправителя (подпись «(Имя игрока)» у персонажа/НПС). */
const userDisplayName = computed(
  () => displayName(props.user?.name, props.user?.surname, props.user?.login) || props.msg.username,
);

const authorName = computed(() => {
  if (speaker.value?.kind === 'character') return `${speaker.value.characterName} (${userDisplayName.value})`;
  if (speaker.value?.kind === 'npc') return `${speaker.value.npcName} (${userDisplayName.value})`;

  return userDisplayName.value;
});

const authorInitials = computed(() => {
  if (speaker.value?.kind === 'character') return initials(speaker.value.characterName);
  if (speaker.value?.kind === 'npc') return initials(speaker.value.npcName);

  return props.user ? initials(props.user.name, props.user.surname) : '';
});

function onAuthorClick(): void {
  const openEntity = props.rendererContext?.openEntity;
  if (typeof openEntity === 'function' && speaker.value?.kind === 'character') {
    openEntity(`character:${speaker.value.characterId}`);

    return;
  }
  if (typeof openEntity === 'function' && speaker.value?.kind === 'npc') {
    openEntity(`npc:${speaker.value.npcId}`);

    return;
  }
  emit('open-profile', props.msg.userId);
}
</script>

<template>
  <div v-if="props.msg.kind === 'default' || props.msg.kind === 'highlighted'" class="chat-system-divider">
    <v-divider class="chat-system-divider__line" />
    <span
      class="chat-system-divider__text"
      :class="{ 'chat-system-divider__text--highlighted': props.msg.kind === 'highlighted' }"
      >{{ props.msg.content }}</span
    >
    <v-divider class="chat-system-divider__line" />
  </div>

  <div v-else class="chat-message">
    <div class="chat-msg-header">
      <v-avatar
        v-if="props.user"
        color="secondary"
        size="28"
        class="chat-msg-avatar"
        style="cursor: pointer"
        @click="emit('open-profile', props.msg.userId)"
      >
        <span class="text-caption font-weight-medium text-white">{{ authorInitials }}</span>
      </v-avatar>
      <span class="font-weight-medium text-caption chat-msg-author" style="cursor: pointer" @click="onAuthorClick">{{
        authorName
      }}</span>
      <v-chip v-if="speaker?.kind === 'gm'" size="x-small" color="primary" variant="tonal" class="chat-msg-role">
        Ведущий
      </v-chip>
      <v-chip v-if="visibilityLabel" size="x-small" variant="tonal" class="chat-msg-role">
        {{ visibilityLabel }}
      </v-chip>
      <ChatVisibilityMenu
        v-if="canChangeVisibility"
        :model-value="props.msg.visibility"
        :role-options="visibilityRoleOptions ?? []"
        :user-options="visibilityOptions ?? []"
        @update:model-value="(visibility) => emit('update-visibility', props.msg.id, visibility)"
      />
      <template v-for="item in metaAttachments" :key="`meta-${item.index}`">
        <component
          :is="item.renderer?.component"
          :attachment="item.attachment"
          :index="item.index"
          :context="props.rendererContext"
        />
      </template>
      <span class="text-caption text-disabled">{{ DateTime.formatTime(props.msg.createdAt) }}</span>
    </div>
    <div v-if="props.msg.content" class="chat-msg-text">
      <template v-for="(seg, si) in segments" :key="si">
        <template v-if="seg.kind === 'text'">{{ seg.text }}</template>
        <component
          v-else-if="inlineRenderers[si]"
          :is="inlineRenderers[si]?.component"
          :segment="seg"
          :context="props.rendererContext"
        />
      </template>
    </div>
    <div v-if="blockAttachments.length" class="chat-attachments">
      <template v-for="item in blockAttachments" :key="item.index">
        <component
          v-if="item.renderer"
          :is="item.renderer"
          :attachment="item.attachment"
          :index="item.index"
          :context="props.rendererContext"
        />
      </template>
    </div>
  </div>
</template>

<style scoped>
.chat-message {
  padding: 0 16px;
}

.chat-msg-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.chat-msg-avatar {
  flex-shrink: 0;
}
.chat-msg-author {
  flex-shrink: 0;
}
.chat-msg-role {
  flex-shrink: 0;
}

.chat-msg-text {
  padding: 8px 12px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 12px 12px 12px 4px;
  display: inline-block;
  max-width: 80%;
  white-space: pre-wrap;
  text-align: left;
}

.chat-attachments {
  margin-top: 6px;
}

.chat-system-divider {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px 4px;
}
.chat-system-divider__line {
  flex: 1;
}
.chat-system-divider__text {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.55);
  text-align: center;
}
.chat-system-divider__text--highlighted {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.text-disabled {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
}
</style>
