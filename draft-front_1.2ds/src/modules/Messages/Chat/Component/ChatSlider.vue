<template>
  <SlidePanel v-model="open" width="calc(100vw - 120px)" max-width="1100px" body-flex>
    <template #header>
      <MessengerTabs />
    </template>
    <Messenger>
      <template #tabs><!-- tabs rendered in header --></template>
    </Messenger>
  </SlidePanel>
</template>

<script setup lang="ts">
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue'
import Messenger from '@/modules/Messages/Chat/Component/Messenger.vue'
import MessengerTabs from '@/modules/Messages/Chat/Component/MessengerTabs.vue'

const open = defineModel<boolean>({ default: false })
</script>
