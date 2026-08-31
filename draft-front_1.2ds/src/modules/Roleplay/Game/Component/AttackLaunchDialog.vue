<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { AttackAction } from '@/modules/Roleplay/Game/Dto/AttackAction';
import type { AttackActionStrike } from '@/modules/Roleplay/Game/Dto/AttackActionStrike';
import type { AttackActionSlotDraft } from '@/modules/Roleplay/Game/Dto/AttackActionSlotDraft';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService, useAttackFavorites } from '@/modules/Roleplay/Character/init';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { attackActionSourceService } from '@/modules/Roleplay/Game/Service/Instance/attackActionSourceService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { asProcessAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
import { AttackProfileOption } from '@/modules/Roleplay/Character/init';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';
import { formatProcessEffect } from '@/modules/Roleplay/Game/Utils/processMessage';

const props = defineProps<{
  open: boolean;
  gameId: number;
  chatId: number | null;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  mechanics: Mechanic[];
  activeSpeakerKey: string | null;
  actorKey?: CombatEntityKey | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  'launch-attack': [attackAction: AttackAction];
}>();

const overlays = ref<GameCombatOverlay[]>([]);
const processSessions = ref<Record<CombatEntityKey, ProcessSession>>({});
const sourceRuleId = ref<string | null>(null);
const processStepCode = ref<string | null>(null);
const slots = ref<AttackActionSlotDraft[]>([{ profile: null, targetKey: null }]);
const profileMenuSlot = ref<number | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);
const sendChat = combatChatSendService.sendCombatChat(props.gameId);
const attackFavorites = useAttackFavorites();

const actorKey = computed<CombatEntityKey | null>(() => {
  if (props.actorKey) return props.actorKey;
  const key = props.activeSpeakerKey;

  return key && key !== 'gm' ? (key as CombatEntityKey) : null;
});

const actorVersion = computed(() => {
  if (!actorKey.value) return null;
  const overlay = overlays.value.find((item) => item.entityKey === actorKey.value) ?? null;

  return combatCardModelService.combatCardModel(actorKey.value, props.characters, props.npcs, true, null, overlay)
    .effectiveVersion;
});

const actorOverview = computed(() =>
  actorVersion.value ? characterOverviewService.build(actorVersion.value, props.rules) : null,
);
const favoriteAttack = computed(() => {
  const key = actorKey.value;
  const overview = actorOverview.value;
  if (!key || !overview) return null;
  const favorite = attackFavorites.favoriteOf(key);
  if (!favorite) return null;

  return (
    overview.attacks.find(
      (attack) =>
        attack.itemRuleId === favorite.itemRuleId &&
        attack.profileType === favorite.profileType &&
        (attack.profileIndex ?? 0) === favorite.profileIndex,
    ) ?? null
  );
});
const activeProcess = computed(() =>
  actorKey.value && processSessions.value[actorKey.value] ? processSessions.value[actorKey.value] : null,
);
const sources = computed(() => {
  const available = attackActionSourceService.list(props.rules, actorOverview.value);
  if (!activeProcess.value) return available;

  return available.filter((source) => source.isProcess && source.ruleId === activeProcess.value?.processRuleId);
});
const selectedSource = computed<CombatActionOption | null>(
  () => sources.value.find((source) => source.ruleId === sourceRuleId.value) ?? null,
);
const isWideAttack = computed(() => selectedSource.value?.attackMode === 'wide');
const selectedSourceRule = computed(() =>
  selectedSource.value ? (props.rules.find((rule) => rule.id === selectedSource.value?.ruleId) ?? null) : null,
);
const processSteps = computed(() => {
  const process = selectedSource.value?.process;
  if (!process) return [];
  const currentStepCode =
    activeProcess.value?.processRuleId === selectedSource.value.ruleId
      ? activeProcess.value.currentStepCode
      : (process.start_step_code ?? process.steps[0]?.code);
  if (!currentStepCode) return [];

  if (!activeProcess.value) return process.steps.filter((step) => step.code === currentStepCode);

  const availableSteps = processSessionService.availableSteps(process, currentStepCode);
  if (activeProcess.value.currentStepStatus !== 'pending') return availableSteps;

  const currentStep = process.steps.find((step) => step.code === currentStepCode);

  return currentStep && !availableSteps.some((step) => step.code === currentStep.code)
    ? [currentStep, ...availableSteps]
    : availableSteps;
});
const selectedProcessStep = computed(
  () => processSteps.value.find((step) => step.code === processStepCode.value) ?? null,
);
const compatibleProfiles = computed(() =>
  attackActionSourceService.compatibleProfiles(selectedSourceRule.value, actorOverview.value?.attacks ?? []),
);
const pendingEffects = ref<Record<CombatEntityKey, PendingActionEffect[]>>({});
const targetOptions = computed(() => [
  ...props.characters
    .filter(
      (membership) =>
        membership.membershipStatus === 'active' && `character:${membership.characterId}` !== actorKey.value,
    )
    .map((membership) => ({
      value: `character:${membership.characterId}` as CombatEntityKey,
      title: membership.characterName,
    })),
  ...props.npcs
    .filter((npc) => npc.status === 'active' && `npc:${npc.id}` !== actorKey.value)
    .map((npc) => ({ value: `npc:${npc.id}` as CombatEntityKey, title: npc.name })),
]);
const baseCost = computed(() => {
  if (selectedSource.value?.isProcess) {
    const step =
      selectedProcessStep.value ??
      selectedSource.value.process?.steps.find((item) => item.code === processStepCode.value);

    return step ? processSessionService.stepCost(step, ACTION_POINTS_CODE) : 0;
  }

  return selectedSource.value?.odCost ?? 0;
});
const finalCost = computed(() => {
  const profileType = slots.value[0]?.profile?.profileType ?? 'strike';
  const pending = actorKey.value ? (pendingEffects.value[actorKey.value] ?? []) : [];
  const resolution = actionEffectService.resolveForNextAction(pending, {
    isAttack: true,
    component: profileType,
    baseCost: baseCost.value,
  });

  return baseCost.value + resolution.actionCostDelta;
});
const actionPoints = computed(() => {
  if (!actorOverview.value) return 0;
  const resource = attackDamageService.actionPointsResource(actorOverview.value, props.rules);

  return resource?.current.base ?? 0;
});
function sourceTitle(source: CombatActionOption): string {
  if (source.isProcess) return `${source.name} · процесс`;

  return `${source.name} · ${source.odCost} ОД`;
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

function profileLabel(profile: AttackOverview | null): string {
  return profile ? `${profile.itemName} · ${profile.profileTypeLabel}` : 'Выберите профиль оружия';
}

function profileKey(profile: AttackOverview): string {
  return `${profile.itemRuleId}:${profile.profileType}:${profile.profileIndex ?? 'legacy'}`;
}

function selectProfile(slotIndex: number, profile: AttackOverview): void {
  const nextSlots = [...slots.value];
  if (isWideAttack.value) {
    slots.value = nextSlots.map((slot) => ({ ...slot, profile }));
  } else {
    nextSlots[slotIndex] = { ...nextSlots[slotIndex], profile };
    slots.value = nextSlots;
  }
  if (actorKey.value) {
    attackFavorites.setFavorite(actorKey.value, {
      itemRuleId: profile.itemRuleId,
      profileType: profile.profileType,
      profileIndex: profile.profileIndex ?? 0,
    });
  }
  profileMenuSlot.value = null;
}

function addTarget(): void {
  if (!isWideAttack.value || slots.value.length >= attackActionSourceService.maxTargets(selectedSourceRule.value))
    return;
  slots.value = [...slots.value, { profile: slots.value[0]?.profile ?? null, targetKey: null }];
}

function removeTarget(index: number): void {
  if (!isWideAttack.value || index === 0) return;
  slots.value = slots.value.filter((_, slotIndex) => slotIndex !== index);
}

async function hydrate(): Promise<void> {
  const api = getGameApi();
  const [nextOverlays, nextPending, nextProcesses] = await Promise.all([
    api.getCombatOverlays(props.gameId).catch(() => []),
    api.getPendingActionEffects(props.gameId).catch(() => ({})),
    api.getProcessSessions(props.gameId).catch(() => ({})),
  ]);
  overlays.value = nextOverlays;
  pendingEffects.value = nextPending;
  processSessions.value = nextProcesses;
  sourceRuleId.value = sources.value[0]?.ruleId ?? null;
  slots.value = [{ profile: null, targetKey: targetOptions.value[0]?.value ?? null }];
}

async function stopProcess(): Promise<void> {
  const key = actorKey.value;
  const session = activeProcess.value;
  if (!key || !session) return;
  const processRule = props.rules.find((rule) => rule.id === session.processRuleId);
  const processSpec = processRule ? asProcessAbilitySpec(processRule) : null;
  if (
    !processRule ||
    !processSpec ||
    !processSessionService.canInterruptNormally(processSpec, session.currentStepCode)
  ) {
    error.value = 'Текущий процесс нельзя прервать обычным способом';

    return;
  }

  busy.value = true;
  error.value = null;
  try {
    await getGameApi().setProcessSession(props.gameId, key, null);
    const completionEffects = actionEffectService.effectsAfterProcess(processRule);
    const currentEffects = pendingEffects.value[key] ?? [];
    const nextEffects = [...currentEffects, ...completionEffects];
    pendingEffects.value = { ...pendingEffects.value, [key]: nextEffects };
    await getGameApi().setCombatActionEffects(props.gameId, key, nextEffects);
    if (props.chatId !== null) {
      const effectText = completionEffects.length
        ? ` Эффект: ${completionEffects.map((item) => formatProcessEffect(item.effect, props.rules)).join('; ')}.`
        : '';
      await sendChat(`${processRule?.name ?? 'Процесс'} прекращён.${effectText}`, [], props.chatId, speakerFor(key));
    }

    const nextSessions = { ...processSessions.value };
    delete nextSessions[key];
    processSessions.value = nextSessions;
    sourceRuleId.value = sources.value[0]?.ruleId ?? null;
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось прекратить процесс';
  } finally {
    busy.value = false;
  }
}

async function submit(): Promise<void> {
  const source = selectedSource.value;
  const initiator = actorKey.value;
  if (!source || !initiator) throw new Error('Выберите атакующего и атаку');
  const selectedStepCode =
    selectedProcessStep.value?.code ??
    processStepCode.value ??
    source.process?.start_step_code ??
    source.process?.steps[0]?.code ??
    null;
  if (source.isProcess && (!selectedStepCode || !source.process)) throw new Error('Выберите шаг процесса');
  if (slots.value.some((slot) => !slot.profile || !slot.targetKey))
    throw new Error('Заполните профиль и цель каждого удара');
  const targetCountError = attackActionSourceService.validateTargetCount(
    selectedSourceRule.value,
    slots.value.flatMap((slot) => (slot.targetKey ? [slot.targetKey] : [])),
  );
  if (targetCountError) throw new Error(targetCountError);
  if (!isWideAttack.value && slots.value.some((slot) => slot.targetKey !== slots.value[0]?.targetKey)) {
    throw new Error('Одновременные удары должны иметь одну общую цель');
  }
  if (isWideAttack.value && new Set(slots.value.map((slot) => slot.targetKey)).size !== slots.value.length) {
    throw new Error('Цели Широкого удара должны быть различными');
  }
  const attackStrikes: AttackActionStrike[] = slots.value.flatMap((slot) =>
    slot.profile && slot.targetKey ? [{ profile: slot.profile, targetKey: slot.targetKey }] : [],
  );
  if (attackStrikes.length !== slots.value.length) throw new Error('Не удалось собрать удары атаки');
  if (finalCost.value > actionPoints.value) throw new Error('Недостаточно ОД для атаки');
  const processSession =
    activeProcess.value ??
    (source.process ? processSessionService.start(props.gameId, initiator, source.ruleId, source.process) : null);
  if (source.isProcess && !processSession) throw new Error('Не удалось создать сессию процесса');
  const processSource =
    source.isProcess && processSession && selectedStepCode
      ? { kind: 'process' as const, process: { session: processSession, stepCode: selectedStepCode } }
      : null;
  if (source.isProcess && !processSource) throw new Error('Не удалось определить шаг процесса');

  emit('launch-attack', {
    initiator,
    source: processSource ?? { kind: 'action', actionRuleId: source.ruleId },
    strikes: attackStrikes,
    mode: isWideAttack.value ? 'wide' : 'single',
    reactionMode: 'simultaneous',
    totalOdCost: finalCost.value,
  });
  emit('update:open', false);
}

async function submitSafe(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await submit();
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось подготовить атаку';
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
watch(selectedSource, (source) => {
  processStepCode.value = source?.process?.start_step_code ?? source?.process?.steps[0]?.code ?? null;
  const preferred = favoriteAttack.value;
  const profile = attackActionSourceService.isProfileAvailable(preferred, compatibleProfiles.value) ? preferred : null;
  slots.value = [{ profile, targetKey: targetOptions.value[0]?.value ?? null }];
});
watch(
  processSteps,
  (steps) => {
    if (!steps.some((step) => step.code === processStepCode.value)) {
      processStepCode.value = steps[0]?.code ?? null;
    }
  },
  { immediate: true },
);
watch(selectedProcessStep, () => {
  const preferred = favoriteAttack.value;
  const profile = attackActionSourceService.isProfileAvailable(preferred, compatibleProfiles.value) ? preferred : null;

  slots.value = slots.value.map((slot, index) => ({ ...slot, profile: index === 0 ? profile : null }));
});
</script>

<template>
  <v-dialog :model-value="open" max-width="620" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title>Атака</v-card-title>
      <v-card-text>
        <v-autocomplete
          v-model="sourceRuleId"
          :items="sources"
          :item-title="sourceTitle"
          item-value="ruleId"
          label="Атака или процесс"
          :disabled="busy || !actorKey"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps" :title="sourceTitle(item.raw)" />
          </template>
        </v-autocomplete>
        <div class="text-body-2 text-medium-emphasis mb-3">
          Итоговая стоимость атаки: <strong>{{ finalCost }} ОД</strong>
          <v-btn
            v-if="activeProcess"
            size="x-small"
            variant="text"
            color="warning"
            class="ml-1"
            :disabled="busy"
            @click="stopProcess"
          >
            Прекратить процесс
          </v-btn>
        </div>

        <template v-if="selectedSource?.isProcess">
          <v-autocomplete
            v-model="processStepCode"
            :items="processSteps"
            item-title="name"
            item-value="code"
            label="Шаг процесса"
            :disabled="busy"
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item
                v-bind="itemProps"
                :title="`${item.raw.name} · ${processSessionService.stepCost(item.raw, ACTION_POINTS_CODE)} ОД`"
                :subtitle="item.raw.description"
              />
            </template>
          </v-autocomplete>
        </template>

        <div v-for="(slot, index) in slots" :key="index" class="attack-slot mb-3">
          <div class="d-flex align-center ga-2 mb-1">
            <span class="text-subtitle-2">{{ isWideAttack ? `Цель ${index + 1}` : `Удар ${index + 1}` }}</span>
            <v-spacer />
            <v-btn
              v-if="isWideAttack && index > 0"
              icon="mdi-close"
              size="x-small"
              variant="text"
              :disabled="busy"
              aria-label="Удалить цель"
              @click="removeTarget(index)"
            />
          </div>
          <v-menu
            v-if="!isWideAttack || index === 0"
            :model-value="profileMenuSlot === index"
            :close-on-content-click="false"
            location="bottom"
            @update:model-value="(open) => (profileMenuSlot = open ? index : null)"
          >
            <template #activator="{ props: menuProps }">
              <v-sheet v-bind="menuProps" class="profile-choice rounded border pa-2 mb-2">
                <div class="d-flex align-center justify-space-between ga-2">
                  <span class="text-body-2 font-weight-medium text-truncate">
                    <template v-if="slot.profile">
                      {{ slot.profile.itemName }} · {{ slot.profile.profileTypeLabel }} · Дистанция
                      {{ slot.profile.distanceLabel }}
                    </template>
                    <template v-else>{{ profileLabel(slot.profile) }}</template>
                  </span>
                  <v-icon size="18">mdi-chevron-down</v-icon>
                </div>
                <div v-if="slot.profile" class="text-caption text-medium-emphasis">
                  {{ slot.profile.accuracyLabel }} · {{ slot.profile.damageLabel }} ·
                  {{ slot.profile.penetrationLabel }}
                </div>
              </v-sheet>
            </template>
            <v-card min-width="420" max-width="560">
              <v-list density="compact">
                <AttackProfileOption
                  v-for="profile in compatibleProfiles"
                  :key="profileKey(profile)"
                  :attack="profile"
                  :selected="slot.profile ? profileKey(slot.profile) === profileKey(profile) : false"
                  @select="selectProfile(index, $event)"
                />
                <v-list-item v-if="compatibleProfiles.length === 0" title="Подходящих профилей нет" />
              </v-list>
            </v-card>
          </v-menu>
          <div v-else-if="slot.profile" class="text-body-2 text-medium-emphasis mb-2">
            Профиль: {{ slot.profile.itemName }} · {{ slot.profile.profileTypeLabel }}
          </div>
          <v-autocomplete
            v-model="slot.targetKey"
            :items="targetOptions"
            item-title="title"
            item-value="value"
            label="Цель удара"
            density="compact"
            hide-details
            :disabled="busy"
          />
        </div>
        <v-btn
          v-if="isWideAttack && slots.length < attackActionSourceService.maxTargets(selectedSourceRule)"
          variant="outlined"
          size="small"
          class="mb-2"
          :disabled="busy || !slots[0]?.profile"
          @click="addTarget"
        >
          + Цель
        </v-btn>

        <div v-if="selectedSource?.effects?.length" class="text-body-2 text-medium-emphasis mt-2">
          <div v-for="(effect, index) in selectedSource.effects" :key="index">
            {{ actionEffectService.describe(effect) }}
          </div>
        </div>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-3">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="emit('update:open', false)">Отмена</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="!selectedSource || !actorKey" @click="submitSafe">
          Продолжить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.profile-choice {
  cursor: pointer;
}
.attack-slot {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  padding: 8px;
}
</style>
