<script setup lang="ts">
import { computed, ref } from 'vue';
import { avatarColors } from '@/modules/Messages/Chat/Constant/avatarColors';
import { displayName, useUserCatalog, UserProfileSlider } from '@/modules/Core/User/init';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';

const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
}>();

const catalog = useUserCatalog();
const sliderOpen = ref(false);

const login = computed(() => props.segment.params[0] ?? '');
const user = computed(() => catalog.findByLogin(login.value));

const label = computed(() =>
  user.value ? displayName(user.value.name, user.value.surname, user.value.login) : login.value,
);

const color = computed(() => {
  let hash = 0;
  for (let i = 0; i < login.value.length; i++) {
    hash = (hash * 31 + login.value.charCodeAt(i)) | 0;
  }

  return avatarColors[Math.abs(hash) % avatarColors.length];
});
</script>

<template>
  <span v-if="user" class="chat-user-chip" :style="{ '--chip-color': color }" @click="sliderOpen = true">
    <v-icon size="x-small" class="mr-1">mdi-account</v-icon>
    <span class="chat-user-chip__name">{{ label }}</span>
  </span>
  <span v-else class="chat-user-chip chat-user-chip--hidden">
    <v-icon size="x-small" class="mr-1">mdi-account-lock</v-icon>
    <span>Объект скрыт</span>
  </span>

  <UserProfileSlider v-model:open="sliderOpen" :user-id="user?.id ?? null" />
</template>

<style scoped>
.chat-user-chip {
  display: inline-flex;
  align-items: center;
  color: var(--chip-color);
  font-weight: 600;
  cursor: pointer;
}

.chat-user-chip__name {
  font-weight: 600;
}

.chat-user-chip--hidden {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  cursor: default;
  font-style: italic;
}
</style>
