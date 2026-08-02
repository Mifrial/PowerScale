<template>
  <div class="chat-roll">
    <div class="chat-roll-header">
      <v-icon icon="mdi-dice-d6" size="16" class="mr-1" />
      {{ roll.spec.label || `Бросок ${index + 1}` }}
      <span class="ml-2 font-weight-medium">{{ roll.spec.diceCount }}к{{ roll.spec.dieFaces }}</span>
      <span v-if="roll.spec.efficiency" class="ml-1">сл:{{ roll.spec.efficiency }}</span>
      <span v-if="roll.spec.adv" class="ml-1" :class="roll.spec.adv > 0 ? 'text-success' : 'text-error'">{{ roll.spec.adv > 0 ? '+' : '' }}{{ roll.spec.adv }}</span>
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
      <span
        v-for="(d, di) in roll.droppedRolls"
        :key="`drop-${di}`"
        class="roll-die roll-die-dropped"
        :title="roll.spec.adv > 0 ? 'убрано как худшее' : 'убрано как лучшее'"
      >
        {{ d }}
      </span>
    </div>
    <div v-if="roll.droppedRolls.length" class="chat-roll-note">
      <template v-if="roll.spec.adv > 0">убрано {{ roll.droppedRolls.length }} худш{{ roll.droppedRolls.length === 1 ? 'ий' : 'их' }}</template>
      <template v-else>убрано {{ roll.droppedRolls.length }} лучш{{ roll.droppedRolls.length === 1 ? 'ий' : 'их' }}</template>
    </div>
    <div class="chat-roll-total">
      Итого: <strong>{{ roll.totalSuccesses > 0 ? '+' : '' }}{{ roll.totalSuccesses }}{{ sizeSuffix }}</strong> успехов
    </div>
  </div>
</template>

<script setup lang="ts">
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult'
import { rollService } from '@/modules/Roleplay/Game/Service/RollService'

const props = defineProps<{ roll: DiceRollResult; index: number }>()

const sizeSuffix = rollService.formatRollSize(props.roll.spec.dieSize || 0)
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

.roll-die-dropped {
  opacity: 0.45;
  text-decoration: line-through;
  border-style: dashed;
}

.chat-roll-note {
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  margin-top: -2px;
}

.chat-roll-total {
  font-size: 12px;
  margin-top: 2px;
}
</style>
