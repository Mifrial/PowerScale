<script setup lang="ts">
import { ref, computed } from 'vue';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { ROLL_DICE_COUNT_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MAX';
import { ROLL_DICE_COUNT_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MIN';
import { ROLL_DIE_FACES_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DIE_FACES_MAX';
import { ROLL_DIE_FACES_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DIE_FACES_MIN';
import { ROLL_DIE_SIZE_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DIE_SIZE_MAX';
import { ROLL_EFFICIENCY_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MAX';
import { ROLL_EFFICIENCY_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MIN';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  add: [spec: DiceRollSpec];
}>();

const diceCount = ref(5);
const dieSize = ref(0);
const dieFaces = ref(6);
const efficiency = ref(3);
const adv = ref(0);
const label = ref('');
const showAdvanced = ref(false);

const valid = computed(() => diceCount.value > 0);

function toggleAdvanced() {
  showAdvanced.value = !showAdvanced.value;
}

function add() {
  if (!valid.value) return;
  emit('add', {
    diceCount: diceCount.value,
    dieSize: dieSize.value,
    dieFaces: dieFaces.value,
    efficiency: efficiency.value,
    advantages: aggregateSourceDeltasService.advantageEntries(adv.value),
    label: label.value || undefined,
  });
  reset();
}

function reset() {
  diceCount.value = 5;
  dieSize.value = 0;
  dieFaces.value = 6;
  efficiency.value = 3;
  adv.value = 0;
  label.value = '';
  showAdvanced.value = false;
}

function close() {
  emit('update:modelValue', false);
}
</script>

<template>
  <Transition name="roll-form">
    <div v-if="modelValue" class="roll-form">
      <div class="roll-form-header">
        <span class="text-caption font-weight-medium">Бросок</span>
        <v-btn icon variant="text" size="x-small" @click="close">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>
      <div class="roll-inline">
        <ClampedNumberField
          v-model="diceCount"
          :min="ROLL_DICE_COUNT_MIN"
          :max="ROLL_DICE_COUNT_MAX"
          density="compact"
          hide-details
          class="roll-inline-num"
        />
        <span>d</span>
        <ClampedNumberField
          v-model="dieFaces"
          :min="ROLL_DIE_FACES_MIN"
          :max="ROLL_DIE_FACES_MAX"
          density="compact"
          hide-details
          class="roll-inline-num"
        />
        <span> при </span>
        <ClampedNumberField
          v-model="efficiency"
          :min="ROLL_EFFICIENCY_MIN"
          :max="ROLL_EFFICIENCY_MAX"
          density="compact"
          hide-details
          class="roll-inline-num"
        />
        <span> эффективности с </span>
        <ClampedNumberField
          v-model="adv"
          :min="-ROLL_ADV_MAX"
          :max="ROLL_ADV_MAX"
          density="compact"
          hide-details
          class="roll-inline-num"
        />
        <span> преимуществами</span>
        <div class="ml-2 d-flex ga-1">
          <v-btn icon variant="text" size="x-small" :disabled="!valid" @click="add">
            <v-icon>mdi-check</v-icon>
          </v-btn>
          <v-btn icon variant="text" size="x-small" @click="toggleAdvanced">
            <v-icon>{{ showAdvanced ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</v-icon>
          </v-btn>
        </div>
      </div>
      <div v-if="showAdvanced" class="roll-advanced-row">
        <span class="text-caption">Размерность</span>
        <ClampedNumberField
          v-model="dieSize"
          :min="-ROLL_DIE_SIZE_MAX"
          :max="ROLL_DIE_SIZE_MAX"
          density="compact"
          hide-details
          class="roll-inline-num"
        />
        <input v-model="label" placeholder="метка" class="roll-inline-text" />
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.roll-form {
  padding: 6px 0;
}

.roll-form-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}

.roll-inline {
  display: flex;
  align-items: center;
  gap: 3px;
  flex-wrap: wrap;
  font-size: 14px;
  line-height: 1.4;
}

.roll-inline-num {
  width: 76px;
  flex-shrink: 0;
}
.roll-inline-num :deep(.v-field__input) {
  text-align: center;
}

.roll-inline-text {
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 13px;
  background: transparent;
  color: inherit;
  outline: none;
  width: 120px;
}
.roll-inline-text:focus {
  border-color: rgb(var(--v-theme-primary));
}

.roll-advanced-row {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
  flex-wrap: wrap;
}
</style>
