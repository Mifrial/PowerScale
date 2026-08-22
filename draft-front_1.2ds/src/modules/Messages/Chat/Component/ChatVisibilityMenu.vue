<script setup lang="ts">
import { ref } from 'vue';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';

/**
 * Меню видимости сообщения (отправка или правка уже отправленного): «Всем» / выбранная роль /
 * выбранные участники. Модель — текущая видимость (`undefined` = всем); применяется сразу.
 * Роли/участников поставляет домен/хост — Chat не знает их значений.
 */
defineProps<{
  roleOptions: { code: string; label: string }[];
  userOptions: { userId: number; name: string }[];
}>();

const model = defineModel<ChatMessageVisibility | undefined>({ default: undefined });

const menuOpen = ref(false);
const selectedRole = ref<string | null>(null);
const selectedUsers = ref<number[]>([]);

const hasRestriction = ref(false);

function open(): void {
  const current = model.value;
  const restricted = current?.all === false;
  selectedRole.value = restricted && typeof current?.forRole === 'string' ? current.forRole : null;
  selectedUsers.value = restricted ? (current?.forUsers ?? []) : [];
  hasRestriction.value = restricted;
  menuOpen.value = true;
}

function apply(): void {
  if (selectedRole.value) {
    model.value = { all: false, forRole: selectedRole.value };
    hasRestriction.value = true;
  } else if (selectedUsers.value.length > 0) {
    model.value = { all: false, forUsers: [...selectedUsers.value] };
    hasRestriction.value = true;
  } else {
    model.value = undefined;
    hasRestriction.value = false;
  }
}

function toggleUser(userId: number, checked: boolean): void {
  if (checked) selectedUsers.value.push(userId);
  else selectedUsers.value = selectedUsers.value.filter((id) => id !== userId);
  apply();
}
</script>

<template>
  <v-menu v-model="menuOpen" :close-on-content-click="false" location="top end">
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        icon
        variant="text"
        size="x-small"
        :color="hasRestriction ? 'primary' : undefined"
        :title="hasRestriction ? 'Видимость ограничена' : 'Видимость сообщения'"
        aria-label="Видимость сообщения"
        @click="open"
      >
        <v-icon>mdi-eye-outline</v-icon>
      </v-btn>
    </template>
    <v-card min-width="240" elevation="8" border>
      <v-card-text class="pa-2">
        <v-list density="compact" class="mb-1">
          <v-list-item density="compact" :active="selectedRole === null && selectedUsers.length === 0" @click="apply">
            Всем
          </v-list-item>
          <v-list-item
            v-for="option in roleOptions"
            :key="option.code"
            density="compact"
            :active="selectedRole === option.code"
            @click="
              selectedRole = option.code;
              selectedUsers = [];
              apply();
            "
          >
            {{ option.label }}
          </v-list-item>
        </v-list>
        <div class="text-caption text-medium-emphasis px-2 mb-1">Выбранным:</div>
        <div v-if="userOptions.length === 0" class="text-caption text-medium-emphasis px-2">Участников нет</div>
        <v-checkbox
          v-for="option in userOptions"
          :key="option.userId"
          :label="option.name"
          density="compact"
          hide-details
          :model-value="selectedUsers.includes(option.userId)"
          class="visibility-checkbox"
          @update:model-value="(checked) => toggleUser(option.userId, Boolean(checked))"
        />
      </v-card-text>
    </v-card>
  </v-menu>
</template>
