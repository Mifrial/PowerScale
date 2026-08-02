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
        <input v-model.number="diceCount" type="number" class="roll-inline-num" min="1" max="30" />
        <span>d</span>
        <input v-model.number="dieFaces" type="number" class="roll-inline-num" min="2" max="100" />
        <span> при </span>
        <input v-model.number="efficiency" type="number" class="roll-inline-num" min="1" max="6" />
        <span> эффективности с </span>
        <input v-model.number="adv" type="number" class="roll-inline-num" min="-10" max="10" />
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
        <input v-model.number="dieSize" type="number" class="roll-inline-num" min="0" max="10" />
        <input v-model="label" placeholder="метка" class="roll-inline-text" />
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec'

defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  add: [spec: DiceRollSpec]
}>()

const diceCount = ref(5)
const dieSize = ref(0)
const dieFaces = ref(6)
const efficiency = ref(3)
const adv = ref(0)
const label = ref('')
const showAdvanced = ref(false)

const valid = computed(() => diceCount.value > 0)

function toggleAdvanced() {
  showAdvanced.value = !showAdvanced.value
}

function add() {
  if (!valid.value) return
  emit('add', {
    diceCount: diceCount.value,
    dieSize: dieSize.value,
    dieFaces: dieFaces.value,
    efficiency: efficiency.value,
    adv: adv.value,
    label: label.value || undefined,
  })
  reset()
}

function reset() {
  diceCount.value = 5
  dieSize.value = 0
  dieFaces.value = 6
  efficiency.value = 3
  adv.value = 0
  label.value = ''
  showAdvanced.value = false
}

function close() {
  emit('update:modelValue', false)
}
</script>

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
  width: 34px;
  text-align: center;
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  border-radius: 4px;
  padding: 2px 2px;
  font-size: 14px;
  background: transparent;
  color: inherit;
  outline: none;
  -moz-appearance: textfield;
}
.roll-inline-num::-webkit-inner-spin-button,
.roll-inline-num::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.roll-inline-num:focus {
  border-color: rgb(var(--v-theme-primary));
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
