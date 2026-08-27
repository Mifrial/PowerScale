<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { actionOdCost, asActionAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';
import { formatAttackActionMessage } from '@/modules/Roleplay/Game/Utils/attackDamageMessage';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';

const props = defineProps<{
  open: boolean;
  gameId: number;
  chatId: number | null;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  mechanics: Mechanic[];
  canEdit: boolean;
  currentUserId: number | null;
  activeSpeakerKey: string | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  settled: [];
  'overlay-changed': [];
}>();

const overlays = ref<GameCombatOverlay[]>([]);
const pendingEffectsByEntity = ref<Record<CombatEntityKey, PendingActionEffect[]>>({});
const selectedRuleId = ref<string | null>(null);
const showReactions = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);
const sendChat = combatChatSendService.sendCombatChat(props.gameId);

const actorKey = computed<CombatEntityKey | null>(() => {
  const key = props.activeSpeakerKey;

  return key && key !== 'gm' ? (key as CombatEntityKey) : null;
});

const actorVersion = computed(() => {
  if (!actorKey.value) return null;
  const overlay = overlays.value.find((item) => item.entityKey === actorKey.value) ?? null;

  return combatCardModelService.combatCardModel(
    actorKey.value,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlay,
  ).effectiveVersion;
});

const actorOverview = computed(() =>
  actorVersion.value ? characterOverviewService.build(actorVersion.value, props.rules) : null,
);

const actions = computed<CombatActionOption[]>(() => {
  const owned = new Set(actorOverview.value?.abilities.map((ability) => ability.ruleId) ?? []);

  return props.rules.flatMap((rule) => {
    const spec = asActionAbilitySpec(rule);
    if (rule.keywordIds?.includes(71)) return [];
    if (!spec || (!owned.has(rule.id) && !Object.values(spec.zones).some((zone) => zone?.kind === 'automatic')))
      return [];
    const option: CombatActionOption = {
      ruleId: rule.id,
      code: rule.code,
      name: rule.name,
      odCost: actionOdCost(spec.action_components),
      effects: actionEffectService.effectsOf(rule),
      isAttack: false,
      isReaction: rule.keywordIds?.includes(53) ?? false,
    };

    return [option];
  });
});

const visibleActions = computed(() => actions.value.filter((action) => action.isReaction === showReactions.value));
const selectedAction = computed(() => actions.value.find((action) => action.ruleId === selectedRuleId.value) ?? null);
const actionPoints = computed(() => {
  if (!actorOverview.value) return 0;
  const resource = attackDamageService.actionPointsResource(actorOverview.value, props.rules);

  return resource?.current.base ?? 0;
});

function versionOf(key: CombatEntityKey): typeof actorVersion.value {
  const overlay = overlays.value.find((item) => item.entityKey === key) ?? null;

  return combatCardModelService.combatCardModel(
    key,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlay,
  ).effectiveVersion;
}

function speakerFor(key: CombatEntityKey): ChatSpeaker {
  if (key.startsWith('npc:')) {
    const id = Number(key.slice(4));
    const npc = props.npcs.find((item) => item.id === id);

    return { kind: 'npc', npcId: id, npcName: npc?.name ?? 'НПС' };
  }
  const id = Number(key.slice(10));
  const character = props.characters.find((item) => item.characterId === id);

  return { kind: 'character', characterId: id, characterName: character?.characterName ?? 'Персонаж' };
}

async function hydrate(): Promise<void> {
  error.value = null;
  const api = getGameApi();
  const [nextOverlays, nextPending] = await Promise.all([
    api.getCombatOverlays(props.gameId).catch(() => []),
    api.getPendingActionEffects(props.gameId).catch(() => ({})),
  ]);
  overlays.value = nextOverlays;
  pendingEffectsByEntity.value = nextPending;
  selectedRuleId.value = visibleActions.value[0]?.ruleId ?? null;
}

async function submit(): Promise<void> {
  const key = actorKey.value;
  const action = selectedAction.value;
  if (!key || !action) throw new Error('Выберите действие');
  if (action.odCost > actionPoints.value) throw new Error('Недостаточно ОД для действия');
  const version = versionOf(key);
  if (!version) throw new Error('Лист участника не найден');
  const resource = attackDamageService.actionPointsResource(
    characterOverviewService.build(version, props.rules),
    props.rules,
  );
  if (!resource) throw new Error('ОД не найдено');

  const pending = pendingEffectsByEntity.value[key] ?? [];
  const resolved = actionEffectService.resolveForNextAction(pending, {
    isAttack: false,
    component: 'strike',
    baseCost: action.odCost,
  });
  const spent = Math.min(action.odCost, resource.current.base);
  const next = attackDamageService.spendActionPoints(resource.current, spent);
  const overlay = await getGameApi().setCombatResource(props.gameId, key, resource.ruleId, next);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
  const effects = [
    ...actionEffectService.consumeResource(resolved.remainingEffects, 'action-points', spent),
    ...actionEffectService.effectsAfterAction(props.rules.find((rule) => rule.id === action.ruleId)),
  ];
  pendingEffectsByEntity.value = { ...pendingEffectsByEntity.value, [key]: effects };
  await getGameApi().setCombatActionEffects(props.gameId, key, effects);
  emit('overlay-changed');
  if (props.chatId !== null) {
    const speaker = speakerFor(key);
    const attackerName =
      speaker.kind === 'character' ? speaker.characterName : speaker.kind === 'npc' ? speaker.npcName : 'Персонаж';
    await sendChat(
      `${formatAttackActionMessage({
        attackerKey: key,
        attackerName,
        action,
        attackerAp: spent,
        rules: props.rules,
      })}${action.effects?.length ? `\nЭффекты: ${action.effects.map((effect) => actionEffectService.describe(effect)).join('; ')}` : ''}`,
      [],
      props.chatId,
      speaker,
    );
  }
}

async function submitSafe(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await submit();
    emit('settled');
    emit('update:open', false);
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось выполнить действие';
  } finally {
    busy.value = false;
  }
}

watch(
  () => props.open,
  (open) => {
    if (open) void hydrate();
  },
);

watch(showReactions, () => {
  if (!visibleActions.value.some((action) => action.ruleId === selectedRuleId.value)) {
    selectedRuleId.value = visibleActions.value[0]?.ruleId ?? null;
  }
});
</script>

<template>
  <v-dialog :model-value="open" max-width="460" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title>Действие</v-card-title>
      <v-card-text>
        <v-switch v-model="showReactions" label="Показывать реакции" density="compact" hide-details class="mb-2" />
        <v-autocomplete
          v-model="selectedRuleId"
          :items="visibleActions"
          item-title="name"
          item-value="ruleId"
          label="Выберите действие"
          placeholder="Начните вводить название"
          no-data-text="Действия не найдены"
          clearable
          :disabled="busy || !actorKey"
          :item-props="(item) => ({ disabled: item.odCost > actionPoints })"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps" :title="`${item.raw.name} · ${item.raw.odCost} ОД`" />
          </template>
        </v-autocomplete>
        <div v-if="selectedAction?.effects?.length" class="text-body-2 text-medium-emphasis">
          <div v-for="(effect, index) in selectedAction.effects" :key="index">
            {{ actionEffectService.describe(effect) }}
          </div>
        </div>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-3">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="emit('update:open', false)">Отмена</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="!selectedAction || !actorKey" @click="submitSafe">
          Выполнить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
