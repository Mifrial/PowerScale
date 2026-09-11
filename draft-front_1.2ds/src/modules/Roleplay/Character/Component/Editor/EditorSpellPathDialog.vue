<script setup lang="ts">
import { ref, watch } from 'vue';
import LightButton from '@/modules/Core/UI/Component/light/LightButton.vue';
import type { SpellPathOption } from '@/modules/Roleplay/Character/Dto/Editor/SpellPathOption';

const props = defineProps<{
  options: SpellPathOption[];
}>();

const open = defineModel<boolean>({ default: false });

const emit = defineEmits<{
  select: [name: string, code: string | null];
}>();

const selectedCode = ref<string | null>(null);

function cheapestCode(): string | null {
  if (props.options.length === 0) return null;
  const cheapest = [...props.options].sort(
    (left, right) => left.cost - right.cost || left.name.localeCompare(right.name),
  )[0];

  return cheapest.code;
}

watch(open, (value) => {
  if (value) selectedCode.value = cheapestCode();
});

function onCancel(): void {
  open.value = false;
}

function onConfirm(): void {
  const option = props.options.find((entry) => entry.code === selectedCode.value) ?? props.options[0];
  if (!option) return;
  emit('select', option.name, option.code);
  open.value = false;
}

function optionLabel(option: SpellPathOption): string {
  return `${option.name} · ${option.cost} ОР`;
}
</script>

<template>
  <v-dialog v-model="open" max-width="420">
    <v-card rounded="lg">
      <v-card-title class="text-h6 pa-4 pb-2">Путь изучения</v-card-title>
      <v-card-subtitle class="px-4 pb-2">Выберите путь, в рамках которого изучается заклинание.</v-card-subtitle>
      <v-card-text>
        <v-radio-group v-model="selectedCode" hide-details>
          <v-radio v-for="option in options" :key="option.code" :label="optionLabel(option)" :value="option.code" />
        </v-radio-group>
      </v-card-text>
      <v-card-actions class="pa-4 pt-0">
        <v-spacer />
        <LightButton @click="onCancel">Отмена</LightButton>
        <LightButton :disabled="!selectedCode" @click="onConfirm">Изучить</LightButton>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
