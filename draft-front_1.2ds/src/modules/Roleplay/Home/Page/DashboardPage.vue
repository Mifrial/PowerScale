<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useNotificationStore } from '@/modules/Messages/Notifications/Store/notifications';
import NotificationList from '@/modules/Messages/Notifications/Component/NotificationList.vue';
import FlatTextBtn from '@/modules/Core/UI/Component/FlatTextBtn.vue';

const auth = useAuthStore();
const userStore = useUserStore();
const notification = useNotificationStore();

const dashboardItems = computed(() => notification.items.slice(0, 4));

const characterCard = {
  icon: 'mdi-account-group',
  label: 'Персонажи',
  description: 'Просмотр и управление персонажами',
  to: '/characters',
  count: 2,
};

const gameCard = {
  icon: 'mdi-gamepad-variant',
  label: 'Игры',
  description: 'Список игр и сессий',
  to: '/games',
  count: 1,
};

onMounted(() => {
  notification.fetchData();
});
</script>

<template>
  <div>
    <h1 class="text-h4 font-weight-bold mb-2">Добро пожаловать{{ auth.isGuest ? '' : `, ${userStore.username}` }}</h1>
    <p class="text-body-1 text-medium-emphasis mb-6">
      {{
        auth.isGuest
          ? 'Вы вошли как гость. Часть функций недоступна.'
          : 'PowerScale — система управления RPG-персонажами и правилами'
      }}
    </p>

    <v-row>
      <v-col cols="12" sm="6">
        <v-card :to="characterCard.to" elevation="2" class="pa-4 d-flex flex-column dashboard-card" height="100%" hover>
          <div class="d-flex align-center mb-2">
            <v-icon :icon="characterCard.icon" size="36" color="primary" class="mr-3" />
            <v-card-title class="pa-0 text-body-1 font-weight-medium flex-grow-1">
              {{ characterCard.label }}
            </v-card-title>
            <v-chip v-if="characterCard.count" color="error" size="small" variant="flat">
              {{ characterCard.count }}
            </v-chip>
          </div>
          <v-card-text class="pa-0 text-caption text-medium-emphasis">
            {{ characterCard.description }}
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6">
        <v-card :to="gameCard.to" elevation="2" class="pa-4 d-flex flex-column dashboard-card" height="100%" hover>
          <div class="d-flex align-center mb-2">
            <v-icon :icon="gameCard.icon" size="36" color="primary" class="mr-3" />
            <v-card-title class="pa-0 text-body-1 font-weight-medium flex-grow-1">
              {{ gameCard.label }}
            </v-card-title>
            <v-chip v-if="gameCard.count" color="error" size="small" variant="flat">
              {{ gameCard.count }}
            </v-chip>
          </div>
          <v-card-text class="pa-0 text-caption text-medium-emphasis">
            {{ gameCard.description }}
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card elevation="2" rounded="0" class="pa-4 pb-0 mt-6 dashboard-notification-card">
      <v-card-title class="pa-0 text-body-1 font-weight-medium mb-4 d-flex align-center">
        <v-icon icon="mdi-bell-ring" size="20" class="mr-2" color="primary" />
        Последние уведомления
      </v-card-title>

      <NotificationList :items="dashboardItems" icon-size="20" action-size="x-small" @action="notification.markAsRead">
        <template #empty> Нет новых уведомлений </template>
      </NotificationList>

      <div class="text-center dashboard-footer mx-n4">
        <FlatTextBtn color="primary" block rounded="0" :to="{ name: 'Notifications' }"> Все уведомления → </FlatTextBtn>
      </div>
    </v-card>
  </div>
</template>

<style scoped>
.dashboard-footer {
  border-top: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}

.dashboard-card:hover {
  background-color: rgb(var(--v-theme-primaryLight));
}
</style>
