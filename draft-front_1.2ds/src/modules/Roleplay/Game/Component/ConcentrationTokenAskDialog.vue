<script setup lang="ts">
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

defineProps<{
  open: boolean;
  maxSpend: number;
  remaining: number;
  amount: number;
}>();

const emit = defineEmits<{
  'update:amount': [value: number];
  confirm: [];
  skip: [];
}>();
</script>

<template>
  <v-dialog :model-value="open" max-width="420" persistent>
    <v-card>
      <v-card-title class="text-body-1">Проверка на истощение</v-card-title>
      <v-card-text>
        <div class="text-body-2 mb-3">Потратить жетоны концентрации на бросок Силы воли?</div>
        <ClampedNumberField
          :model-value="amount"
          :min="0"
          :max="maxSpend"
          label="Жетоны концентрации"
          :hint="`Осталось: ${remaining}`"
          persistent-hint
          density="compact"
          hide-details="auto"
          @update:model-value="emit('update:amount', $event)"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="emit('skip')">Без жетонов</v-btn>
        <v-btn color="primary" @click="emit('confirm')">Бросить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
