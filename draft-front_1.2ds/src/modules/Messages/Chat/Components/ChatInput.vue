<template>
  <div class="chat-input-area border-t">
    <div v-if="!disabled && pendingRolls.length" class="roll-bar">
      <div v-for="(r, ri) in pendingRolls" :key="ri" class="roll-chip">
        <v-icon icon="mdi-dice-d6" size="14" />
        {{ r.diceCount }}к{{ r.dieFaces }}{{ r.adv ? (r.adv > 0 ? ` +${r.adv}` : ` ${r.adv}`) : '' }}{{ r.modifier ? (r.modifier > 0 ? ` +${r.modifier}` : ` ${r.modifier}`) : '' }}{{ r.label ? ` (${r.label})` : '' }}
        <v-icon icon="mdi-close" size="14" class="ml-1 roll-chip-remove" @click="removeRoll(ri)" />
      </div>
    </div>

    <DiceRollForm v-if="!disabled" v-model="showRollForm" @add="addRoll" />

    <div class="chat-input-wrapper">
      <v-textarea
        v-model="messageText"
        placeholder="Напишите сообщение..."
        hide-details
        auto-grow
        rows="3"
        max-rows="6"
        variant="outlined"
        class="chat-input"
        :disabled="disabled"
        @keydown.enter.exact.prevent="handleSend"
      />
      <div v-if="!disabled" class="chat-input-actions">
        <v-btn icon variant="tonal" size="x-small" @click="showRollForm = !showRollForm">
          <v-icon size="16">mdi-dice-d6-outline</v-icon>
        </v-btn>
        <v-btn
          icon
          variant="tonal"
          size="x-small"
          :loading="sending"
          :disabled="!canSend"
          aria-label="Отправить"
          @click="handleSend"
        >
          <v-icon>mdi-send</v-icon>
        </v-btn>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { DiceRollSpec } from '@/modules/Messages/Chat/Interface/types'
import DiceRollForm from './DiceRollForm.vue'

const props = defineProps<{
  sending: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  send: [text: string, rolls: DiceRollSpec[]]
}>()

const messageText = ref('')
const showRollForm = ref(false)
const pendingRolls = ref<DiceRollSpec[]>([])

const canSend = computed(() => messageText.value.trim().length > 0 || pendingRolls.value.length > 0)

function addRoll(spec: DiceRollSpec) {
  pendingRolls.value.push(spec)
  showRollForm.value = false
}

function removeRoll(index: number) {
  pendingRolls.value.splice(index, 1)
}

function handleSend() {
  if (!canSend.value) return
  emit('send', messageText.value.trim(), [...pendingRolls.value])
  messageText.value = ''
  pendingRolls.value = []
  showRollForm.value = false
}
</script>

<style scoped>
.chat-input-area {
  flex-shrink: 0;
  padding: 8px 12px 12px;
}

.roll-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 4px 0;
}

.roll-chip {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgb(var(--v-theme-primaryLight));
  border-radius: 16px;
  padding: 2px 10px;
  font-size: 12px;
}

.roll-chip-remove {
  cursor: pointer;
  opacity: 0.5;
}
.roll-chip-remove:hover {
  opacity: 1;
}

.chat-input-wrapper {
  position: relative;
}
.chat-input-actions {
  position: absolute;
  bottom: 4px;
  right: 4px;
  left: auto;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}
.chat-input-actions :deep(.v-btn:hover) {
  background: rgba(var(--v-theme-primary), 0.15);
  color: rgb(var(--v-theme-primary));
}
.chat-input :deep(.v-field__input) {
  padding-right: 44px;
}
.chat-input {
  flex: 1;
}

.text-disabled {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
}
</style>
