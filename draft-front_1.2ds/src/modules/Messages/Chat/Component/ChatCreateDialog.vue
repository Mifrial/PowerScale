<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useChatUserPicker } from '@/modules/Messages/Chat/Composables/useChatUserPicker';
import { displayName, useCurrentUser } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';

const open = defineModel<boolean>('open', { default: false });

const store = useChatStore();
const { userId: currentUserId } = useCurrentUser();
const { users, loading, error, load } = useChatUserPicker();

const kind = ref<'private' | 'group'>('private');
const groupName = ref('');
const selectedUserId = ref<number | null>(null);
const selectedMemberIds = ref<number[]>([]);

const otherUsers = computed(() => users.value.filter((user) => user.id !== currentUserId.value));

const userItems = computed(() =>
  otherUsers.value.map((user) => ({
    title: labelOf(user),
    value: user.id,
  })),
);

function labelOf(user: User): string {
  return displayName(user.name, user.surname, user.login);
}

watch(open, async (isOpen) => {
  if (!isOpen) return;
  kind.value = 'private';
  groupName.value = '';
  selectedUserId.value = null;
  selectedMemberIds.value = [];
  store.createError = '';
  await load();
});

async function submit() {
  if (kind.value === 'private') {
    if (selectedUserId.value === null) return;
    const ok = await store.addPrivate(selectedUserId.value);
    if (ok) open.value = false;

    return;
  }

  const ok = await store.addGroup(groupName.value, selectedMemberIds.value);
  if (ok) open.value = false;
}
</script>

<template>
  <v-dialog v-model="open" max-width="480">
    <v-card>
      <v-card-title>Новый чат</v-card-title>
      <v-card-text>
        <v-btn-toggle v-model="kind" mandatory density="compact" class="mb-4" color="primary">
          <v-btn value="private">Личный</v-btn>
          <v-btn value="group">Группа</v-btn>
        </v-btn-toggle>

        <div v-if="error" class="text-error text-body-2 mb-2">
          {{ error }}
          <v-btn variant="text" size="small" @click="load()">Повторить</v-btn>
        </div>
        <div v-if="store.createError" class="text-error text-body-2 mb-2">{{ store.createError }}</div>

        <v-text-field
          v-if="kind === 'group'"
          v-model="groupName"
          label="Название"
          variant="outlined"
          class="mb-3"
          hide-details
        />

        <v-autocomplete
          v-if="kind === 'private'"
          v-model="selectedUserId"
          :items="userItems"
          :loading="loading"
          label="Собеседник"
          variant="outlined"
          hide-details
          clearable
        />
        <v-autocomplete
          v-else
          v-model="selectedMemberIds"
          :items="userItems"
          :loading="loading"
          label="Участники (необязательно)"
          variant="outlined"
          hide-details
          multiple
          chips
          closable-chips
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
        <v-btn
          color="primary"
          :loading="store.creating"
          :disabled="kind === 'private' ? selectedUserId === null : groupName.trim() === ''"
          @click="submit"
        >
          Создать
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
