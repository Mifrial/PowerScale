<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useNotificationInbox, NotificationList } from '@/modules/Messages/Notifications/init';
import FlatTextBtn from '@/modules/Core/UI/Component/FlatTextBtn.vue';

const { isGuest, username } = useCurrentUser();
const notification = useNotificationInbox();

const dashboardItems = computed(() => notification.items.value.slice(0, 4));

const characterCard = {
  icon: 'mdi-account-group',
  label: 'Персонажи',
  description: 'Просмотр и управление персонажами',
  to: '/characters',
};

const gameCard = {
  icon: 'mdi-gamepad-variant',
  label: 'Игры',
  description: 'Список игр и сессий',
  to: '/games',
};

onMounted(() => {
  notification.fetchData();
});
</script>

<template>
  <v-container fluid>
    <h1 class="text-h4 font-weight-bold mb-2">Добро пожаловать{{ isGuest ? '' : `, ${username}` }}</h1>
    <p class="text-body-1 text-medium-emphasis mb-6">
      {{
        isGuest
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

      <NotificationList
        :items="dashboardItems"
        icon-size="20"
        action-size="x-small"
        @action="(p) => notification.markAsRead(p.id)"
      >
        <template #empty> Нет новых уведомлений </template>
      </NotificationList>

      <div class="text-center dashboard-footer mx-n4">
        <FlatTextBtn color="primary" block rounded="0" :to="{ name: 'Notifications' }"> Все уведомления → </FlatTextBtn>
      </div>
    </v-card>
  </v-container>
</template>

<style scoped>
.dashboard-footer {
  border-top: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}

.dashboard-card:hover {
  background-color: rgb(var(--v-theme-primaryLight));
}
</style>
