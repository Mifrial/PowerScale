<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { avatarColors } from '@/modules/Messages/Chat/Constant/avatarColors';
import { displayName, useUserCatalog, UserProfileSlider } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';

const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
}>();

const catalog = useUserCatalog();
const sliderOpen = ref(false);
const resolved = ref<User | null>(null);
const resolving = ref(false);

const login = computed(() => props.segment.params[0] ?? '');
const user = computed(() => resolved.value ?? catalog.findByLogin(login.value));

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

// Токен хранит логин: профиль может быть не в каталоге (заметки, старые сообщения) —
// догружаем по логину, «Объект скрыт» показываем только после неудачного поиска.
watch(
  login,
  async (value) => {
    resolved.value = null;
    resolving.value = false;
    if (!value || catalog.findByLogin(value)) return;
    resolving.value = true;
    try {
      const found = await catalog.resolveByLogin(value);
      if (login.value === value) resolved.value = found;
    } catch {
      if (login.value === value) resolved.value = null;
    } finally {
      if (login.value === value) resolving.value = false;
    }
  },
  { immediate: true },
);
</script>

<template>
  <span v-if="user" class="chat-user-chip" :style="{ '--chip-color': color }" @click="sliderOpen = true">
    <v-icon size="x-small" class="mr-1">mdi-account</v-icon>
    <span class="chat-user-chip__name">{{ label }}</span>
  </span>
  <span v-else-if="resolving" class="chat-user-chip chat-user-chip--pending">
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

.chat-user-chip--pending {
  color: rgba(var(--v-theme-on-surface), 0.6);
  cursor: default;
}

.chat-user-chip--hidden {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  cursor: default;
  font-style: italic;
}
</style>
