<script setup lang="ts">
// Shell — composition root приложения: собирает app-level UI из компонентов модулей
// напрямую (по аналогии с main.ts, регистрирующим модули). Исключение из правила 28.
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useNotificationStore } from '@/modules/Messages/Notifications/Store/notifications';
import TopBar from '@/shell/TopBar.vue';
import SideBar from '@/shell/SideBar.vue';
import ChatBar from '@/modules/Messages/Chat/Component/ChatBar.vue';
import NotificationSlider from '@/modules/Messages/Notifications/Component/NotificationSlider.vue';
import ChatSlider from '@/modules/Messages/Chat/Component/ChatSlider.vue';

const route = useRoute();
const chatStore = useChatStore();
const notificationStore = useNotificationStore();
const isMessenger = computed(() => route.path === '/messenger');

const sidebarCollapsed = ref(false);
const notificationOpen = ref(false);
const chatOpen = ref(false);

onMounted(async () => {
  if (chatStore.chats.length === 0) {
    await chatStore.fetchChats();
  }
  notificationStore.fetchData();
});
</script>

<template>
  <div>
    <TopBar
      @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed"
      @toggle-notifications="notificationOpen = !notificationOpen"
    />
    <SideBar v-model:collapsed="sidebarCollapsed" />
    <ChatBar @open-chat="chatOpen = true" />
    <NotificationSlider v-model="notificationOpen" />
    <ChatSlider v-model="chatOpen" />

    <v-main :class="{ 'messenger-main': isMessenger }">
      <!-- Гаттер страницы — на самих страницах (свой v-container), чтобы не было контейнера в контейнере -->
      <router-view />
    </v-main>
  </div>
</template>

<style scoped>
.messenger-main {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.messenger-main.v-main {
  padding-top: var(--v-layout-top);
  padding-left: var(--v-layout-left);
}
</style>
