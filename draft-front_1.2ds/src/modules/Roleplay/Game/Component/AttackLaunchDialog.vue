<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { AttackAction } from '@/modules/Roleplay/Game/Dto/AttackAction';
import type { AttackActionStrike } from '@/modules/Roleplay/Game/Dto/AttackActionStrike';
import type { AttackActionSlotDraft } from '@/modules/Roleplay/Game/Dto/AttackActionSlotDraft';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { resolveLaunchLoad } from '@/modules/Roleplay/Game/Utils/launchLoadState';
import {
  characterOverviewService,
  movementContextService,
  useAttackFavorites,
} from '@/modules/Roleplay/Character/init';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { furiousRushService } from '@/modules/Roleplay/Game/Service/Instance/furiousRushService';
import { attackActionSourceService } from '@/modules/Roleplay/Game/Service/Instance/attackActionSourceService';
import { lastStrikeService } from '@/modules/Roleplay/Game/Service/Instance/lastStrikeService';
import { pushProfileService } from '@/modules/Roleplay/Game/Service/Instance/pushProfileService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { comboProcessService } from '@/modules/Roleplay/Game/Service/Instance/comboProcessService';
import { COMBO_STEP_CODES } from '@/modules/Roleplay/Game/Constant/Process/COMBO_STEP_CODES';
import {
  asProcessAbilitySpec,
  asActionAbilitySpec,
  actionRefEquals,
  findRuleByRef,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
import { AttackProfileOption } from '@/modules/Roleplay/Character/init';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';
import CombatEntitySelect from '@/modules/Roleplay/Game/Component/CombatEntitySelect.vue';
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
  initiativeKeys?: string[];
  runtimeProjections?: Record<CombatEntityKey, GameRuntimeEntityProjection>;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  'launch-attack': [attackAction: AttackAction];
  'overlay-changed': [];
}>();

const overlays = ref<GameCombatOverlay[]>([]);
const processSessions = ref<Record<CombatEntityKey, ProcessSession>>({});
const committedSessions = ref<Record<CombatEntityKey, CommittedActionSession>>({});
const sourceRuleCode = ref<string | null>(null);
const processStepCode = ref<string | null>(null);
const slots = ref<AttackActionSlotDraft[]>([{ profile: null, targetKey: null }]);
const profileMenuSlot = ref<number | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);
const loadPhase = ref<'loading' | 'error' | 'incomplete' | 'ready'>('loading');
const loadError = ref<string | null>(null);
let loadGeneration = 0;
let loadAbort: AbortController | null = null;
const selectedOptionalChildCodes = ref<string[]>([]);
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

  return combatCardModelService.combatCardModel(
    actorKey.value,
    props.characters,
    props.npcs,
    true,
    null,
    overlay,
    props.runtimeProjections?.[actorKey.value] ?? null,
  ).effectiveVersion;
});

const actorOverview = computed(() =>
  actorVersion.value ? characterOverviewService.build(actorVersion.value, props.rules) : null,
);
const favoriteAttack = computed(() =>
  attackActionSourceService.favoriteAttack(
    actorOverview.value?.attacks ?? [],
    actorKey.value ? (attackFavorites.favoriteOf(actorKey.value) ?? null) : null,
    props.rules,
  ),
);
const activeProcess = computed(() =>
  actorKey.value && processSessions.value[actorKey.value] ? processSessions.value[actorKey.value] : null,
);
const activeCommitted = computed(() => (actorKey.value ? (committedSessions.value[actorKey.value] ?? null) : null));
const sources = computed(() => {
  const available = attackActionSourceService.list(props.rules, actorOverview.value);
  const session = activeProcess.value;
  const processRule = session ? findRuleByRef(props.rules, session.processRuleCode) : null;
  const spec = processRule ? asProcessAbilitySpec(processRule) : null;

  return comboProcessService.listSources(available, session, spec, actorOverview.value, props.rules);
});
const selectedSource = computed<CombatActionOption | null>(
  () =>
    sources.value.find((source) => source.code === sourceRuleCode.value || source.ruleCode === sourceRuleCode.value) ??
    null,
);
const selectedSourceRule = computed(() => findRuleByRef(props.rules, selectedSource.value?.code) ?? null);
const launchEffectLines = computed(() => {
  const lines = actionEffectService.describeForLaunch(
    selectedSourceRule.value,
    actorVersion.value,
    slots.value[0]?.profile ?? null,
    props.rules,
    selectedOptionalChildCodes.value,
  );
  const paired = attackActionSourceService.sameWeaponCheckModifiers(selectedSourceRule.value, slots.value.length);
  if (paired[0]) {
    lines.push(`${Math.abs(paired[0].delta)} помехи к текущим проверкам на попадание (множественная атака)`);
  }

  return lines;
});
const afterStrikeOptions = computed(() =>
  actorVersion.value
    ? furiousRushService.optionsOf(selectedSourceRule.value?.code, actorVersion.value.abilities, props.rules)
    : [],
);
const activeComboSpec = computed(() => {
  const session = activeProcess.value;
  const processRule = session ? findRuleByRef(props.rules, session.processRuleCode) : null;
  const liveSpec = processRule ? asProcessAbilitySpec(processRule) : null;
  if (comboProcessService.isComboSpec(liveSpec)) return liveSpec;
  if (comboProcessService.isComboSpec(selectedSource.value?.process ?? null)) {
    return selectedSource.value?.process ?? null;
  }

  return null;
});
const comboLockedTarget = computed(() =>
  activeComboSpec.value ? (activeProcess.value?.comboTargetKey ?? null) : null,
);
const isWideAttack = computed(() => selectedSource.value?.attackMode === 'wide' && !activeComboSpec.value);
const isSequentialStrikes = computed(
  () => !isWideAttack.value && attackActionSourceService.isSequentialStrikes(selectedSourceRule.value),
);
const isSameWeaponStrikes = computed(
  () => !isWideAttack.value && attackActionSourceService.isSameWeaponStrikes(selectedSourceRule.value),
);
const sharesLaunchTarget = computed(() => isSequentialStrikes.value || isSameWeaponStrikes.value);
const canAddSameWeaponSlot = computed(() => {
  if (!isSameWeaponStrikes.value) return false;
  const max = attackActionSourceService.maxWeapons(selectedSourceRule.value, actorVersion.value, props.rules);
  if (slots.value.length >= max) return false;

  return attackActionSourceService.hasUnusedSameWeaponCopy(
    compatibleProfiles.value,
    slots.value.flatMap((slot) => (slot.profile ? [slot.profile] : [])),
    slots.value[0]?.profile?.itemRuleCode,
  );
});
const canRemoveSameWeaponSlot = computed(
  () =>
    isSameWeaponStrikes.value && slots.value.length > attackActionSourceService.minWeapons(selectedSourceRule.value),
);
const processSteps = computed(() => {
  const process = selectedSource.value?.process;
  if (!process) return [];
  const session =
    activeProcess.value && actionRefEquals(selectedSource.value, activeProcess.value.processRuleCode, props.rules)
      ? activeProcess.value
      : null;

  return comboProcessService.visibleSteps(process, session);
});
const selectedProcessStep = computed(
  () => processSteps.value.find((step) => step.code === processStepCode.value) ?? null,
);
const compatibleProfiles = computed(() =>
  attackActionSourceService.compatibleProfiles(
    selectedSourceRule.value,
    actorOverview.value?.attacks ?? [],
    props.rules,
  ),
);
const pendingEffects = ref<Record<CombatEntityKey, PendingActionEffect[]>>({});
const actorSnapshot = computed(() =>
  lastStrikeService.snapshotOf(actorKey.value ? (pendingEffects.value[actorKey.value] ?? []) : []),
);
const followUpTargetKeys = computed(() =>
  lastStrikeService.followUpTargetKeys(selectedSourceRule.value, actorSnapshot.value),
);
const followUpExclude = computed(() =>
  lastStrikeService.excludeForSelect(selectedSourceRule.value, actorSnapshot.value, actorKey.value, [
    ...props.characters.map((membership) => `character:${membership.characterId}`),
    ...props.npcs.map((npc) => `npc:${npc.id}`),
  ]),
);
const canContinue = computed(() => {
  if (!selectedSource.value || !actorKey.value) return false;

  return slots.value.every(
    (slot) =>
      Boolean(slot.profile && slot.targetKey) &&
      lastStrikeService.canFollowUp(selectedSourceRule.value, actorSnapshot.value, slot.targetKey),
  );
});
const sourceItems = computed(() =>
  sources.value.map((source) => {
    const allowed = lastStrikeService.followUpTargetKeys(findRuleByRef(props.rules, source.code), actorSnapshot.value);

    return { ...source, disabled: allowed !== null && allowed.length === 0 };
  }),
);
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

  const raw = baseCost.value + resolution.actionCostDelta;
  const floor = asActionAbilitySpec(selectedSourceRule.value)?.min_total_action_cost;

  return floor == null ? raw : Math.max(floor, raw);
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
  return `${profile.inventoryItemId ?? profile.itemRuleCode}:${profile.instanceIndex ?? 0}:${profile.profileType}:${profile.profileIndex ?? 'legacy'}`;
}

function attackPreview(profile: AttackOverview): AttackOverview {
  const version = actorVersion.value;
  const reachDelta = actionEffectService.currentAttackReach(
    selectedSourceRule.value,
    profile.profileType,
    1,
    movementContextService.resolveMovementStep(version ?? undefined, props.rules),
  );
  let next = profile;
  if (version) {
    const delta = actionEffectService.currentAttackActionCharacteristicModifierForActor(
      selectedSourceRule.value,
      profile.profileType,
      version,
      profile,
      props.rules,
    );
    if (delta) {
      next =
        characterOverviewService.attackAtDistance(
          version,
          props.rules,
          profile.itemRuleCode,
          profile.profileType,
          0,
          profile.profileIndex,
          delta,
          profile.instanceIndex,
        ) ?? profile;
    }
  }
  if (!reachDelta) return next;

  return { ...next, reach: next.reach + reachDelta, distanceLabel: String(next.reach + reachDelta) };
}

function selectProfile(slotIndex: number, profile: AttackOverview): void {
  if (isSequentialStrikes.value && attackActionSourceService.requiresDistinctWeapons(selectedSourceRule.value)) {
    const clash = slots.value.some(
      (slot, index) =>
        index !== slotIndex &&
        slot.profile &&
        attackActionSourceService.weaponKey(slot.profile) === attackActionSourceService.weaponKey(profile),
    );
    if (clash) return;
  }
  if (isSameWeaponStrikes.value) {
    const clash = slots.value.some(
      (slot, index) =>
        index !== slotIndex &&
        slot.profile &&
        attackActionSourceService.weaponKey(slot.profile) === attackActionSourceService.weaponKey(profile),
    );
    if (clash) return;
    if (slotIndex === 0 && slots.value[0]?.profile?.itemRuleCode !== profile.itemRuleCode) {
      refillSameWeaponSlots(profile, slots.value.length);
      if (actorKey.value) {
        attackFavorites.setFavorite(actorKey.value, {
          itemRuleCode: profile.itemRuleCode,
          profileType: profile.profileType,
          profileIndex: profile.profileIndex ?? 0,
        });
      }
      profileMenuSlot.value = null;

      return;
    }
  }
  const nextSlots = [...slots.value];
  if (isWideAttack.value) {
    slots.value = nextSlots.map((slot) => ({ ...slot, profile }));
  } else {
    nextSlots[slotIndex] = { ...nextSlots[slotIndex], profile };
    slots.value = nextSlots;
  }
  if (actorKey.value) {
    attackFavorites.setFavorite(actorKey.value, {
      itemRuleCode: profile.itemRuleCode,
      profileType: profile.profileType,
      profileIndex: profile.profileIndex ?? 0,
    });
  }
  profileMenuSlot.value = null;
}

function defaultSlotTarget(): CombatEntityKey | null {
  if (comboLockedTarget.value) return comboLockedTarget.value;
  const allowed = followUpTargetKeys.value;
  if (allowed !== null) return (allowed[0] as CombatEntityKey | undefined) ?? null;
  const last = activeProcess.value?.lastStrikeTargetKey;
  if (last && targetOptions.value.some((option) => option.value === last)) return last;

  return targetOptions.value[0]?.value ?? null;
}

function slotRepeatHint(profile: AttackOverview | null): string | null {
  if (!profile || !activeProcess.value) return null;
  const modifiers = processSessionService.repeatWeaponModifiers(
    activeProcess.value,
    selectedSource.value?.process,
    attackActionSourceService.weaponKey(profile),
  );
  const delta = modifiers[0]?.delta ?? 0;
  if (!delta) return null;

  return `Помеха обстоятельств ${delta}: это оружие уже било в процессе ${-delta} раз`;
}

function defaultProfile(): AttackOverview | null {
  const favorite = favoriteAttack.value;
  if (isSameWeaponStrikes.value) {
    return attackActionSourceService.preferredSameWeaponLead(
      compatibleProfiles.value,
      attackActionSourceService.minWeapons(selectedSourceRule.value),
      attackActionSourceService.isProfileAvailable(favorite, compatibleProfiles.value) ? favorite : null,
    );
  }
  if (attackActionSourceService.isProfileAvailable(favorite, compatibleProfiles.value)) return favorite;

  return pushProfileService.preferredProfile(compatibleProfiles.value);
}

function slotProfiles(slotIndex: number): AttackOverview[] {
  if (isSameWeaponStrikes.value) {
    const used = slots.value.flatMap((slot, index) => (index === slotIndex || !slot.profile ? [] : [slot.profile]));
    if (slotIndex === 0) {
      return attackActionSourceService.profilesForOtherWeapons(
        compatibleProfiles.value,
        used,
        slots.value[0]?.profile ?? null,
      );
    }
    const itemRuleCode = slots.value[0]?.profile?.itemRuleCode;
    if (!itemRuleCode) return compatibleProfiles.value;

    return attackActionSourceService.profilesForSameWeapon(
      compatibleProfiles.value,
      used,
      slots.value[slotIndex]?.profile ?? null,
      itemRuleCode,
    );
  }
  if (!isSequentialStrikes.value || !attackActionSourceService.requiresDistinctWeapons(selectedSourceRule.value)) {
    return compatibleProfiles.value;
  }
  const used = slots.value.flatMap((slot, index) => (index === slotIndex || !slot.profile ? [] : [slot.profile]));

  return attackActionSourceService.profilesForOtherWeapons(
    compatibleProfiles.value,
    used,
    slots.value[slotIndex]?.profile ?? null,
  );
}

function resetSlots(profile: AttackOverview | null): void {
  const target = defaultSlotTarget();
  if (isWideAttack.value || (!isSequentialStrikes.value && !isSameWeaponStrikes.value)) {
    slots.value = [{ profile, targetKey: target }];

    return;
  }
  if (isSameWeaponStrikes.value) {
    refillSameWeaponSlots(profile, attackActionSourceService.minWeapons(selectedSourceRule.value));

    return;
  }
  const count = attackActionSourceService.strikeCount(selectedSourceRule.value);
  const used: AttackOverview[] = profile ? [profile] : [];
  slots.value = Array.from({ length: count }, (_, index) => {
    const next =
      index === 0 ? profile : attackActionSourceService.nextDistinctWeaponProfile(compatibleProfiles.value, used);
    if (index > 0 && next) used.push(next);

    return { profile: next, targetKey: target };
  });
}

function refillSameWeaponSlots(profile: AttackOverview | null, count: number): void {
  const target = defaultSlotTarget();
  if (!profile) {
    slots.value = Array.from({ length: count }, () => ({ profile: null, targetKey: target }));

    return;
  }
  const used: AttackOverview[] = [profile];
  slots.value = Array.from({ length: count }, (_, index) => {
    const next =
      index === 0
        ? profile
        : attackActionSourceService.nextSameWeaponProfile(compatibleProfiles.value, used, profile.itemRuleCode);
    if (index > 0 && next) used.push(next);

    return { profile: next, targetKey: target };
  });
}

function addSameWeaponSlot(): void {
  const lead = slots.value[0]?.profile;
  if (!lead || !isSameWeaponStrikes.value) return;
  const max = attackActionSourceService.maxWeapons(selectedSourceRule.value, actorVersion.value, props.rules);
  if (slots.value.length >= max) return;
  const next = attackActionSourceService.nextSameWeaponProfile(
    compatibleProfiles.value,
    slots.value.flatMap((slot) => (slot.profile ? [slot.profile] : [])),
    lead.itemRuleCode,
  );
  if (!next) return;
  slots.value = [...slots.value, { profile: next, targetKey: slots.value[0]?.targetKey ?? defaultSlotTarget() }];
}

function removeSameWeaponSlot(index: number): void {
  if (!isSameWeaponStrikes.value || index === 0) return;
  if (slots.value.length <= attackActionSourceService.minWeapons(selectedSourceRule.value)) return;
  slots.value = slots.value.filter((_, slotIndex) => slotIndex !== index);
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

function isAbortError(cause: unknown): boolean {
  return cause instanceof Error && cause.name === 'AbortError';
}

async function hydrate(): Promise<void> {
  loadAbort?.abort();
  const controller = new AbortController();
  loadAbort = controller;
  const generation = ++loadGeneration;
  loadPhase.value = 'loading';
  loadError.value = null;
  const api = getGameApi();
  const signal = controller.signal;
  try {
    const [nextOverlays, nextPending, nextProcesses, nextCommitted] = await Promise.all([
      api.getCombatOverlays(props.gameId, signal),
      api.getPendingActionEffects(props.gameId, signal),
      api.getProcessSessions(props.gameId, signal),
      api.getCommittedActionSessions(props.gameId, signal),
    ]);
    if (generation !== loadGeneration) return;
    const outcome = resolveLaunchLoad({
      overlays: { ok: true as const, value: nextOverlays },
      pending: { ok: true as const, value: nextPending },
      processes: { ok: true as const, value: nextProcesses },
      committed: { ok: true as const, value: nextCommitted },
    });
    if (outcome.status === 'error') {
      loadPhase.value = 'error';
      loadError.value = outcome.message;

      return;
    }
    overlays.value = outcome.values.overlays;
    pendingEffects.value = outcome.values.pending;
    processSessions.value = outcome.values.processes;
    committedSessions.value = outcome.values.committed;
    sourceRuleCode.value = sources.value[0]?.code ?? null;
    resetSlots(null);
    loadPhase.value = !actorKey.value || !actorVersion.value ? 'incomplete' : 'ready';
  } catch (cause) {
    if (generation !== loadGeneration || isAbortError(cause)) return;
    const outcome = resolveLaunchLoad({ overlays: { ok: false as const, cause } });
    if (outcome.status === 'error') {
      loadPhase.value = 'error';
      loadError.value = outcome.message;
    }
  }
}

async function stopProcess(): Promise<void> {
  const key = actorKey.value;
  const session = activeProcess.value;
  if (!key || !session) return;
  const processRule = findRuleByRef(props.rules, session.processRuleCode);
  const processSpec = processRule ? asProcessAbilitySpec(processRule) : null;
  const interrupt = processSessionService.planNormalInterrupt(
    processSpec,
    session.currentStepCode,
    processRule ?? null,
  );
  if (!interrupt.allowed) {
    error.value = 'Текущий процесс нельзя прервать обычным способом';

    return;
  }

  busy.value = true;
  error.value = null;
  try {
    await getGameApi().setProcessSession(props.gameId, key, null);
    const completionEffects = interrupt.completionEffects;
    const currentEffects = pendingEffects.value[key] ?? [];
    const nextEffects = [...currentEffects, ...completionEffects];
    pendingEffects.value = { ...pendingEffects.value, [key]: nextEffects };
    await getGameApi().setCombatActionEffects(props.gameId, key, nextEffects);
    emit('overlay-changed');
    if (props.chatId !== null) {
      const effectText = completionEffects.length
        ? ` Эффект: ${completionEffects.map((item) => formatProcessEffect(item.effect, props.rules)).join('; ')}.`
        : '';
      await sendChat(`${processRule?.name ?? 'Процесс'} прекращён.${effectText}`, [], props.chatId, speakerFor(key));
    }

    const nextSessions = { ...processSessions.value };
    delete nextSessions[key];
    processSessions.value = nextSessions;
    sourceRuleCode.value = sources.value[0]?.code ?? null;
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
  if (activeCommitted.value) throw new Error('Сначала закончи или сорви текущее действие');
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
    throw new Error('Удары этой атаки должны иметь одну общую цель');
  }
  if (isWideAttack.value && new Set(slots.value.map((slot) => slot.targetKey)).size !== slots.value.length) {
    throw new Error('Цели Широкого удара должны быть различными');
  }
  const attackStrikes: AttackActionStrike[] = slots.value.flatMap((slot) =>
    slot.profile && slot.targetKey ? [{ profile: slot.profile, targetKey: slot.targetKey }] : [],
  );
  if (attackStrikes.length !== slots.value.length) throw new Error('Не удалось собрать удары атаки');
  const distinctError = attackActionSourceService.validateDistinctWeapons(
    selectedSourceRule.value,
    attackStrikes.map((strike) => strike.profile),
  );
  if (distinctError) throw new Error(distinctError);
  const sameWeaponError = attackActionSourceService.validateSameWeapons(
    selectedSourceRule.value,
    attackStrikes.map((strike) => strike.profile),
  );
  if (sameWeaponError) throw new Error(sameWeaponError);
  if (lastStrikeService.requiresSingleStrike(selectedSourceRule.value) && attackStrikes.length !== 1) {
    throw new Error('Критический удар наносит один удар');
  }
  if (finalCost.value > actionPoints.value) throw new Error('Недостаточно ОД для атаки');
  if (
    attackStrikes.some(
      (strike) => !lastStrikeService.canFollowUp(selectedSourceRule.value, actorSnapshot.value, strike.targetKey),
    )
  ) {
    throw new Error(lastStrikeService.followUpBlockedMessage(selectedSourceRule.value));
  }
  const processSession =
    activeProcess.value ??
    (source.process ? processSessionService.start(props.gameId, initiator, source.code, source.process) : null);
  if (source.isProcess && !processSession) throw new Error('Не удалось создать сессию процесса');
  const processRule = activeProcess.value ? findRuleByRef(props.rules, activeProcess.value.processRuleCode) : null;
  const comboSpec =
    (processRule ? asProcessAbilitySpec(processRule) : null) ??
    (comboProcessService.isComboSpec(source.process) ? source.process : null) ??
    null;
  const boundSession =
    processSession && comboSpec
      ? comboProcessService.prepareStrike(
          processSession,
          comboSpec,
          attackStrikes.map((strike) => strike.targetKey),
        )
      : processSession;
  const processSource =
    source.isProcess && boundSession && selectedStepCode
      ? { kind: 'process' as const, process: { session: boundSession, stepCode: selectedStepCode } }
      : null;
  if (source.isProcess && !processSource) throw new Error('Не удалось определить шаг процесса');
  const comboClose =
    !source.isProcess &&
    boundSession &&
    comboProcessService.canCloseWithOtherAttack(boundSession, comboSpec, actorOverview.value, props.rules)
      ? { session: boundSession, stepCode: COMBO_STEP_CODES.finish, comboClose: true }
      : undefined;

  emit('launch-attack', {
    initiator,
    source: processSource ?? { kind: 'action', actionRuleCode: source.code },
    strikes: attackStrikes,
    mode: isWideAttack.value ? 'wide' : 'single',
    reactionMode: isSequentialStrikes.value ? 'sequential' : isSameWeaponStrikes.value ? 'paired' : 'simultaneous',
    totalOdCost: finalCost.value,
    ...(comboClose ? { comboClose } : {}),
    ...(selectedOptionalChildCodes.value.length
      ? { optionalChildAbilityCodes: [...selectedOptionalChildCodes.value] }
      : {}),
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
  selectedOptionalChildCodes.value = [];
  resetSlots(defaultProfile());
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
  if (isSequentialStrikes.value || isSameWeaponStrikes.value) return;
  const profile = defaultProfile();

  slots.value = slots.value.map((slot, index) => ({ ...slot, profile: index === 0 ? profile : null }));
});
watch(
  comboLockedTarget,
  (targetKey) => {
    if (!targetKey) return;
    slots.value = slots.value.map((slot) => ({ ...slot, targetKey }));
  },
  { immediate: true },
);
watch(
  () => slots.value[0]?.targetKey,
  (targetKey) => {
    if (!sharesLaunchTarget.value || targetKey == null) return;
    slots.value = slots.value.map((slot, index) => (index === 0 ? slot : { ...slot, targetKey }));
  },
);
watch(followUpTargetKeys, (allowed) => {
  if (allowed === null) return;
  const next = (allowed[0] as CombatEntityKey | undefined) ?? null;
  slots.value = slots.value.map((slot) => ({
    ...slot,
    targetKey: slot.targetKey && allowed.includes(slot.targetKey) ? slot.targetKey : next,
  }));
});
</script>

<template>
  <v-dialog :model-value="open" max-width="620" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title>Атака</v-card-title>
      <v-card-text>
        <div v-if="loadPhase === 'loading'" class="d-flex justify-center py-6">
          <v-progress-circular indeterminate />
        </div>
        <template v-else-if="loadPhase === 'error'">
          <v-alert type="error" variant="tonal" density="compact" class="mb-3">{{ loadError }}</v-alert>
          <v-btn variant="text" @click="hydrate">Повторить</v-btn>
        </template>
        <v-alert v-else-if="loadPhase === 'incomplete'" type="warning" variant="tonal" density="compact">
          Нет листа участника для этого действия.
        </v-alert>
        <template v-else>
          <v-alert v-if="activeCommitted" type="warning" variant="tonal" density="compact" class="mb-3">
            Сначала закончи или сорви незавершённое действие.
          </v-alert>
          <v-autocomplete
            v-model="sourceRuleCode"
            :items="sourceItems"
            :item-title="sourceTitle"
            item-value="code"
            label="Атака или процесс"
            :disabled="busy || !actorKey"
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item v-bind="itemProps" :title="sourceTitle(item.raw)" :disabled="item.raw.disabled" />
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
              <span class="text-subtitle-2">{{
                isWideAttack
                  ? `Цель ${index + 1}`
                  : isSameWeaponStrikes
                    ? `Экземпляр ${index + 1}`
                    : `Удар ${index + 1}`
              }}</span>
              <v-spacer />
              <v-btn
                v-if="(isWideAttack && index > 0) || (canRemoveSameWeaponSlot && index > 0)"
                icon="mdi-close"
                size="x-small"
                variant="text"
                :disabled="busy"
                :aria-label="isWideAttack ? 'Удалить цель' : 'Удалить экземпляр'"
                @click="isWideAttack ? removeTarget(index) : removeSameWeaponSlot(index)"
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
                    {{ attackPreview(slot.profile).accuracyLabel }} · {{ attackPreview(slot.profile).damageLabel }} ·
                    {{ attackPreview(slot.profile).penetrationLabel }}
                  </div>
                  <div v-if="slotRepeatHint(slot.profile)" class="text-caption text-warning">
                    {{ slotRepeatHint(slot.profile) }}
                  </div>
                </v-sheet>
              </template>
              <v-card min-width="420" max-width="560">
                <v-list density="compact">
                  <AttackProfileOption
                    v-for="profile in slotProfiles(index)"
                    :key="profileKey(profile)"
                    :attack="attackPreview(profile)"
                    :selected="slot.profile ? profileKey(slot.profile) === profileKey(profile) : false"
                    @select="selectProfile(index, profile)"
                  />
                  <v-list-item
                    v-if="slotProfiles(index).length === 0"
                    :title="
                      compatibleProfiles.length === 0
                        ? 'Подходящих профилей нет'
                        : isSameWeaponStrikes
                          ? 'Нужен ещё один экземпляр того же оружия'
                          : 'Нет другого оружия'
                    "
                  />
                </v-list>
              </v-card>
            </v-menu>
            <div v-else-if="slot.profile" class="text-body-2 text-medium-emphasis mb-2">
              Профиль: {{ slot.profile.itemName }} · {{ slot.profile.profileTypeLabel }}
            </div>
            <CombatEntitySelect
              v-if="!sharesLaunchTarget || index === 0"
              v-model="slot.targetKey"
              label="Цель удара"
              :characters="characters"
              :npcs="npcs"
              :initiative-keys="initiativeKeys"
              :exclude="followUpExclude"
              :disabled="
                busy || Boolean(comboLockedTarget) || (followUpTargetKeys !== null && followUpTargetKeys.length <= 1)
              "
            />
            <div v-else class="text-caption text-medium-emphasis">Та же цель, что у первого удара</div>
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
          <v-btn
            v-if="canAddSameWeaponSlot"
            variant="outlined"
            size="small"
            class="mb-2"
            :disabled="busy || !slots[0]?.profile"
            @click="addSameWeaponSlot"
          >
            + Экземпляр
          </v-btn>

          <v-checkbox
            v-for="option in afterStrikeOptions"
            :key="option.rule.code"
            :model-value="selectedOptionalChildCodes.includes(option.rule.code)"
            :label="option.rule.name"
            density="compact"
            hide-details
            class="mt-1"
            @update:model-value="
              (on) =>
                (selectedOptionalChildCodes = on
                  ? [...selectedOptionalChildCodes, option.rule.code]
                  : selectedOptionalChildCodes.filter((code) => code !== option.rule.code))
            "
          />
          <div v-if="launchEffectLines.length" class="text-body-2 text-medium-emphasis mt-2">
            <div v-for="(line, index) in launchEffectLines" :key="index">
              {{ line }}
            </div>
          </div>
          <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-3">{{ error }}</v-alert>
        </template>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="emit('update:open', false)">Отмена</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="loadPhase !== 'ready' || !canContinue" @click="submitSafe">
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
