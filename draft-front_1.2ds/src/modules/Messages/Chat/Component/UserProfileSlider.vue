<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import type { User } from '@/modules/Core/User/Dto/User';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import { initials as userInitials, displayName as userDisplayName } from '@/modules/Core/User/Utils/profile';

const props = defineProps<{
  userId: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const userStore = useUserStore();
const userData = ref<User | null>(null);

const initials = computed(() => {
  if (!userData.value) return '??';

  return userInitials(userData.value.name, userData.value.surname);
});

const displayName = computed(() => {
  if (!userData.value) return '';

  return userDisplayName(userData.value.name, userData.value.surname, userData.value.login);
});

watch(
  () => props.userId,
  async (id) => {
    if (id == null) {
      userData.value = null;

      return;
    }
    const existing = userStore.users.find((u) => u.id === id);
    if (existing) {
      userData.value = existing;

      return;
    }
    try {
      userData.value = await userStore.getUser(id);
    } catch {
      userData.value = null;
    }
  },
  { immediate: true },
);
</script>

<template>
  <SlidePanel v-model="open" width="520px">
    <template #header>
      <div class="d-flex align-center ga-2 w-100">
        <v-btn icon variant="text" size="small" @click="open = false">
          <v-icon>mdi-close</v-icon>
        </v-btn>
        <span class="font-weight-bold text-body-1">Профиль пользователя</span>
      </div>
    </template>

    <div v-if="userData" class="pa-6">
      <div class="text-center mb-6">
        <v-avatar color="primary" size="80" class="mb-3">
          <span class="text-h4 font-weight-medium text-white">{{ initials }}</span>
        </v-avatar>
        <div class="text-h6 font-weight-medium">{{ displayName }}</div>
        <div class="text-caption text-medium-emphasis">@{{ userData.login }}</div>
      </div>

      <v-card variant="outlined" class="mb-4">
        <v-list density="compact">
          <v-list-item>
            <template #title>Имя</template>
            <template #subtitle>{{ userData.name || '—' }}</template>
          </v-list-item>
          <v-list-item>
            <template #title>Фамилия</template>
            <template #subtitle>{{ userData.surname || '—' }}</template>
          </v-list-item>
          <v-list-item>
            <template #title>Псевдоним</template>
            <template #subtitle>
              <span v-if="userData.nickname">@{{ userData.nickname }}</span>
              <span v-else class="text-medium-emphasis">—</span>
            </template>
          </v-list-item>
          <v-list-item>
            <template #title>Email</template>
            <template #subtitle>{{ userData.email }}</template>
          </v-list-item>
          <v-list-item>
            <template #title>Статус</template>
            <template #subtitle>
              <v-chip :color="userData.active ? 'success' : 'grey'" size="x-small" label>
                {{ userData.active ? 'Активен' : 'Отключён' }}
              </v-chip>
            </template>
          </v-list-item>
          <v-list-item>
            <template #title>Зарегистрирован</template>
            <template #subtitle>{{ userData.registered || '—' }}</template>
          </v-list-item>
          <v-list-item>
            <template #title>Последний вход</template>
            <template #subtitle>{{ userData.lastLogin || '—' }}</template>
          </v-list-item>
        </v-list>
      </v-card>

      <v-card variant="outlined" v-if="userData.groups?.length">
        <v-card-item>
          <v-card-title class="text-body-2 font-weight-bold">Группы</v-card-title>
        </v-card-item>
        <v-card-text>
          <v-chip v-for="g in userData.groups" :key="g" size="small" label class="mr-1 mb-1">{{ g }}</v-chip>
        </v-card-text>
      </v-card>
    </div>
    <div v-else class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>
  </SlidePanel>
</template>
