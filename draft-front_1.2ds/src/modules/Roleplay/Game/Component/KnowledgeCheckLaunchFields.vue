<script setup lang="ts">
import { computed } from 'vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import LevelTintSelect from '@/modules/Core/UI/Component/Input/LevelTintSelect.vue';
import { knowledgeCheckLaunchService } from '@/modules/Roleplay/Game/Service/Instance/knowledgeCheckLaunchService';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  fieldCode: string;
  slotText: string;
  band: number;
  rules: Rule[];
  abilities: CharacterAbility[];
  hintText: string | null;
}>();

const emit = defineEmits<{
  'update:fieldCode': [value: string];
  'update:slotText': [value: string];
  'update:band': [value: number];
}>();

const fieldItems = computed(() => knowledgeCheckLaunchService.fieldSelectItems(props.abilities));
const hasSlotDictionary = computed(() => knowledgeCheckLaunchService.hasSlotDictionary(props.fieldCode, props.rules));
const slotItems = computed(() =>
  knowledgeCheckLaunchService.slotSelectItems(props.abilities, props.fieldCode, props.rules),
);
const slotLabel = computed(() => knowledgeCheckLaunchService.slotLabel(props.fieldCode));
const slotChoice = computed(() =>
  knowledgeCheckLaunchService.slotChoiceOf(props.fieldCode, props.slotText, props.rules),
);
const showCustomSlot = computed(
  () => !hasSlotDictionary.value || knowledgeCheckLaunchService.isOtherSlotChoice(slotChoice.value),
);
const customSlotText = computed(() => (showCustomSlot.value ? props.slotText : ''));

function onFieldCode(value: string): void {
  emit('update:fieldCode', value);
  emit('update:slotText', '');
}

function onSlotChoice(value: string): void {
  emit('update:slotText', knowledgeCheckLaunchService.slotTextOfChoice(value, ''));
}

function onCustomSlot(value: string): void {
  emit('update:slotText', value);
}
</script>

<template>
  <div class="knowledge-check-fields">
    <LevelTintSelect
      class="knowledge-check-fields__grow"
      :model-value="fieldCode"
      :items="fieldItems"
      label="Тип знания"
      @update:model-value="onFieldCode"
    />
    <LevelTintSelect
      v-if="hasSlotDictionary"
      class="knowledge-check-fields__grow"
      :model-value="slotChoice"
      :items="slotItems"
      :label="slotLabel"
      @update:model-value="onSlotChoice"
    />
    <v-text-field
      v-if="showCustomSlot"
      class="knowledge-check-fields__grow"
      :model-value="customSlotText"
      density="compact"
      variant="outlined"
      hide-details
      :label="hasSlotDictionary ? 'Свой текст' : slotLabel"
      @update:model-value="onCustomSlot(String($event ?? ''))"
    />
    <ClampedNumberField
      class="knowledge-check-fields__band"
      :model-value="band"
      :min="1"
      :max="3"
      min-width="160px"
      density="compact"
      hide-details
      label="Полоса"
      @update:model-value="emit('update:band', $event)"
    />
    <div v-if="hintText" class="text-caption text-medium-emphasis knowledge-check-fields__hint">{{ hintText }}</div>
  </div>
</template>

<style scoped>
.knowledge-check-fields {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
  align-items: flex-start;
}
.knowledge-check-fields__grow {
  flex: 1 1 220px;
  min-width: 200px;
}
.knowledge-check-fields__band {
  flex: 0 0 180px;
  min-width: 180px;
}
.knowledge-check-fields__hint {
  flex: 1 1 100%;
}
</style>
