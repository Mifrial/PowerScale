<script setup lang="ts">
import { computed, ref } from 'vue';
import type { SpellCastUpgradeOption } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastUpgradeOption';
import { spellCastUpgradeService } from '@/modules/Roleplay/Game/Service/Instance/spellCastUpgradeService';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

const props = defineProps<{
  options: SpellCastUpgradeOption[];
  modelValue: string[];
  parameterValues: Record<string, number>;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string[]];
  'update:parameterValues': [value: Record<string, number>];
}>();

const open = ref(false);
const selected = computed(() => spellCastUpgradeService.selectedOf(props.options, props.modelValue));

function isOn(code: string): boolean {
  return props.modelValue.includes(code);
}

function toggle(code: string, on: boolean | null): void {
  const next = props.modelValue.filter((item) => item !== code);
  if (on) {
    next.push(code);
  }
  emit('update:modelValue', next);
}

function parameterValue(code: string): number {
  return Math.max(0, Math.floor(props.parameterValues[code] ?? 1));
}

function setParameterValue(code: string, value: number | null): void {
  emit('update:parameterValues', {
    ...props.parameterValues,
    [code]: parameterValueFromInput(value),
  });
}

function parameterValueFromInput(value: number | null): number {
  return Math.max(0, Math.floor(value ?? 0));
}

function remove(code: string): void {
  emit(
    'update:modelValue',
    props.modelValue.filter((item) => item !== code),
  );
}

function chipLabel(option: SpellCastUpgradeOption): string {
  return spellCastUpgradeService.chipLabel(option, parameterValue(option.ruleCode));
}
</script>

<template>
  <div>
    <div class="d-flex align-start ga-1">
      <div class="spell-cast-upgrade-list__field">
        <slot />
      </div>
      <v-btn
        v-if="options.length"
        icon
        variant="text"
        size="small"
        class="mt-1"
        :title="selected.length ? `Модификаторы каста: ${selected.length}` : 'Модифицировать заклинание'"
        @click="open = true"
      >
        <v-badge :content="selected.length || undefined" :model-value="selected.length > 0" color="primary">
          <v-icon size="22">mdi-plus-box-outline</v-icon>
        </v-badge>
      </v-btn>
    </div>
    <div v-if="selected.length" class="d-flex flex-wrap ga-1 mt-2">
      <v-chip
        v-for="option in selected"
        :key="option.ruleCode"
        size="small"
        closable
        @click:close="remove(option.ruleCode)"
      >
        {{ chipLabel(option) }}
      </v-chip>
    </div>
    <v-dialog v-model="open" max-width="440">
      <v-card>
        <v-card-title class="text-body-1">Модификаторы каста</v-card-title>
        <v-card-text>
          <template v-for="option in options" :key="option.ruleCode">
            <v-checkbox
              :model-value="isOn(option.ruleCode)"
              :label="chipLabel(option)"
              hide-details
              density="compact"
              @update:model-value="toggle(option.ruleCode, $event)"
            />
            <ClampedNumberField
              v-if="option.upgrade.resistance_penetration_per_step && isOn(option.ruleCode)"
              :model-value="parameterValue(option.ruleCode)"
              label="х"
              :min="0"
              :max="999"
              density="compact"
              hide-details
              class="mb-2 ms-8"
              @update:model-value="setParameterValue(option.ruleCode, $event)"
            />
          </template>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="primary" variant="text" @click="open = false">Готово</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.spell-cast-upgrade-list__field {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
