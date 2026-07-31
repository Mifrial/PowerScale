<template>
  <div class="chat-roll">
    <div class="chat-roll-header">
      <v-icon icon="mdi-dice-d6" size="16" class="mr-1" />
      {{ roll.spec.label || `Бросок ${index + 1}` }}
      <span class="ml-2 font-weight-medium">{{ roll.spec.diceCount }}к{{ roll.spec.dieFaces }}</span>
      <span v-if="roll.spec.efficiency" class="ml-1">сл:{{ roll.spec.efficiency }}</span>
      <span v-if="roll.spec.adv" class="ml-1" :class="roll.spec.adv > 0 ? 'text-success' : 'text-error'">{{ roll.spec.adv > 0 ? '+' : '' }}{{ roll.spec.adv }}</span>
      <span v-if="roll.spec.modifier" class="ml-1 text-info">мод:{{ roll.spec.modifier > 0 ? '+' : '' }}{{ roll.spec.modifier }}</span>
      <span v-if="roll.spec.dieSize" class="ml-1">рзмер:{{ roll.spec.dieSize }}</span>
    </div>
    <div class="chat-roll-detail">
      <span
        v-for="(s, si) in roll.successes"
        :key="si"
        class="roll-die"
        :class="{ good: s > 0, bad: s < 0 }"
      >
        {{ roll.adjustedRolls[si] }}
      </span>
    </div>
    <div class="chat-roll-total">
      Итого: <strong>{{ roll.totalSuccesses > 0 ? '+' : '' }}{{ roll.totalSuccesses }}</strong> успехов
    </div>
  </div>
</template>

<script setup lang="ts">
import type { DiceRollResult } from '@/modules/Messages/Chat/Interface/types'

defineProps<{ roll: DiceRollResult; index: number }>()
</script>

<style scoped>
.chat-roll {
  background: rgba(var(--v-theme-primaryLight), 0.6);
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  border-radius: 8px;
  padding: 8px 12px;
  margin-top: 6px;
  display: inline-block;
  text-align: left;
  max-width: 100%;
}

.chat-roll-header {
  font-size: 12px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 2px;
}

.chat-roll-detail {
  display: flex;
  gap: 4px;
  margin: 6px 0;
  flex-wrap: wrap;
}

.roll-die {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 600;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}
.roll-die.good {
  background: rgba(var(--v-theme-success), 0.1);
  border-color: rgb(var(--v-theme-success));
  color: rgb(var(--v-theme-success));
}
.roll-die.bad {
  background: rgba(var(--v-theme-error), 0.1);
  border-color: rgb(var(--v-theme-error));
  color: rgb(var(--v-theme-error));
}

.chat-roll-total {
  font-size: 12px;
  margin-top: 2px;
}
</style>
