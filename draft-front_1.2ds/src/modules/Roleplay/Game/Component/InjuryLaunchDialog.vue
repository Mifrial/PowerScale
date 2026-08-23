<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { combatCardModel, combatEntityName, combatExhaustion } from '@/modules/Roleplay/Game/Utils/combatCardModel';
import { injuryPoolSize, manualInjuryAdvantages, rollInjury } from '@/modules/Roleplay/Game/Utils/injuryRoll';
import { formatInjuryCheckMessage } from '@/modules/Roleplay/Game/Utils/injuryCheckMessage';
import { injuryHooksOf, resolveDamageTypeHooks } from '@/modules/Roleplay/Game/Utils/resolveDamageTypeHooks';
import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';

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
}>();

const chatStore = useChatStore();
const overlays = ref<GameCombatOverlay[]>([]);
const damage = ref(0);
const woundStrength = ref(0);
const attackSr = ref(0);
const adv = ref(0);
const damageTypeCode = ref<string | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);

const damageTypeOptions = computed(() =>
  props.rules.filter((rule) => rule.type === 'damage_type').map((rule) => ({ title: rule.name, value: rule.code })),
);

const overlay = computed(() => overlays.value.find((item) => item.entityKey === props.targetKey) ?? null);

const model = computed(() => {
  if (!props.targetKey) return null;

  return combatCardModel(props.targetKey, props.characters, props.npcs, true, null, overlay.value);
});

const overview = computed(() => {
  const version = model.value?.effectiveVersion;
  if (!version) return null;

  return characterOverviewService.build(version, props.rules);
});

const endurance = computed(() => {
  const current = overview.value;
  if (!current) return 1;
  for (const characteristic of current.characteristics) {
    const rule = props.rules.find((entry) => entry.id === characteristic.ruleId);
    if (rule?.code === 'endurance') return Math.max(1, characteristic.value.base);
  }

  return 1;
});

const exhaustion = computed(() => {
  const version = model.value?.effectiveVersion;
  if (!version) return 0;

  return combatExhaustion(version.states, props.rules) ?? 0;
});

const procedure = computed(() => resolveInjuryProcedure(props.rules, props.mechanics));
const extraDice = computed(() => {
  const hooks = injuryHooksOf(resolveDamageTypeHooks(damageTypeCode.value, props.rules, props.mechanics));
  let extra = 0;
  for (const hook of hooks) {
    if (hook.extraDiceFromSrDivisor) extra += Math.floor(Math.max(0, attackSr.value) / hook.extraDiceFromSrDivisor);
  }

  return extra;
});
const poolPreview = computed(() =>
  injuryPoolSize(
    {
      damage: damage.value,
      woundStrength: woundStrength.value,
      endurance: endurance.value,
      exhaustion: exhaustion.value,
      attackSr: attackSr.value,
    },
    procedure.value,
    extraDice.value,
  ),
);
const needsAttackSr = computed(() => extraDice.value > 0 || attackSr.value > 0);

watch(
  () => [props.open, props.targetKey] as const,
  async ([open]) => {
    if (!open) return;
    error.value = null;
    damage.value = 0;
    woundStrength.value = 0;
    attackSr.value = 0;
    adv.value = 0;
    damageTypeCode.value = null;
    overlays.value = await getGameApi().getCombatOverlays(props.gameId);
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
    const result = rollInjury(
      {
        damage: damage.value,
        woundStrength: woundStrength.value,
        endurance: endurance.value,
        exhaustion: exhaustion.value,
        attackSr: attackSr.value,
        damageTypeCode: damageTypeCode.value,
        advantages: manualInjuryAdvantages(adv.value),
        actorKey: props.targetKey,
      },
      Math.random,
      props.rules,
      props.mechanics,
    );
    const injury = result.injury;
    if (!injury) throw new Error('Нет исхода увечья');
    const name = combatEntityName(props.targetKey, props.characters, props.npcs);
    await chatStore.sendMessage(
      formatInjuryCheckMessage({
        targetKey: props.targetKey,
        targetName: name,
        damageTypeCode: damageTypeCode.value,
        rules: props.rules,
        injury,
      }),
      [{ type: ROLL_ATTACHMENT_TYPE, payload: result }],
      props.chatId,
      speakerFor(props.targetKey),
    );
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
          Цель: <strong>{{ targetKey ? combatEntityName(targetKey, characters, npcs) : '—' }}</strong> · Стойкость
          {{ endurance }}
          <span v-if="exhaustion >= 6"> · Истощение {{ exhaustion }}</span>
        </div>
        <ClampedNumberField v-model="damage" label="Повреждения" :min="0" :max="99" class="mb-3" />
        <ClampedNumberField v-model="woundStrength" label="Сила раны" :min="0" :max="20" class="mb-3" />
        <v-select
          v-model="damageTypeCode"
          :items="damageTypeOptions"
          label="Тип урона (необязательно)"
          clearable
          hide-details="auto"
          class="mb-3"
        />
        <ClampedNumberField
          v-if="needsAttackSr || damageTypeCode"
          v-model="attackSr"
          label="РУ атаки"
          :min="0"
          :max="20"
          class="mb-3"
        />
        <ClampedNumberField
          v-model="adv"
          label="Преимущества / помехи"
          :min="-ROLL_ADV_MAX"
          :max="ROLL_ADV_MAX"
          class="mb-3"
        />
        <div class="text-body-2 text-medium-emphasis">Кубов: {{ poolPreview }}</div>
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
