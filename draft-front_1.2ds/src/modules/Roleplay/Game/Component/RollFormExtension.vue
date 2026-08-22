<script setup lang="ts">
import { ref } from 'vue';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { ChatToolbarContext } from '@/modules/Messages/Chat/Dto/ChatToolbarContext';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import DiceRollForm from '@/modules/Roleplay/Game/Component/DiceRollForm.vue';

const props = defineProps<ChatToolbarContext>();

const showForm = ref(false);

function add(spec: DiceRollSpec) {
  props.addAttachment({ type: ROLL_ATTACHMENT_TYPE, payload: spec });
  showForm.value = false;
}
</script>

<template>
  <v-menu v-model="showForm" :close-on-content-click="false" location="top end">
    <template #activator="{ props: menuProps }">
      <v-btn v-bind="menuProps" icon variant="text" size="x-small" :disabled="disabled" aria-label="Бросок кубиков">
        <v-icon>mdi-dice-d6-outline</v-icon>
      </v-btn>
    </template>
    <v-card min-width="460" max-width="540" elevation="8" border>
      <v-card-text class="pa-2">
        <DiceRollForm v-model="showForm" @add="add" />
      </v-card-text>
    </v-card>
  </v-menu>
</template>
