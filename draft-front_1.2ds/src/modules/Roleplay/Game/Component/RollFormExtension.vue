<script setup lang="ts">
import { ref } from 'vue';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { ChatToolbarContext } from '@/modules/Messages/Chat/Interface/IChatToolbarExtension';
import DiceRollForm from '@/modules/Roleplay/Game/Component/DiceRollForm.vue';

const props = defineProps<ChatToolbarContext>();

const showForm = ref(false);

function add(spec: DiceRollSpec) {
  props.addRoll(spec);
  showForm.value = false;
}
</script>

<template>
  <div class="roll-form-extension">
    <div class="roll-form-toggle">
      <v-btn icon variant="tonal" size="x-small" :disabled="disabled" @click="showForm = !showForm">
        <v-icon size="16">mdi-dice-d6-outline</v-icon>
      </v-btn>
    </div>
    <DiceRollForm v-model="showForm" @add="add" />
  </div>
</template>

<style scoped>
.roll-form-extension {
  padding: 4px 0;
}
.roll-form-toggle {
  display: inline-flex;
}
</style>
