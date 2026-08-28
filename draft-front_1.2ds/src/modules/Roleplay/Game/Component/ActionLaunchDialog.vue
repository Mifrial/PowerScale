<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { actionOdCost, asActionAbilitySpec, asProcessAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
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
  'launch-process-step': [payload: { session: ProcessSession; stepCode: string; attack: AttackOverview }];
}>();

const overlays = ref<GameCombatOverlay[]>([]);
const pendingEffectsByEntity = ref<Record<CombatEntityKey, PendingActionEffect[]>>({});
const processSessionsByEntity = ref<Record<CombatEntityKey, ProcessSession>>({});
const selectedRuleId = ref<string | null>(null);
const selectedProcessStepCode = ref<string | null>(null);
const selectedProcessAttackKey = ref<string | null>(null);
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
    const process = asProcessAbilitySpec(rule);
    if (rule.keywordIds?.includes(71)) return [];
    if (!spec && !process) return [];
    if (!owned.has(rule.id) && (!spec || !Object.values(spec.zones).some((zone) => zone?.kind === 'automatic')))
      return [];
    const option: CombatActionOption = {
      ruleId: rule.id,
      code: rule.code,
      name: rule.name,
      odCost: spec ? actionOdCost(spec.action_components) : 0,
      effects: spec ? actionEffectService.effectsOf(rule) : [],
      isAttack: false,
      isReaction: rule.keywordIds?.includes(53) ?? false,
      isProcess: process !== null,
      process: process ?? undefined,
    };

    return [option];
  });
});

const visibleActions = computed(() => actions.value.filter((action) => action.isReaction === showReactions.value));
const activeProcess = computed(() => (actorKey.value ? processSessionsByEntity.value[actorKey.value] : undefined));
const processRule = computed(() =>
  activeProcess.value ? props.rules.find((rule) => rule.id === activeProcess.value?.processRuleId) : null,
);
const processRuleSpec = computed(() => (processRule.value ? asProcessAbilitySpec(processRule.value) : null));
const selectableActions = computed(() => {
  if (!activeProcess.value) return visibleActions.value;

  return visibleActions.value.filter(
    (action) => action.isProcess && action.ruleId === activeProcess.value?.processRuleId,
  );
});
const selectedAction = computed(
  () => selectableActions.value.find((action) => action.ruleId === selectedRuleId.value) ?? null,
);
const processSteps = computed(() => {
  if (!selectedAction.value?.process) return [];
  const currentStep =
    activeProcess.value?.processRuleId === selectedAction.value.ruleId
      ? activeProcess.value.currentStepCode
      : (selectedAction.value.process.start_step_code ?? selectedAction.value.process.steps[0]?.code);
  if (!currentStep) return [];

  return activeProcess.value
    ? processSessionService.availableSteps(selectedAction.value.process, currentStep)
    : selectedAction.value.process.steps.filter((step) => step.code === currentStep);
});
const processAttacks = computed(() => {
  const action = selectedAction.value;
  if (!action?.process || !actorOverview.value) return [];
  const rule = processRule.value ?? props.rules.find((item) => item.id === action.ruleId);
  const keywordIds = rule?.keywordIds ?? [];
  const melee = keywordIds.includes(1);
  const ranged = keywordIds.includes(2);

  return actorOverview.value.attacks.filter(
    (attack) =>
      (!melee && !ranged) || (melee && attack.profileType === 'strike') || (ranged && attack.profileType !== 'strike'),
  );
});
const selectedProcessAttack = computed<AttackOverview | null>(
  () =>
    processAttacks.value.find(
      (attack) => `${attack.itemRuleId}:${attack.profileType}` === selectedProcessAttackKey.value,
    ) ?? null,
);
const selectedProcessStep = computed(
  () => processSteps.value.find((step) => step.code === selectedProcessStepCode.value) ?? null,
);
const processStepApCost = computed(() =>
  selectedProcessStep.value ? processSessionService.stepCost(selectedProcessStep.value, 'action-points') : 0,
);
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
  processSessionsByEntity.value = await api.getProcessSessions(props.gameId).catch(() => ({}));
  overlays.value = nextOverlays;
  pendingEffectsByEntity.value = nextPending;
  selectedRuleId.value = selectableActions.value[0]?.ruleId ?? null;
  selectedProcessStepCode.value = null;
  selectedProcessAttackKey.value = null;
}

async function submit(): Promise<void> {
  const key = actorKey.value;
  const action = selectedAction.value;
  if (!key || !action) throw new Error('Выберите действие');
  if (action.isProcess) {
    const stepCode = selectedProcessStepCode.value;
    const attack = selectedProcessAttack.value;
    if (!action.process || !stepCode || !attack) throw new Error('Выберите шаг процесса и профиль атаки');
    if (processStepApCost.value > actionPoints.value) throw new Error('Недостаточно ОД для шага процесса');
    const session =
      activeProcess.value ?? processSessionService.start(props.gameId, key, action.ruleId, action.process);
    emit('launch-process-step', { session, stepCode, attack });
    emit('update:open', false);

    return;
  }
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

async function stopProcess(): Promise<void> {
  const key = actorKey.value;
  const process = activeProcess.value;
  if (!key || !process) return;
  const rule = processRule.value;
  if (!rule || !processRuleSpec.value) return;

  busy.value = true;
  error.value = null;
  try {
    await getGameApi().setProcessSession(props.gameId, key, null);
    const completionEffects = actionEffectService.effectsAfterProcess(rule);
    const pendingEffects = pendingEffectsByEntity.value[key] ?? [];
    const nextEffects = [...pendingEffects, ...completionEffects];
    pendingEffectsByEntity.value = { ...pendingEffectsByEntity.value, [key]: nextEffects };
    await getGameApi().setCombatActionEffects(props.gameId, key, nextEffects);
    if (props.chatId !== null) {
      await sendChat(`${rule.name}: процесс прекращён без траты ОД.`, [], props.chatId, speakerFor(key));
    }
    const nextSessions = { ...processSessionsByEntity.value };
    delete nextSessions[key];
    processSessionsByEntity.value = nextSessions;
    emit('settled');
    emit('update:open', false);
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось прекратить процесс';
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
  if (!selectableActions.value.some((action) => action.ruleId === selectedRuleId.value)) {
    selectedRuleId.value = selectableActions.value[0]?.ruleId ?? null;
  }
});

watch(selectedAction, (action) => {
  if (!action?.isProcess || !action.process) {
    selectedProcessStepCode.value = null;
    selectedProcessAttackKey.value = null;

    return;
  }
  selectedProcessStepCode.value =
    processSteps.value[0]?.code ?? action.process.start_step_code ?? action.process.steps[0]?.code ?? null;
  const attack = processAttacks.value[0];
  selectedProcessAttackKey.value = attack ? `${attack.itemRuleId}:${attack.profileType}` : null;
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
          :items="selectableActions"
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
            <v-list-item
              v-bind="itemProps"
              :title="item.raw.isProcess ? `${item.raw.name} · процесс` : `${item.raw.name} · ${item.raw.odCost} ОД`"
            />
          </template>
        </v-autocomplete>
        <div v-if="activeProcess" class="text-body-2 text-medium-emphasis mb-2">
          Активный процесс: <strong>{{ processRule?.name ?? activeProcess.processRuleId }}</strong
          >, текущий шаг — {{ activeProcess.currentStepCode }}
          <v-btn
            v-if="processRuleSpec && processSessionService.canExit(processRuleSpec, activeProcess.currentStepCode)"
            size="x-small"
            variant="text"
            color="warning"
            class="ml-1"
            :disabled="busy"
            @click="stopProcess"
          >
            Прекратить
          </v-btn>
        </div>
        <template v-if="selectedAction?.isProcess">
          <v-autocomplete
            v-model="selectedProcessStepCode"
            :items="processSteps"
            item-title="name"
            item-value="code"
            label="Следующий шаг"
            :disabled="busy"
            class="mb-2"
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item
                v-bind="itemProps"
                :title="`${item.raw.name} · ${processSessionService.stepCost(item.raw, 'action-points')} ОД`"
              />
            </template>
          </v-autocomplete>
          <v-autocomplete
            v-model="selectedProcessAttackKey"
            :items="processAttacks"
            :item-title="(item) => `${item.itemName} · ${item.profileTypeLabel}`"
            :item-value="(item) => `${item.itemRuleId}:${item.profileType}`"
            label="Профиль атаки"
            :disabled="busy"
          />
          <div class="text-body-2 text-medium-emphasis mb-2">Стоимость шага: {{ processStepApCost }} ОД</div>
        </template>
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
