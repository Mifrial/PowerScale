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
import type {
  HorizontalMovementDirection,
  VerticalMovementDirection,
} from '@/modules/Roleplay/Rule/Dto/Ability/MovementOperation';
import type { ActionOperationRequest } from '@/modules/Roleplay/Game/Dto/ActionOperationRequest';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { actionOperationResolutionService, getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService, movementContextService } from '@/modules/Roleplay/Character/init';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { actionExecutionService } from '@/modules/Roleplay/Game/Service/Instance/actionExecutionService';
import {
  actionOdCost,
  actionUsesChosenCost,
  asActionAbilitySpec,
  asProcessAbilitySpec,
  WAIT_ACTION_CODE,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';
import { formatProcessEffect } from '@/modules/Roleplay/Game/Utils/processMessage';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { MOVEMENT_DIRECTION_LABELS } from '@/modules/Roleplay/Game/Constant/Movement/MOVEMENT_DIRECTION_LABELS';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

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
const chosenActionOdCost = ref(0);
const showReactions = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);
const selectedHorizontalDirection = ref<HorizontalMovementDirection | null>(null);
const selectedVerticalDirection = ref<VerticalMovementDirection | null>(null);
const horizontalDistance = ref<DimensionalNumberValue | null>(null);
const verticalDistance = ref<DimensionalNumberValue | null>(null);
const currentSpeed = ref<CurrentSpeed>({
  horizontal: { stepsPerActionPoint: 0, direction: null },
  vertical: { stepsPerActionPoint: 0, direction: null },
});
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
const actorMovementStep = computed(() =>
  movementContextService.resolveMovementStep(actorVersion.value ?? undefined, props.rules),
);

const actions = computed<CombatActionOption[]>(() => {
  const owned = new Set(actorOverview.value?.abilities.map((ability) => ability.ruleId) ?? []);

  return props.rules.flatMap((rule) => {
    const spec = asActionAbilitySpec(rule);
    const process = asProcessAbilitySpec(rule);
    const requirements =
      rule.spec && typeof rule.spec === 'object' && 'requirements' in rule.spec ? rule.spec.requirements : [];
    if (rule.keywordIds?.includes(71)) return [];
    if (!spec && !process) return [];
    if (spec && !requirementsSatisfied(spec.requirements)) return [];
    if (process && !requirementsSatisfied(requirements)) return [];
    if (!owned.has(rule.id) && (!spec || !Object.values(spec.zones ?? {}).some((zone) => zone?.kind === 'automatic')))
      return [];
    const option: CombatActionOption = {
      ruleId: rule.id,
      code: rule.code,
      name: rule.name,
      odCost: spec ? actionOdCost(spec.action_components) : 0,
      isVariableCost: spec ? actionUsesChosenCost(spec.action_components) : false,
      effects: spec ? actionEffectService.effectsOf(rule) : [],
      isAttack: false,
      isReaction: rule.keywordIds?.includes(53) ?? false,
      isProcess: process !== null,
      process: process ?? undefined,
      operations: spec?.operations,
    };

    return [option];
  });
});

function requirementsSatisfied(entries: { level: number; requirements: Requirement[] }[]): boolean {
  return entries.every((entry) =>
    entry.requirements.every((requirement) => {
      if (requirement.type === 'current_speed') {
        const component = currentSpeed.value[requirement.axis];

        return (
          component.direction === requirement.direction &&
          component.stepsPerActionPoint >= requirement.min_steps_per_action_point
        );
      }
      if (requirement.type === 'and') return requirementsSatisfied([{ level: 1, requirements: requirement.children }]);
      if (requirement.type === 'or')
        return requirement.children.some((child) => requirementsSatisfied([{ level: 1, requirements: [child] }]));

      return true;
    }),
  );
}

const visibleActions = computed(() => actions.value.filter((action) => action.isReaction === showReactions.value));
const activeProcess = computed(() => (actorKey.value ? processSessionsByEntity.value[actorKey.value] : undefined));
const processRule = computed(() =>
  activeProcess.value ? props.rules.find((rule) => rule.id === activeProcess.value?.processRuleId) : null,
);
const processRuleSpec = computed(() => (processRule.value ? asProcessAbilitySpec(processRule.value) : null));
const selectableActions = computed(() => {
  if (!activeProcess.value) return visibleActions.value;

  return visibleActions.value.filter(
    (action) =>
      (action.isProcess && action.ruleId === activeProcess.value?.processRuleId) || action.code === WAIT_ACTION_CODE,
  );
});
const selectedAction = computed(
  () => selectableActions.value.find((action) => action.ruleId === selectedRuleId.value) ?? null,
);
const selectedActionOdCost = computed(() =>
  selectedAction.value?.isVariableCost ? chosenActionOdCost.value : (selectedAction.value?.odCost ?? 0),
);
const processSteps = computed(() => {
  if (!selectedAction.value?.process) return [];
  const currentStep =
    activeProcess.value?.processRuleId === selectedAction.value.ruleId
      ? activeProcess.value.currentStepCode
      : (selectedAction.value.process.start_step_code ?? selectedAction.value.process.steps[0]?.code);
  if (!currentStep) return [];

  if (!activeProcess.value) return selectedAction.value.process.steps.filter((step) => step.code === currentStep);

  const availableSteps = processSessionService.availableSteps(selectedAction.value.process, currentStep);
  if (activeProcess.value.currentStepStatus !== 'pending') return availableSteps;

  const currentStepItem = selectedAction.value.process.steps.find((step) => step.code === currentStep);

  return currentStepItem && !availableSteps.some((step) => step.code === currentStepItem.code)
    ? [currentStepItem, ...availableSteps]
    : availableSteps;
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
const selectedMovementOperations = computed(() => {
  const operations = selectedAction.value?.isProcess
    ? selectedProcessStep.value?.operations
    : selectedAction.value?.operations;

  return operations?.filter((operation) => operation.type === 'movement') ?? [];
});
const isMovementProcess = computed(
  () =>
    selectedAction.value?.process?.steps.some((step) =>
      step.operations?.some((operation) => operation.type === 'movement'),
    ) ?? false,
);
const movementContext = computed(() => ({
  currentMovementStep: actorMovementStep.value,
  characteristicValues: new Map(
    actorOverview.value?.characteristics.flatMap((characteristic) => {
      const rule = props.rules.find((item) => item.id === characteristic.ruleId);

      return rule ? [[rule.code, characteristic.value] as const] : [];
    }),
  ),
}));
const horizontalMovementBounds = computed(() => {
  const operation = selectedMovementOperations.value[0];

  return operation
    ? actionOperationResolutionService.movementBounds(operation, 'horizontal', movementContext.value)
    : null;
});
const verticalMovementBounds = computed(() => {
  const operation = selectedMovementOperations.value[0];

  return operation
    ? actionOperationResolutionService.movementBounds(operation, 'vertical', movementContext.value)
    : null;
});
const horizontalMovementMaxLabel = computed(() =>
  horizontalMovementBounds.value ? new DimensionalNumber(horizontalMovementBounds.value.max).toString() : null,
);
const verticalMovementMaxLabel = computed(() =>
  verticalMovementBounds.value ? new DimensionalNumber(verticalMovementBounds.value.max).toString() : null,
);
const movementInputError = computed(() => {
  const checks = [
    { value: horizontalDistance.value, bounds: horizontalMovementBounds.value, label: 'Горизонтальная' },
    { value: verticalDistance.value, bounds: verticalMovementBounds.value, label: 'Вертикальная' },
  ];
  for (const check of checks) {
    if (!check.value || !check.bounds) continue;
    const comparison = new DimensionalNumber(check.value).compare(new DimensionalNumber(check.bounds.max));
    if (comparison > 0) {
      return `${check.label} дистанция больше максимальной (${new DimensionalNumber(check.bounds.max).toString()})`;
    }
  }

  return null;
});
const horizontalDirectionOptions = computed(
  () =>
    selectedMovementOperations.value[0]?.allowedDirections.horizontal.map((value) => ({
      value,
      title: MOVEMENT_DIRECTION_LABELS[value],
    })) ?? [],
);
const verticalDirectionOptions = computed(
  () =>
    selectedMovementOperations.value[0]?.allowedDirections.vertical.map((value) => ({
      value,
      title: MOVEMENT_DIRECTION_LABELS[value],
    })) ?? [],
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
  const [nextOverlays, nextPending, nextSpeed] = await Promise.all([
    api.getCombatOverlays(props.gameId).catch(() => []),
    api.getPendingActionEffects(props.gameId).catch(() => ({})),
    keyForHydrate(api),
  ]);
  processSessionsByEntity.value = await api.getProcessSessions(props.gameId).catch(() => ({}));
  overlays.value = nextOverlays;
  pendingEffectsByEntity.value = nextPending;
  if (nextSpeed) currentSpeed.value = nextSpeed;
  selectedRuleId.value = selectableActions.value[0]?.ruleId ?? null;
  selectedProcessStepCode.value = null;
  selectedProcessAttackKey.value = null;
}

async function keyForHydrate(api: ReturnType<typeof getGameApi>): Promise<CurrentSpeed | null> {
  const key = actorKey.value;

  return key ? api.getCurrentSpeed(props.gameId, key).catch(() => null) : null;
}

function operationRequestsOf(): ActionOperationRequest[] {
  if (!selectedMovementOperations.value.length) return [];
  const request: NonNullable<ActionOperationRequest['movement']> = {};
  if (selectedHorizontalDirection.value && horizontalDistance.value) {
    request.horizontal = {
      direction: selectedHorizontalDirection.value,
      distance: horizontalDistance.value,
    };
  }
  if (selectedVerticalDirection.value && verticalDistance.value) {
    request.vertical = {
      direction: selectedVerticalDirection.value,
      distance: verticalDistance.value,
    };
  }

  return [{ movement: request }];
}

async function submit(): Promise<void> {
  const key = actorKey.value;
  const action = selectedAction.value;
  if (!key || !action) throw new Error('Выберите действие');
  const speaker = speakerFor(key);
  const operationRequests = operationRequestsOf();
  if (movementInputError.value) throw new Error(movementInputError.value);
  if (action.isProcess) {
    const stepCode = selectedProcessStepCode.value;
    const attack = selectedProcessAttack.value;
    if (!action.process || !stepCode) throw new Error('Выберите шаг процесса');
    if (processStepApCost.value > actionPoints.value) throw new Error('Недостаточно ОД для шага процесса');
    const session =
      activeProcess.value ?? processSessionService.start(props.gameId, key, action.ruleId, action.process);
    const step = action.process.steps.find((item) => item.code === stepCode);
    if (!step) throw new Error('Шаг процесса не найден');
    if (step.operations?.length || isMovementProcess.value) {
      const version = versionOf(key);
      if (!version) throw new Error('Лист участника не найден');
      const resolution = await actionExecutionService.execute({
        gameId: props.gameId,
        entityKey: key,
        version,
        rule: processRule.value ?? actionRuleOf(action.ruleId),
        action,
        rules: props.rules,
        mechanics: props.mechanics,
        pendingEffects: pendingEffectsByEntity.value[key] ?? [],
        actionPointCost: processStepApCost.value,
        attackerName: speaker.kind === 'character' ? speaker.characterName : 'НПС',
        chatId: props.chatId,
        speaker,
        sendChat,
        operations: step.operations ?? [],
        operationRequests,
        currentMovementStep: actorMovementStep.value,
      });
      const resolvedSession = processSessionService.resolveStep(
        session,
        action.process,
        stepCode,
        resolution.resolution.status === 'completed',
      );
      const nextSession = resolvedSession
        ? processSessionService.recordResolution(resolvedSession, resolution.resolution)
        : null;
      await getGameApi().setProcessSession(props.gameId, key, nextSession);
      overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, resolution.overlay);
      pendingEffectsByEntity.value = {
        ...pendingEffectsByEntity.value,
        [key]: resolution.effects,
      };
      currentSpeed.value = await getGameApi().getCurrentSpeed(props.gameId, key);
      emit('overlay-changed');

      return;
    }
    if (!attack) throw new Error('Выберите профиль атаки');
    emit('launch-process-step', { session, stepCode, attack });
    emit('update:open', false);

    return;
  }
  const actionOd = selectedActionOdCost.value;
  if (actionOd <= 0) throw new Error('Укажите количество ОД');
  if (actionOd > actionPoints.value) throw new Error('Недостаточно ОД для действия');
  const version = versionOf(key);
  if (!version) throw new Error('Лист участника не найден');
  const actionRule = props.rules.find((rule) => rule.id === action.ruleId);
  if (!actionRule) throw new Error('Правило действия не найдено в текущей ревизии');
  let pendingEffects = pendingEffectsByEntity.value[key] ?? [];
  const processSession = activeProcess.value;
  if (processSession) {
    const processRule = props.rules.find((rule) => rule.id === processSession.processRuleId);
    const processSpec = processRule ? asProcessAbilitySpec(processRule) : null;
    if (
      !processRule ||
      !processSpec ||
      !processSessionService.canInterruptNormally(processSpec, processSession.currentStepCode)
    ) {
      throw new Error('Текущий процесс нельзя прервать обычным способом');
    }
    await getGameApi().setProcessSession(props.gameId, key, null);
    const completionEffects = actionEffectService.effectsAfterProcess(processRule);
    pendingEffects = [...pendingEffects, ...completionEffects];
    if (props.chatId !== null) {
      const effectText = completionEffects.length
        ? ` Эффект: ${completionEffects.map((item) => formatProcessEffect(item.effect, props.rules)).join('; ')}.`
        : '';
      await sendChat(`${processRule?.name ?? 'Процесс'} прерван.${effectText}`, [], props.chatId, speakerFor(key));
    }
    const nextSessions = { ...processSessionsByEntity.value };
    delete nextSessions[key];
    processSessionsByEntity.value = nextSessions;
  }

  const execution = await actionExecutionService.execute({
    gameId: props.gameId,
    entityKey: key,
    version,
    rule: actionRule,
    action,
    rules: props.rules,
    pendingEffects,
    actionPointCost: actionOd,
    attackerName:
      speaker.kind === 'character' ? speaker.characterName : speaker.kind === 'npc' ? speaker.npcName : 'Персонаж',
    chatId: props.chatId,
    speaker,
    sendChat,
    operations: action.operations,
    operationRequests,
    currentMovementStep: actorMovementStep.value,
    characteristicValues: new Map(
      actorOverview.value?.characteristics.flatMap((characteristic) => {
        const rule = props.rules.find((item) => item.id === characteristic.ruleId);

        return rule ? [[rule.code, characteristic.value] as const] : [];
      }),
    ),
    mechanics: props.mechanics,
  });
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, execution.overlay);
  const effects = execution.effects;
  pendingEffectsByEntity.value = { ...pendingEffectsByEntity.value, [key]: effects };
  currentSpeed.value = await getGameApi().getCurrentSpeed(props.gameId, key);
  emit('overlay-changed');
}

function actionRuleOf(ruleId: string): Rule {
  const rule = props.rules.find((entry) => entry.id === ruleId);
  if (!rule) throw new Error('Правило действия не найдено в текущей ревизии');

  return rule;
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
      const effectText = completionEffects.length
        ? ` Эффект: ${completionEffects.map((item) => formatProcessEffect(item.effect, props.rules)).join('; ')}.`
        : '';
      await sendChat(`${rule.name}: процесс прекращён без траты ОД.${effectText}`, [], props.chatId, speakerFor(key));
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
  chosenActionOdCost.value = action?.isVariableCost ? actionPoints.value : 0;
  if (!action?.isProcess || !action.process) {
    selectedProcessStepCode.value = null;
    selectedProcessAttackKey.value = null;

    return;
  }
  const movement = action.operations?.find((operation) => operation.type === 'movement');
  selectedHorizontalDirection.value = movement?.allowedDirections.horizontal[0] ?? null;
  selectedVerticalDirection.value = movement?.allowedDirections.vertical[0] ?? null;
  horizontalDistance.value = null;
  verticalDistance.value = null;
  selectedProcessStepCode.value =
    processSteps.value[0]?.code ?? action.process.start_step_code ?? action.process.steps[0]?.code ?? null;
  const attack = processAttacks.value[0];
  selectedProcessAttackKey.value = attack ? `${attack.itemRuleId}:${attack.profileType}` : null;
});
watch(actionPoints, (points) => {
  if (selectedAction.value?.isVariableCost && chosenActionOdCost.value === 0) chosenActionOdCost.value = points;
});
watch(
  processSteps,
  (steps) => {
    if (!steps.some((step) => step.code === selectedProcessStepCode.value)) {
      selectedProcessStepCode.value = steps[0]?.code ?? null;
    }
  },
  { immediate: true },
);
watch(
  [selectedMovementOperations, horizontalMovementBounds, verticalMovementBounds],
  ([operations, horizontalBounds, verticalBounds]) => {
    const operation = operations[0];
    selectedHorizontalDirection.value = operation?.allowedDirections.horizontal[0] ?? null;
    selectedVerticalDirection.value = operation?.allowedDirections.vertical[0] ?? null;
    horizontalDistance.value = horizontalBounds?.max ?? null;
    verticalDistance.value = verticalBounds?.max ?? null;
  },
  { immediate: true },
);
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
          :item-props="(item) => ({ disabled: !item.isVariableCost && item.odCost > actionPoints })"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item
              v-bind="itemProps"
              :title="
                item.raw.isProcess
                  ? `${item.raw.name} · процесс`
                  : `${item.raw.name} · ${item.raw.isVariableCost ? 'выберите ОД' : `${item.raw.odCost} ОД`}`
              "
            />
          </template>
        </v-autocomplete>
        <div v-if="activeProcess" class="text-body-2 text-medium-emphasis mb-2">
          Активный процесс: <strong>{{ processRule?.name ?? activeProcess.processRuleId }}</strong
          >, текущий шаг — {{ activeProcess.currentStepCode }}
          <v-btn
            v-if="
              processRuleSpec &&
              processSessionService.canInterruptNormally(processRuleSpec, activeProcess.currentStepCode)
            "
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
        <ClampedNumberField
          v-if="selectedAction?.isVariableCost"
          :model-value="chosenActionOdCost"
          :min="1"
          :max="actionPoints"
          label="Количество ОД"
          hint="Можно потратить любое доступное количество ОД"
          persistent-hint
          density="compact"
          class="mb-2"
          :disabled="busy"
          @update:model-value="chosenActionOdCost = $event"
        />
        <template v-if="selectedMovementOperations.length">
          <v-select
            v-if="horizontalDirectionOptions.length"
            v-model="selectedHorizontalDirection"
            :items="horizontalDirectionOptions"
            item-title="title"
            item-value="value"
            label="Горизонтальное направление"
            density="compact"
            class="mb-2"
          />
          <DimensionalNumberInput
            v-if="selectedHorizontalDirection"
            v-model="horizontalDistance"
            label="Горизонтальная дистанция"
            density="compact"
            class="mb-2"
          />
          <div v-if="horizontalMovementMaxLabel" class="text-caption text-medium-emphasis mb-2">
            Максимум: {{ horizontalMovementMaxLabel }}
          </div>
          <v-select
            v-if="verticalDirectionOptions.length"
            v-model="selectedVerticalDirection"
            :items="verticalDirectionOptions"
            item-title="title"
            item-value="value"
            label="Вертикальное направление"
            density="compact"
            class="mb-2"
          />
          <DimensionalNumberInput
            v-if="selectedVerticalDirection"
            v-model="verticalDistance"
            label="Вертикальная дистанция"
            density="compact"
            class="mb-2"
          />
          <div v-if="verticalMovementMaxLabel" class="text-caption text-medium-emphasis mb-2">
            Максимум: {{ verticalMovementMaxLabel }}
          </div>
        </template>
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
            v-if="!isMovementProcess"
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
        <v-alert v-if="movementInputError" type="error" variant="tonal" density="compact" class="mt-3">
          {{ movementInputError }}
        </v-alert>
        <v-alert v-else-if="error" type="error" variant="tonal" density="compact" class="mt-3">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="emit('update:open', false)">Отмена</v-btn>
        <v-btn
          color="primary"
          :loading="busy"
          :disabled="!selectedAction || !actorKey || !!movementInputError"
          @click="submitSafe"
        >
          Выполнить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
