<script setup lang="ts">
import { ref, computed } from 'vue';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { getCommandHandlers, getToolbarExtensions } from '@/modules/Messages/Chat/init';

const props = defineProps<{
  sending: boolean;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  send: [text: string, rolls: DiceRollSpec[]];
}>();

const messageText = ref('');
const pendingRolls = ref<DiceRollSpec[]>([]);

const toolbarExtensions = getToolbarExtensions();

const toolbarContext = computed(() => ({
  pendingRolls: pendingRolls.value,
  addRoll,
  removeRoll,
  send: (text: string, rolls: DiceRollSpec[]) => emit('send', text, rolls),
  disabled: props.disabled ?? false,
}));

const canSend = computed(() => messageText.value.trim().length > 0 || pendingRolls.value.length > 0);

function addRoll(spec: DiceRollSpec) {
  pendingRolls.value.push(spec);
}

function removeRoll(index: number) {
  pendingRolls.value.splice(index, 1);
}

function rollSummary(r: DiceRollSpec): string {
  const adv = r.adv ? (r.adv > 0 ? ` +${r.adv}` : ` ${r.adv}`) : '';
  const label = r.label ? ` (${r.label})` : '';

  return `${r.diceCount}к${r.dieFaces}${adv}${label}`;
}

function handleSend() {
  if (!canSend.value) return;
  const text = messageText.value.trim();
  let rolls = pendingRolls.value;
  if (text.startsWith('/')) {
    for (const handler of getCommandHandlers()) {
      const parsed = handler.parse(text);
      if (parsed) {
        messageText.value = '';
        pendingRolls.value = [];
        emit('send', parsed.content, parsed.rolls);

        return;
      }
    }
  }
  emit('send', text, rolls);
  messageText.value = '';
  pendingRolls.value = [];
}
</script>

<template>
  <div class="chat-input-area border-t">
    <template v-for="ext in toolbarExtensions" :key="ext.id">
      <component :is="ext.component" v-bind="toolbarContext" />
    </template>

    <div v-if="!disabled && pendingRolls.length" class="roll-bar">
      <div v-for="(r, ri) in pendingRolls" :key="ri" class="roll-chip">
        <v-icon icon="mdi-dice-d6" size="14" />
        {{ rollSummary(r) }}
        <v-icon icon="mdi-close" size="14" class="ml-1 roll-chip-remove" @click="removeRoll(ri)" />
      </div>
    </div>

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
