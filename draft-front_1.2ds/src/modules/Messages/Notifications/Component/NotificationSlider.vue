<script setup lang="ts">
import { watch, computed } from 'vue';
import { useNotificationStore } from '@/modules/Messages/Notifications/Store/notifications';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import NotificationList from '@/modules/Messages/Notifications/Component/NotificationList.vue';
import FlatTextBtn from '@/modules/Core/UI/Component/FlatTextBtn.vue';
import { SLIDER_LIMIT } from '@/modules/Messages/Notifications/Constant/notificationSliderConfig';

const open = defineModel<boolean>({ default: false });
const store = useNotificationStore();

const displayItems = computed(() => store.items.slice(0, SLIDER_LIMIT));

watch(
  open,
  async (val) => {
    if (val && !store.items.length) {
      await store.fetchData();
    }
  },
  { immediate: true },
);
</script>

<template>
  <SlidePanel v-model="open">
    <template #header>
      <span class="text-body-1 font-weight-medium flex-grow-1">Уведомления</span>
    </template>

    <div class="notification-body">
      <NotificationList :items="displayItems" icon-size="22" action-size="x-small" @action="store.markAsRead">
        <template #empty> Нет уведомлений </template>
      </NotificationList>
    </div>

    <template #footer>
      <FlatTextBtn color="primary" block rounded="0" :to="{ name: 'Notifications' }"> Все уведомления → </FlatTextBtn>
    </template>
  </SlidePanel>
</template>

<style scoped>
.notification-body {
  display: flex;
  justify-content: center;
  padding: 12px;
}
</style>
