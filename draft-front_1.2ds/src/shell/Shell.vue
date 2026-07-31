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
      <template v-if="isMessenger">
        <router-view />
      </template>
      <v-container v-else fluid class="pa-6">
        <router-view />
      </v-container>
    </v-main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useChatStore } from '@/modules/Messages/Chat/Store/chat'
import TopBar from './TopBar.vue'
import SideBar from './SideBar.vue'
import ChatBar from '@/modules/Messages/Chat/Components/ChatBar.vue'
import NotificationSlider from '@/modules/Messages/Notifications/Components/NotificationSlider.vue'
import ChatSlider from '@/modules/Messages/Chat/Components/ChatSlider.vue'

const route = useRoute()
const chatStore = useChatStore()
const isMessenger = computed(() => route.path === '/messenger')

const sidebarCollapsed = ref(false)
const notificationOpen = ref(false)
const chatOpen = ref(false)

onMounted(async () => {
  if (chatStore.chats.length === 0) {
    await chatStore.fetchChats()
  }
})
</script>

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
