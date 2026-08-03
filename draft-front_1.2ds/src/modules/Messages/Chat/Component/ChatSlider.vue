<script setup lang="ts">
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import ChatMessenger from '@/modules/Messages/Chat/Component/ChatMessenger.vue';
import MessengerTabs from '@/modules/Messages/Chat/Component/MessengerTabs.vue';

const open = defineModel<boolean>({ default: false });
</script>

<template>
  <SlidePanel v-model="open" width="calc(100vw - 120px)" max-width="1100px" body-flex>
    <template #header>
      <MessengerTabs />
    </template>
    <ChatMessenger>
      <template #tabs><!-- tabs rendered in header --></template>
    </ChatMessenger>
  </SlidePanel>
</template>
