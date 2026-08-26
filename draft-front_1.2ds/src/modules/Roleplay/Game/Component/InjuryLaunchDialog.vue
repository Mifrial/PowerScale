<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';

import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';

import { injuryRollService } from '@/modules/Roleplay/Game/Service/Instance/injuryRollService';

import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';

const props = defineProps<{
  open: boolean;
  gameId: number;
  chatId: number | null;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  mechanics: Mechanic[];
  targetKey: CombatEntityKey | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  'overlay-changed': [];
}>();

const sendChat = combatChatSendService.sendCombatChat(props.gameId);
const difficulty = ref(1);
const attackSr = ref(0);
const adv = ref(0);
const damageTypeCode = ref<string | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);

const damageTypeOptions = computed(() =>
  props.rules.filter((rule) => rule.type === 'damage_type').map((rule) => ({ title: rule.name, value: rule.code })),
);

const typeAddsSrDice = computed(() =>
  damageTypeHooksService
    .injuryHooksOf(damageTypeHooksService.resolveDamageTypeHooks(damageTypeCode.value, props.rules, props.mechanics))
    .some((hook) => hook.extraDiceFromSrDivisor),
);

watch(
  () => [props.open, props.targetKey] as const,
  async ([open]) => {
    if (!open) return;
    error.value = null;
    difficulty.value = 1;
    attackSr.value = 0;
    adv.value = 0;
    damageTypeCode.value = null;
  },
);

function speakerFor(key: CombatEntityKey | null): ChatSpeaker {
  if (!key) return { kind: 'gm' };
  if (key.startsWith('npc:')) {
    const id = Number(key.slice(4));
    const npc = props.npcs.find((item) => item.id === id);

    return { kind: 'npc', npcId: id, npcName: npc?.name ?? 'НПС' };
  }
  const id = Number(key.slice(10));
  const membership = props.characters.find((item) => item.characterId === id);

  return { kind: 'character', characterId: id, characterName: membership?.characterName ?? 'Персонаж' };
}

async function submit(): Promise<void> {
  if (!props.targetKey || props.chatId === null) return;
  busy.value = true;
  error.value = null;
  try {
    const applied = await injuryCheckService.applyInjuryCheck({
      input: {
        leftoverDamage: 0,
        woundStrength: 0,
        difficulty: difficulty.value,
        endurance: 1,
        exhaustion: 0,
        attackSr: attackSr.value,
        damageTypeCode: damageTypeCode.value,
        advantages: injuryRollService.manualInjuryAdvantages(adv.value),
        actorKey: props.targetKey,
      },
      rules: props.rules,
      mechanics: props.mechanics,
      gameId: props.gameId,
      targetKey: props.targetKey,
      targetName: combatCardModelService.combatEntityName(props.targetKey, props.characters, props.npcs),
      chatId: props.chatId,
      speaker: speakerFor(props.targetKey),
      sendMessage: (content, attachments, chatId, speaker) => sendChat(content, attachments, chatId, speaker),
    });
    if (applied.overlay) emit('overlay-changed');
    emit('update:open', false);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось бросить увечье';
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <v-dialog :model-value="open" max-width="480" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title>Проверка на увечье</v-card-title>
      <v-card-text>
        <div class="text-body-2 mb-3">
          Цель:
          <strong>{{ targetKey ? combatCardModelService.combatEntityName(targetKey, characters, npcs) : '—' }}</strong>
        </div>
        <div class="d-flex ga-3 mb-3">
          <ClampedNumberField v-model="difficulty" label="Сложность" :min="0" :max="40" class="flex-grow-1" />
          <ClampedNumberField
            v-model="adv"
            label="Преимущества / помехи"
            :min="-ROLL_ADV_MAX"
            :max="ROLL_ADV_MAX"
            class="flex-grow-1"
          />
        </div>
        <v-autocomplete
          v-model="damageTypeCode"
          :items="damageTypeOptions"
          label="Тип урона (необязательно)"
          auto-select-first
          clearable
          hide-details="auto"
          class="mb-3"
        />
        <ClampedNumberField v-if="typeAddsSrDice" v-model="attackSr" label="РУ атаки" :min="0" :max="20" class="mb-3" />
        <v-alert v-if="error" type="error" variant="tonal" class="mt-3" density="compact">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="emit('update:open', false)">Отмена</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="!targetKey || chatId === null" @click="submit">Бросить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
