<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';

import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ProcessActionContext } from '@/modules/Roleplay/Game/Dto/ProcessActionContext';
import type { AttackAction } from '@/modules/Roleplay/Game/Dto/AttackAction';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { CHECK_HIT_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import {
  EXHAUSTION_STATE_CODE,
  STUNNED_STATE_CODE,
  WOUND_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';

import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';

import type { HitCheckRoll } from '@/modules/Roleplay/Game/Dto/HitCheckRoll';
import type { HitRollInput } from '@/modules/Roleplay/Game/Dto/HitRollInput';
import { hitRollService } from '@/modules/Roleplay/Game/Service/Instance/hitRollService';

import { resolveHitProcedure } from '@/modules/Roleplay/Game/Utils/resolveStrikeProcedure';
import { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/init';
import { weaponAttackRangeService } from '@/modules/Roleplay/Character/init';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';

import { DEFAULT_ATTACK_AP } from '@/modules/Roleplay/Game/Constant/Combat/DEFAULT_ATTACK_AP';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { characteristicSizeByCode } from '@/modules/Roleplay/Game/Utils/strikeCharacteristicMods';
import { formatProcessEffect } from '@/modules/Roleplay/Game/Utils/processMessage';

import {
  buildAttackCalcPayload,
  formatAttackActionMessage,
  formatAttackResultMessage,
  formatStrikeNarrativeMessage,
} from '@/modules/Roleplay/Game/Utils/attackDamageMessage';
import {
  SIMPLE_MELEE_ATTACK_CODE,
  SIMPLE_RANGED_ATTACK_CODE,
  attackActionById,
  listAttackActions,
  reactionAction,
  defenseOdCost,
  turnAction,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';

import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { asProcessAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';

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
  attackerKey: CombatEntityKey | null;
  attack: AttackOverview | null;
  resumeOffer: CheckOffer | null;
  processContext?: ProcessActionContext | null;
  attackAction?: AttackAction | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  settled: [];
  offered: [];
  'overlay-changed': [];
}>();

const combatThread = useCombatChatThread(() => props.gameId);
const sendChat = combatChatSendService.sendCombatChat(props.gameId);
const overlays = ref<GameCombatOverlay[]>([]);
const pendingEffectsByEntity = ref<Record<CombatEntityKey, PendingActionEffect[]>>({});
const offer = ref<CheckOffer | null>(null);
const opponentKey = ref<CombatEntityKey | null>(null);
const reaction = ref<HitDefenseReaction | null>(null);
const attackerAdv = ref(0);
const defenderAdv = ref(0);
const defenseEfficiency = ref<DimensionalNumberValue>({ base: 4, size: -1 });
const blockItemRuleId = ref<string | null>(null);
const selectedActionRuleId = ref<string | null>(null);
const distanceIpari = ref(1);
const cover = ref(0);
const flank = ref(false);
const turn = ref(false);
const agreedInitiatorAdv = ref(0);
const agreedOpponentAdv = ref(0);
const agreedCover = ref(0);
const busy = ref(false);
const error = ref<string | null>(null);

const procedure = computed(() =>
  resolveHitProcedure(currentAttack.value?.profileType ?? 'strike', props.rules, props.mechanics),
);

const resolvedAttackerKey = computed(() => offer.value?.initiator ?? props.attackerKey);

const entityOptions = computed(() => {
  const items: { value: CombatEntityKey; title: string }[] = [];
  for (const membership of props.characters) {
    if (membership.membershipStatus !== 'approved') continue;
    items.push({ value: `character:${membership.characterId}`, title: membership.characterName });
  }
  for (const npc of props.npcs) {
    if (npc.status !== 'active') continue;
    items.push({ value: `npc:${npc.id}`, title: npc.name });
  }

  return items;
});

const opponentOptions = computed(() => entityOptions.value.filter((item) => item.value !== resolvedAttackerKey.value));

const isCompose = computed(() => offer.value === null);
const resolvedAttackAction = computed(() => offer.value?.proposal.attackAction ?? props.attackAction ?? null);

const speakerEntity = computed<CombatEntityKey | null>(() => {
  const key = props.activeSpeakerKey;
  if (key === null || key === 'gm') return null;

  return key as CombatEntityKey;
});

function actingEntity(current: CheckOffer): CombatEntityKey | null {
  if (props.canEdit) return current.waitingOn === 'opponent' ? current.opponent : current.initiator;
  if (speakerEntity.value) return speakerEntity.value;

  return null;
}

function actorRole(current: CheckOffer): 'initiator' | 'opponent' | null {
  const actor = actingEntity(current);
  if (actor === current.initiator) return 'initiator';
  if (actor === current.opponent) return 'opponent';

  return null;
}

const myTurn = computed(() => {
  const current = offer.value;
  if (!current || current.status !== 'pending') return false;
  const actor = actorRole(current);

  return actor !== null && current.waitingOn === actor;
});

const isDefenderStep = computed(() => offer.value !== null && offer.value.waitingOn === 'opponent' && myTurn.value);

const isWaiting = computed(() => offer.value !== null && offer.value.status === 'pending' && !myTurn.value);

function nameOf(key: CombatEntityKey | null): string {
  if (!key) return '';

  return combatCardModelService.combatEntityName(key, props.characters, props.npcs);
}

function versionOf(key: CombatEntityKey | null): CharacterVersion | null {
  if (!key) return null;
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

function overviewOf(key: CombatEntityKey | null): CharacterOverview | null {
  const version = versionOf(key);
  if (!version) return null;

  return characterOverviewService.build(version, props.rules);
}

function attackFromOffer(current: CheckOffer): AttackOverview | null {
  const hit = current.proposal.hit;
  if (!hit) return props.attack;

  return {
    itemRuleId: hit.itemRuleId,
    itemName: hit.itemName,
    itemHref: '',
    profileType: hit.profileType,
    profileTypeLabel: hit.profileType,
    distanceLabel: '',
    minDistance: props.attack?.minDistance ?? 0,
    reach: hit.reach ?? props.attack?.reach ?? 0,
    falloff: hit.falloff ?? props.attack?.falloff ?? { base: 5, size: 0 },
    accuracyLabel: '',
    accuracy: hit.accuracy,
    damageLabel: '',
    penetrationLabel: '',
    damageFormula: '',
    penetrationFormula: '',
    isResolved: true,
    damageTypeCode: hit.damageTypeCode ?? props.attack?.damageTypeCode ?? null,
    damage: hit.damage ?? props.attack?.damage ?? { base: 0, size: 0 },
    penetration: hit.penetration ?? props.attack?.penetration ?? { base: 0, size: 0 },
  };
}

const currentAttack = computed(() =>
  offer.value ? attackFromOffer(offer.value) : (resolvedAttackAction.value?.strikes[0]?.profile ?? props.attack),
);

const isRanged = computed(
  () => currentAttack.value?.profileType === 'throw' || currentAttack.value?.profileType === 'shoot',
);

const canEditCover = computed(() => isCompose.value || myTurn.value);

const ignoreDifficultyPreview = computed(() => {
  if (!isRanged.value) return procedure.value.ignoreDefense;

  return weaponAttackRangeService.rangedHitDifficulty(
    cover.value,
    0,
    distanceIpari.value,
    currentAttack.value?.falloff ?? DEFAULT_FALLOFF,
  );
});

const resolvedAttack = computed(() => {
  const attack = currentAttack.value;
  if (!attack || !isRanged.value) return attack;
  const version = versionOf(resolvedAttackerKey.value);
  if (!version) return attack;

  return (
    characterOverviewService.attackAtDistance(
      version,
      props.rules,
      attack.itemRuleId,
      attack.profileType,
      distanceIpari.value,
      attack.profileIndex,
    ) ?? attack
  );
});

const attackOptions = computed(() =>
  listAttackActions(props.rules, overviewOf(resolvedAttackerKey.value), currentAttack.value?.profileType ?? 'strike'),
);

const fixedActionRuleId = computed(() =>
  resolvedAttackAction.value?.source.kind === 'action' ? resolvedAttackAction.value.source.actionRuleId : null,
);
const selectedAction = computed(
  () =>
    attackActionById(props.rules, fixedActionRuleId.value ?? selectedActionRuleId.value) ??
    attackOptions.value[0] ??
    null,
);
const isPreparationAction = computed(() => selectedAction.value?.isAttack === false);
const effectiveProcessContext = computed(
  () =>
    props.processContext ??
    (resolvedAttackAction.value?.source.kind === 'process' ? resolvedAttackAction.value.source.process : null),
);
const processSpec = computed(() => {
  const rule = effectiveProcessContext.value
    ? props.rules.find((item) => item.id === effectiveProcessContext.value?.session.processRuleId)
    : undefined;

  return rule ? asProcessAbilitySpec(rule) : null;
});
const processStep = computed(() =>
  effectiveProcessContext.value && processSpec.value
    ? (processSpec.value.steps.find((step) => step.code === effectiveProcessContext.value?.stepCode) ?? null)
    : null,
);
const processStepCost = computed(() =>
  processStep.value ? processSessionService.stepCost(processStep.value, ACTION_POINTS_CODE) : null,
);

const attackerPendingEffects = computed(
  () => (resolvedAttackerKey.value ? pendingEffectsByEntity.value[resolvedAttackerKey.value] : undefined) ?? [],
);

const resolvedSelectedAction = computed(() => {
  const action = selectedAction.value;
  if (!action) return null;
  const resolution = actionEffectService.resolveForNextAction(attackerPendingEffects.value, {
    isAttack: selectedAction.value?.isAttack ?? true,
    component: currentAttack.value?.profileType ?? 'strike',
    baseCost: processStepCost.value ?? action.odCost,
  });

  return { ...action, odCost: (processStepCost.value ?? action.odCost) + resolution.actionCostDelta };
});

function remainingAp(key: CombatEntityKey | null): number {
  const overview = overviewOf(key);
  if (!overview) return 0;
  const resource = attackDamageService.actionPointsResource(overview, props.rules);

  return resource ? Math.max(0, resource.current.base) : 0;
}

const attackerAp = computed(() => remainingAp(resolvedAttackerKey.value));
const defenderAp = computed(() => remainingAp(opponentKey.value));

const attackSelectItems = computed(() =>
  attackOptions.value.map((option) => ({
    ...option,
    title: `${option.name} · ${
      option.ruleId === resolvedSelectedAction.value?.ruleId ? resolvedSelectedAction.value.odCost : option.odCost
    } ОД`,
    props: {
      disabled:
        (option.ruleId === resolvedSelectedAction.value?.ruleId ? resolvedSelectedAction.value.odCost : option.odCost) >
        attackerAp.value,
    },
  })),
);

const advantageDirty = computed(() => {
  if (!offer.value) return false;
  if (attackerAdv.value !== agreedInitiatorAdv.value || defenderAdv.value !== agreedOpponentAdv.value) return true;
  if (isRanged.value && cover.value !== agreedCover.value) return true;

  return false;
});

const dodgeAction = computed(() => reactionAction(props.rules, 'dodge'));
const blockAction = computed(() => reactionAction(props.rules, 'block'));
const turnAbility = computed(() => turnAction(props.rules));
const reactionCostLimit = computed(
  () =>
    resolvedAttackAction.value?.totalOdCost ??
    processStepCost.value ??
    resolvedSelectedAction.value?.odCost ??
    DEFAULT_ATTACK_AP,
);

const dodgeAffordable = computed(() => {
  const extra = flank.value && turn.value ? turnAbility.value.odCost : 0;

  return (dodgeAction.value?.odCost ?? 1) + extra <= Math.min(defenderAp.value, reactionCostLimit.value);
});
const blockAffordable = computed(() => {
  const extra = flank.value && turn.value ? turnAbility.value.odCost : 0;

  return (blockAction.value?.odCost ?? 2) + extra <= Math.min(defenderAp.value, reactionCostLimit.value);
});

watch(attackOptions, (options) => {
  if (selectedActionRuleId.value && options.some((item) => item.ruleId === selectedActionRuleId.value)) return;
  const preferredCode =
    currentAttack.value?.profileType === 'strike' ? SIMPLE_MELEE_ATTACK_CODE : SIMPLE_RANGED_ATTACK_CODE;
  selectedActionRuleId.value =
    options.find((item) => item.code === preferredCode)?.ruleId ?? options[0]?.ruleId ?? null;
});

const blockProfiles = computed(() =>
  hitRollService.listBlockProfiles(versionOf(opponentKey.value), props.rules, { shieldsOnly: isRanged.value }),
);

const canBlock = computed(() => blockProfiles.value.length > 0);

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

function processContinueToken(key: CombatEntityKey): string {
  const [entityType, entityId] = key.split(':');

  return entityType && entityId ? `[[process-continue:continue,${entityType},${entityId}]]` : '';
}

function applyReactionDefaults(next: HitDefenseReaction | null): void {
  if (next === 'dodge') {
    defenseEfficiency.value = { ...procedure.value.dodgeEfficiency };
    blockItemRuleId.value = null;
  }
  if (next === 'block') {
    const first = blockProfiles.value[0] ?? null;
    blockItemRuleId.value = first?.itemRuleId ?? null;
    defenseEfficiency.value = first ? { ...first.efficiency } : { ...procedure.value.minBlockEfficiency };
  }
}

watch(reaction, (next, prev) => {
  if (next === prev || !isDefenderStep.value) return;
  applyReactionDefaults(next);
});

watch(blockItemRuleId, (id) => {
  if (!isDefenderStep.value || reaction.value !== 'block' || !id) return;
  const profile = blockProfiles.value.find((item) => item.itemRuleId === id);
  if (profile) defenseEfficiency.value = { ...profile.efficiency };
});

async function hydrate(): Promise<void> {
  error.value = null;
  const api = getGameApi();
  const [nextOverlays, nextPendingEffects] = await Promise.all([
    api.getCombatOverlays(props.gameId).catch(() => []),
    api.getPendingActionEffects(props.gameId).catch(() => ({})),
  ]);
  overlays.value = nextOverlays;
  pendingEffectsByEntity.value = nextPendingEffects;
  if (props.resumeOffer) {
    offer.value = props.resumeOffer;
    opponentKey.value = props.resumeOffer.opponent;
    const hit = props.resumeOffer.proposal.hit;
    reaction.value = hit?.reaction ?? null;
    attackerAdv.value = props.resumeOffer.proposal.initiatorAdv;
    defenderAdv.value = props.resumeOffer.proposal.opponentAdv;
    agreedInitiatorAdv.value = props.resumeOffer.proposal.initiatorAdv;
    agreedOpponentAdv.value = props.resumeOffer.proposal.opponentAdv;
    agreedCover.value = Math.max(0, hit?.cover ?? 0);
    blockItemRuleId.value = hit?.blockItemRuleId ?? null;
    selectedActionRuleId.value = hit?.actionRuleId ?? fixedActionRuleId.value ?? selectedActionRuleId.value;
    distanceIpari.value = hit?.distanceIpari ?? props.attack?.minDistance ?? 1;
    cover.value = Math.max(0, hit?.cover ?? 0);
    flank.value = hit?.flank ?? false;
    turn.value = hit?.turn ?? false;
    if (hit?.defenseEfficiency) {
      defenseEfficiency.value = { ...hit.defenseEfficiency };
    } else {
      applyReactionDefaults(reaction.value);
    }

    return;
  }
  offer.value = null;
  opponentKey.value = resolvedAttackAction.value?.strikes[0]?.targetKey ?? opponentOptions.value[0]?.value ?? null;
  reaction.value = null;
  attackerAdv.value = 0;
  defenderAdv.value = 0;
  agreedInitiatorAdv.value = 0;
  agreedOpponentAdv.value = 0;
  agreedCover.value = 0;
  blockItemRuleId.value = null;
  distanceIpari.value = Math.max(1, props.attack?.minDistance ?? 1);
  cover.value = 0;
  flank.value = false;
  turn.value = false;
  defenseEfficiency.value = { ...procedure.value.dodgeEfficiency };
}

watch(
  () => props.open,
  async (open) => {
    if (open) await hydrate();
  },
);

function close(): void {
  emit('update:open', false);
}

function hitProposal(attack: AttackOverview, nextReaction: HitDefenseReaction | null): CheckOfferProposal['hit'] {
  const preview = resolvedAttack.value ?? attack;

  return {
    itemRuleId: preview.itemRuleId,
    itemName: preview.itemName,
    profileType: preview.profileType,
    profileIndex: preview.profileIndex,
    accuracy: preview.accuracy,
    reaction: nextReaction,
    defenseEfficiency: nextReaction === 'ignore' || nextReaction === null ? null : defenseEfficiency.value,
    blockItemRuleId: nextReaction === 'block' ? blockItemRuleId.value : null,
    damageTypeCode: preview.damageTypeCode,
    damage: preview.damage,
    penetration: preview.penetration,
    actionRuleId: effectiveProcessContext.value?.session.processRuleId ?? resolvedSelectedAction.value?.ruleId ?? null,
    actionName: effectiveProcessContext.value
      ? `${props.rules.find((rule) => rule.id === effectiveProcessContext.value?.session.processRuleId)?.name ?? 'Процесс'} · ${processStep.value?.name ?? ''}`
      : resolvedSelectedAction.value?.name,
    actionOd: processStepCost.value ?? resolvedSelectedAction.value?.odCost,
    distanceIpari: isRanged.value ? distanceIpari.value : null,
    cover: isRanged.value ? Math.max(0, cover.value) : undefined,
    reach: preview.reach,
    falloff: preview.falloff,
    flank: flank.value,
    turn: nextReaction === 'ignore' || nextReaction === null ? false : turn.value && flank.value,
  };
}

async function sendOffer(): Promise<void> {
  const attack = resolvedAttack.value;
  const initiator = resolvedAttackerKey.value;
  if (!attack || !initiator || !opponentKey.value) throw new Error('Выберите цель');
  if (!selectedAction.value) throw new Error('Выберите действие атаки');
  if (isRanged.value && !(distanceIpari.value > 0)) throw new Error('Укажите дистанцию в ипари');
  const attackerApCost = resolvedSelectedAction.value?.odCost || DEFAULT_ATTACK_AP;
  if (attackerApCost > remainingAp(initiator)) throw new Error('Недостаточно ОД для атаки');
  await getGameApi().createCheckOffer(props.gameId, {
    checkCode: CHECK_HIT_CODE,
    initiator,
    opponent: opponentKey.value,
    proposal: {
      initiatorCharacteristic: null,
      opponentCharacteristic: null,
      initiatorAdv: attackerAdv.value,
      opponentAdv: 0,
      attackAction: resolvedAttackAction.value,
      hit: hitProposal(attack, null),
    },
  });
}

async function performPreparation(): Promise<void> {
  const initiator = resolvedAttackerKey.value;
  const action = selectedAction.value;
  if (!initiator || !action || action.isAttack) throw new Error('Выберите подготовительное действие');

  const pendingResolution = actionEffectService.resolveForNextAction(attackerPendingEffects.value, {
    isAttack: false,
    component: 'strike',
    baseCost: action.odCost,
  });
  const spent = await spendAp(initiator, action.odCost);
  if (spent < action.odCost) throw new Error('Недостаточно ОД для действия');

  const nextEffects = [
    ...actionEffectService.consumeResource(pendingResolution.remainingEffects, ACTION_POINTS_CODE, action.odCost),
    ...actionEffectService.effectsAfterAction(props.rules.find((rule) => rule.id === action.ruleId)),
  ];
  pendingEffectsByEntity.value = { ...pendingEffectsByEntity.value, [initiator]: nextEffects };
  await getGameApi().setCombatActionEffects(props.gameId, initiator, nextEffects);
  if (props.chatId !== null) {
    await sendChat(
      `${formatAttackActionMessage({
        attackerKey: initiator,
        attackerName: nameOf(initiator),
        action,
        attackerAp: spent,
        rules: props.rules,
      })}${action.effects?.length ? `\nЭффекты: ${action.effects.map((effect) => actionEffectService.describe(effect)).join('; ')}` : ''}`,
      [],
      props.chatId,
      speakerFor(initiator),
    );
  }
}

function defenderProposal(): CheckOfferProposal {
  const current = offer.value;
  const attack = resolvedAttack.value;
  if (!current || !attack) throw new Error('Нет оферты');
  if (!reaction.value) throw new Error('Выберите игнор, уклон или блок');
  if (reaction.value === 'block' && !blockItemRuleId.value) throw new Error('Выберите профиль блока');
  const turned = Boolean(flank.value && turn.value && reaction.value !== 'ignore');
  const reactionCost = defenseOdCost(reaction.value, turned, props.rules);
  if (reactionCost > remainingAp(current.opponent)) throw new Error('Недостаточно ОД для реакции');
  if (reactionCost > reactionCostLimit.value) throw new Error('Реакция не может стоить дороже атаки');

  return {
    ...current.proposal,
    initiatorAdv: attackerAdv.value,
    opponentAdv: defenderAdv.value,
    hit: hitProposal(attack, reaction.value),
  };
}

async function revise(): Promise<void> {
  const current = offer.value;
  const actor = current ? actingEntity(current) : null;
  if (!current || !actor) throw new Error('Нет оферты');
  offer.value = await getGameApi().reviseCheckOffer(current.id, actor, defenderProposal());
}

async function acceptAndRoll(): Promise<void> {
  const current = offer.value;
  const attack = resolvedAttack.value;
  const actor = current ? actingEntity(current) : null;
  if (!current || !attack || !actor) throw new Error('Нет оферты');
  const proposal = defenderProposal();
  const accepted = await getGameApi().acceptCheckOffer(current.id, actor, proposal);
  const hit = accepted.proposal.hit;
  if (!hit?.reaction) throw new Error('Защитник ещё не выбрал реакцию');
  const actionRule = props.rules.find((rule) => rule.id === hit.actionRuleId);
  const rawAction = attackActionById(props.rules, hit.actionRuleId);
  const pendingResolution = actionEffectService.resolveForNextAction(attackerPendingEffects.value, {
    isAttack: true,
    component: hit.profileType,
    baseCost: rawAction?.odCost ?? hit.actionOd ?? DEFAULT_ATTACK_AP,
    targetDexterityMastery: characteristicSizeByCode(overviewOf(accepted.opponent), props.rules, 'dexterity') ?? 0,
  });
  const commonHitInput: Omit<HitRollInput, 'attack'> = {
    attackerLabel: nameOf(accepted.initiator),
    defenderLabel: nameOf(accepted.opponent),
    attackerKey: accepted.initiator,
    defenderKey: accepted.opponent,
    attackerOverview: overviewOf(accepted.initiator),
    defenderOverview: overviewOf(accepted.opponent),
    reaction: hit.reaction,
    defenseEfficiency: hit.defenseEfficiency,
    attackerAdv:
      accepted.proposal.initiatorAdv +
      stateRuntimeEffectsService.checkAdvantageFromStates(versionOf(accepted.initiator), props.rules, {
        kind: 'hit',
      }) +
      actionEffectService.checkAdvantageDelta(attackerPendingEffects.value, CHECK_HIT_CODE),
    defenderAdv:
      accepted.proposal.opponentAdv +
      stateRuntimeEffectsService.checkAdvantageFromStates(versionOf(accepted.opponent), props.rules, { kind: 'hit' }),
    accuracyDelta: actionEffectService.currentAttackAccuracy(actionRule, hit.profileType),
    defenderDexterityMasteryDelta: pendingResolution.targetDexterityMasteryDelta,
    defenderMasteryAdjustments: pendingResolution.targetDexterityMasteryAdjustments.map((adjustment) => ({
      source_code: 'action-effect',
      source_label: props.rules.find((rule) => rule.id === adjustment.sourceRuleId)?.name ?? 'Временный эффект',
      delta: adjustment.delta,
    })),
    distanceIpari: hit.distanceIpari,
    cover: hit.cover,
    flank: hit.flank,
    turn: hit.turn,
  };
  const attackStrikes = accepted.proposal.attackAction?.strikes ?? [];
  const rollInputs = (attackStrikes.length > 0 ? attackStrikes : [{ profile: attack }]).map((strike) => ({
    ...commonHitInput,
    attack: {
      itemName: strike.profile.itemName,
      profileType: strike.profile.profileType,
      accuracy: strike.profile.accuracy,
      reach: strike.profile.reach,
      falloff: strike.profile.falloff,
    },
  }));
  const simultaneous = hitRollService.rollSimultaneousHits(rollInputs, Math.random, props.rules, props.mechanics);
  const rolled = { attacker: simultaneous.attackers[0], defender: simultaneous.defender };
  const nextProcessSession =
    effectiveProcessContext.value && processSpec.value
      ? processSessionService.resolveStep(
          effectiveProcessContext.value.session,
          processSpec.value,
          effectiveProcessContext.value.stepCode,
          simultaneous.attackers.every((attacker) => (attacker.check?.rating ?? 0) > 0),
        )
      : null;
  if (effectiveProcessContext.value) {
    await getGameApi().setProcessSession(props.gameId, accepted.initiator, nextProcessSession);
  }
  const processRule = effectiveProcessContext.value
    ? props.rules.find((rule) => rule.id === effectiveProcessContext.value?.session.processRuleId)
    : null;
  const processCompletionEffects = nextProcessSession ? [] : actionEffectService.effectsAfterProcess(processRule);
  await applyClickAttack(accepted, hit, rolled.attacker.check?.rating ?? 0, attack, rolled);
  for (const [index, strike] of attackStrikes.entries()) {
    if (index === 0) continue;
    const strikeHit = {
      ...hit,
      itemRuleId: strike.profile.itemRuleId,
      itemName: strike.profile.itemName,
      profileType: strike.profile.profileType,
      accuracy: strike.profile.accuracy,
      damage: strike.profile.damage,
      damageTypeCode: strike.profile.damageTypeCode,
      reach: strike.profile.reach,
      falloff: strike.profile.falloff,
    };
    await applyClickAttack(
      accepted,
      strikeHit,
      simultaneous.attackers[index].check?.rating ?? 0,
      strike.profile,
      { attacker: simultaneous.attackers[index], defender: null },
      { spendResources: false, announce: false },
    );
  }
  const finalAttackCost =
    resolvedAttackAction.value?.totalOdCost ??
    (rawAction?.odCost ?? hit.actionOd ?? DEFAULT_ATTACK_AP) + pendingResolution.actionCostDelta;
  const nextEffects = [
    ...actionEffectService.consumeResource(pendingResolution.remainingEffects, ACTION_POINTS_CODE, finalAttackCost),
    ...actionEffectService.effectsAfterAction(actionRule),
    ...processCompletionEffects,
  ];
  pendingEffectsByEntity.value = { ...pendingEffectsByEntity.value, [accepted.initiator]: nextEffects };
  await getGameApi().setCombatActionEffects(props.gameId, accepted.initiator, nextEffects);
  if (props.chatId !== null && effectiveProcessContext.value) {
    const processName = processRule?.name ?? 'Процесс';
    const effectText = processCompletionEffects.length
      ? ` Эффект: ${processCompletionEffects.map((item) => formatProcessEffect(item.effect, props.rules)).join('; ')}.`
      : '';
    const processToken = processRule ? `[[rule:${processRule.code}]]` : processName;
    const processMessage = nextProcessSession
      ? `Процесс ${processToken} можно продолжить: ${processContinueToken(accepted.initiator)}.`
      : `Процесс ${processToken} завершён.${effectText}`;
    await sendChat(processMessage, [], props.chatId, speakerFor(accepted.initiator));
  }
  offer.value = accepted;
}

async function spendAp(key: CombatEntityKey, cost: number): Promise<number> {
  if (cost <= 0) return 0;
  const overview = overviewOf(key);
  if (!overview) return 0;
  const resource = attackDamageService.actionPointsResource(overview, props.rules);
  if (!resource) return 0;
  const spent = Math.min(cost, Math.max(0, resource.current.base));
  const next = attackDamageService.spendActionPoints(resource.current, spent);
  const overlay = await getGameApi().setCombatResource(props.gameId, key, resource.ruleId, next);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
  await getGameApi().setCurrentSpeed(props.gameId, key, {
    horizontal: { stepsPerActionPoint: 0, direction: null },
    vertical: { stepsPerActionPoint: 0, direction: null },
  });

  return spent;
}

async function applyCombatState(key: CombatEntityKey, code: string, amount: number): Promise<void> {
  if (amount <= 0) return;
  const rule = props.rules.find((item) => item.code === code && item.type === 'state');
  if (!rule) return;
  const independent = (rule.spec as StateSpec | undefined)?.aggregation === 'independent';
  const states = versionOf(key)?.states ?? [];
  const index = states.findIndex((state) => state.stateRuleId === rule.id);
  const overlay =
    !independent && index >= 0
      ? await getGameApi().setCombatStateValue(props.gameId, key, index, (states[index]?.value ?? 0) + amount)
      : await getGameApi().addCombatState(props.gameId, key, {
          stateRuleId: rule.id,
          value: amount,
        } as CharacterStateValue);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
}

async function applyClickAttack(
  accepted: CheckOffer,
  hit: NonNullable<CheckOfferProposal['hit']>,
  sr: number,
  attack: AttackOverview,
  rolled: HitCheckRoll,
  options: { spendResources?: boolean; announce?: boolean } = {},
): Promise<void> {
  const hooks = damageTypeHooksService.resolveDamageTypeHooks(attack.damageTypeCode, props.rules, props.mechanics);
  const defenderOverview = overviewOf(accepted.opponent);
  const typeRule = attack.damageTypeCode
    ? props.rules.find((rule) => rule.code === attack.damageTypeCode && rule.type === 'damage_type')
    : undefined;
  const defenseIgnored = damageTypeSpecService.asDamageTypeSpec(typeRule)?.defense_ignored === true;
  const result = attackDamageService.applyAttackDamage({
    weaponDamage: hit.damage ?? attack.damage,
    sr: Math.max(0, sr),
    damageTypeCode: attack.damageTypeCode,
    defense: defenderOverview?.defense ?? null,
    endurance: defenderOverview ? attackDamageService.enduranceOf(defenderOverview, props.rules) : 1,
    hooks,
    defenseIgnored,
  });
  const action = resolvedSelectedAction.value ??
    attackActionById(props.rules, hit.actionRuleId) ?? {
      ruleId: hit.actionRuleId ?? '',
      code: '',
      name: hit.actionName ?? 'Простая атака',
      odCost: hit.actionOd ?? DEFAULT_ATTACK_AP,
    };
  const processRule = hit.actionRuleId ? props.rules.find((rule) => rule.id === hit.actionRuleId) : null;
  const processSpec = processRule ? asProcessAbilitySpec(processRule) : null;
  const processStepName =
    processStep.value?.name ??
    (processSpec
      ? processSpec.steps.find((step) => step.code === (processSpec.start_step_code ?? processSpec.steps[0]?.code))
          ?.name
      : null);
  const actionForProcess =
    effectiveProcessContext.value || processSpec
      ? {
          ...action,
          ruleId: processRule?.id ?? action.ruleId,
          code: processRule?.code ?? action.code,
          name: `${processRule?.name ?? 'Процесс'} · ${processStepName ?? ''}`,
        }
      : action;
  const attackerAp = resolvedAttackAction.value?.totalOdCost ?? (actionForProcess.odCost || DEFAULT_ATTACK_AP);
  const defenderAp = defenseOdCost(hit.reaction, Boolean(hit.turn && hit.flank), props.rules);
  const shouldSpendResources = options.spendResources !== false;
  const shouldAnnounce = options.announce !== false;
  const spentAttack = shouldSpendResources ? await spendAp(accepted.initiator, attackerAp) : 0;
  const spentDefense = shouldSpendResources ? await spendAp(accepted.opponent, defenderAp) : 0;
  await applyCombatState(accepted.opponent, EXHAUSTION_STATE_CODE, result.exhaustion);
  await applyCombatState(accepted.opponent, WOUND_STATE_CODE, (result.wound ?? 0) + (result.cuttingWound ?? 0));
  await applyCombatState(accepted.opponent, STUNNED_STATE_CODE, result.stun ?? 0);
  emit('overlay-changed');
  const speaker = speakerFor(accepted.initiator);
  if (props.chatId !== null && shouldAnnounce) combatThread.beginAttack();
  try {
    if (props.chatId !== null && shouldAnnounce) {
      await sendChat(
        `${formatAttackActionMessage({
          attackerKey: accepted.initiator,
          attackerName: nameOf(accepted.initiator),
          action: actionForProcess,
          attackerAp: spentAttack,
          rules: props.rules,
        })}${action.effects?.length ? `\nЭффекты: ${action.effects.map((effect) => actionEffectService.describe(effect)).join('; ')}` : ''}`,
        [],
        props.chatId,
        speaker,
      );
      await sendChat(
        formatStrikeNarrativeMessage({
          attackerKey: accepted.initiator,
          attackerName: nameOf(accepted.initiator),
          defenderKey: accepted.opponent,
          defenderName: nameOf(accepted.opponent),
          weaponRuleId: hit.itemRuleId,
          weaponName: hit.itemName,
          damageTypeCode: attack.damageTypeCode,
          profileType: hit.profileType,
          flank: Boolean(hit.flank),
          turn: Boolean(hit.turn && hit.flank && hit.reaction !== 'ignore'),
          reaction: hit.reaction ?? 'ignore',
          reactionAction: reactionAction(props.rules, hit.reaction),
          reactionAp: spentDefense,
          rules: props.rules,
        }),
        [],
        props.chatId,
        speaker,
      );
      const payloads = rolled.defender ? [rolled.attacker, rolled.defender] : [rolled.attacker];
      await sendChat(
        '',
        payloads.map((payload) => ({ type: ROLL_ATTACHMENT_TYPE, payload })),
        props.chatId,
        speaker,
      );
      await sendChat(
        formatAttackResultMessage({
          attackerKey: accepted.initiator,
          attackerName: nameOf(accepted.initiator),
          defenderKey: accepted.opponent,
          defenderName: nameOf(accepted.opponent),
          remainingSr: result.remainingSr,
          exhaustion: result.exhaustion,
          wound: (result.wound ?? 0) + (result.cuttingWound ?? 0),
        }),
        [
          {
            type: ATTACK_CALC_ATTACHMENT_TYPE,
            payload: buildAttackCalcPayload({
              weaponDamage: hit.damage ?? attack.damage,
              damageTypeCode: attack.damageTypeCode,
              rules: props.rules,
              sr: Math.max(0, sr),
              result,
              defenseIgnored,
            }),
          },
        ],
        props.chatId,
        speaker,
      );
    }

    if (result.exhaustion > 0) {
      const afterHit = versionOf(accepted.opponent);
      if (afterHit) {
        const exhaustion = await exhaustionCheckService.applyExhaustionCheck({
          version: afterHit,
          rules: props.rules,
          mechanics: props.mechanics,
          gameId: props.gameId,
          targetKey: accepted.opponent,
          targetName: nameOf(accepted.opponent),
          chatId: props.chatId,
          speaker: speakerFor(accepted.opponent),
          change: 'increase',
          sendMessage: (content, attachments, chatId, speaker) => sendChat(content, attachments, chatId, speaker),
        });
        if (exhaustion.overlay) {
          overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, exhaustion.overlay);
          emit('overlay-changed');
        }
      }
    }
    if (
      !injuryCheckService.shouldLaunchInjuryFromAttack({
        hpDamage: result.hpDamage,
        cuttingWound: result.cuttingWound,
        woundFromHit: result.wound,
      })
    ) {
      return;
    }
    const defenderVersion = versionOf(accepted.opponent);
    const applied = await injuryCheckService.applyInjuryCheck({
      input: injuryCheckService.injuryInputFromAttack({
        hpDamage: result.hpDamage,
        cuttingWound: result.cuttingWound,
        woundFromHit: result.wound,
        overlayExhaustion: injuryCheckService.overlayStateTotal(defenderVersion, props.rules, EXHAUSTION_STATE_CODE),
        endurance: defenderOverview ? attackDamageService.enduranceOf(defenderOverview, props.rules) : 1,
        remainingSr: result.remainingSr,
        damageTypeCode: attack.damageTypeCode,
        actorKey: accepted.opponent,
      }),
      rules: props.rules,
      mechanics: props.mechanics,
      gameId: props.gameId,
      targetKey: accepted.opponent,
      targetName: nameOf(accepted.opponent),
      chatId: props.chatId,
      speaker: speakerFor(accepted.opponent),
      skipIfNoRoll: true,
      targetVersion: defenderVersion ?? undefined,
      sendMessage: (content, attachments, chatId, speaker) => sendChat(content, attachments, chatId, speaker),
    });
    if (applied.overlay) {
      overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, applied.overlay);
      emit('overlay-changed');
    }
  } finally {
    if (props.chatId !== null && shouldAnnounce) combatThread.endAttack();
  }
}

async function submit(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    if (!offer.value) {
      if (isPreparationAction.value) {
        await performPreparation();
        emit('settled');
        close();

        return;
      }
      await sendOffer();
      emit('offered');
      emit('settled');
      close();

      return;
    }
    if (myTurn.value) {
      if (advantageDirty.value) {
        await revise();
        emit('settled');
        close();

        return;
      }
      await acceptAndRoll();
      emit('settled');
      close();
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось запустить попадание';
  } finally {
    busy.value = false;
  }
}

async function submitRevise(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await revise();
    emit('settled');
    close();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось вернуть оферту';
  } finally {
    busy.value = false;
  }
}

const primaryLabel = computed(() => {
  if (!offer.value) return 'На одобрение';
  if (myTurn.value && advantageDirty.value) return 'На одобрение';
  if (myTurn.value && offer.value.waitingOn === 'opponent') return 'Принять и бросить';
  if (myTurn.value) return 'Принять и бросить';

  return offer.value.waitingOn === 'opponent' ? 'Ждём защитника' : 'Ждём атакующего';
});

const hitDialogTitle = computed(() => {
  if (resolvedAttack.value?.profileType === 'throw') return 'Бросок';
  if (resolvedAttack.value?.profileType === 'shoot') return 'Выстрел';

  return 'Удар';
});

const canSubmit = computed(() => {
  if (isCompose.value) {
    if (isPreparationAction.value) {
      return resolvedSelectedAction.value !== null && resolvedSelectedAction.value.odCost <= attackerAp.value;
    }
    if (isRanged.value && !(distanceIpari.value > 0)) return false;

    return (
      opponentKey.value !== null &&
      resolvedSelectedAction.value !== null &&
      resolvedSelectedAction.value.odCost <= attackerAp.value
    );
  }
  if (!myTurn.value) return false;
  if (isDefenderStep.value) {
    if (reaction.value === null) return false;
    if (reaction.value === 'block' && !canBlock.value) return false;
    if (reaction.value === 'dodge' && !dodgeAffordable.value) return false;
    if (reaction.value === 'block' && !blockAffordable.value) return false;

    return true;
  }

  return true;
});
</script>

<template>
  <v-dialog :model-value="open" max-width="480" scrollable @update:model-value="emit('update:open', $event)">
    <v-card class="hit-dialog">
      <v-card-title class="text-subtitle-1 py-2 px-4">{{ hitDialogTitle }}</v-card-title>
      <v-card-text class="hit-dialog-body px-4 py-2">
        <div v-if="resolvedAttack" class="text-body-2">
          {{ nameOf(resolvedAttackerKey) }} · {{ resolvedAttack.itemName }} ({{ resolvedAttack.profileTypeLabel }}) ·
          точность
          {{ new DimensionalNumber(resolvedAttack.accuracy).toString() }}
          <template v-if="isRanged"> · урон {{ new DimensionalNumber(resolvedAttack.damage).toString() }} </template>
        </div>
        <v-select
          v-if="isCompose && !attackAction"
          v-model="selectedActionRuleId"
          :items="attackSelectItems"
          item-title="title"
          item-value="ruleId"
          density="compact"
          variant="outlined"
          hide-details
          label="Действие атаки"
        />
        <div v-else class="text-body-2">
          <template v-if="resolvedAttackAction?.source.kind === 'process'">
            {{ processStep?.name ?? 'Шаг процесса' }} · {{ processStepCost }} ОД ·
            {{ resolvedAttackAction.strikes.length }} одновременных ударов
          </template>
          <template v-else>
            {{ selectedAction?.name ?? 'Действие атаки' }} · {{ selectedAction?.odCost ?? DEFAULT_ATTACK_AP }} ОД
          </template>
        </div>
        <div class="d-flex align-center ga-2 flex-wrap">
          <ClampedNumberField
            v-if="isRanged && !isPreparationAction"
            v-model="distanceIpari"
            label="Дистанция, ипари"
            :min="1"
            :max="400"
            density="compact"
            hide-details
            min-width="140px"
            class="flex-grow-1"
            :disabled="!isCompose"
          />
          <ClampedNumberField
            v-if="isRanged && !isPreparationAction"
            v-model="cover"
            label="Укрытие"
            :min="0"
            :max="20"
            density="compact"
            hide-details
            min-width="110px"
            class="flex-grow-1"
            :disabled="!canEditCover"
          />
          <v-checkbox
            v-if="!isPreparationAction"
            v-model="flank"
            label="Фланг"
            density="compact"
            hide-details
            class="hit-check flex-grow-0"
            :disabled="!isCompose"
          />
        </div>
        <div class="text-caption text-medium-emphasis">
          {{ procedure.code }}@{{ procedure.version }} · игнор
          {{ new DimensionalNumber(ignoreDifficultyPreview).toString() }}
        </div>
        <v-select
          v-if="!isPreparationAction"
          v-model="opponentKey"
          :items="opponentOptions"
          item-title="title"
          item-value="value"
          density="compact"
          variant="outlined"
          hide-details
          label="Цель"
          :disabled="!isCompose"
        />
        <ClampedNumberField
          v-if="isCompose && !isPreparationAction"
          v-model="attackerAdv"
          label="Преим. атака"
          :min="-ROLL_ADV_MAX"
          :max="ROLL_ADV_MAX"
          density="compact"
          hide-details
        />
        <div v-if="offer && myTurn" class="d-flex ga-2">
          <ClampedNumberField
            v-model="attackerAdv"
            label="Преим. атака"
            :min="-ROLL_ADV_MAX"
            :max="ROLL_ADV_MAX"
            density="compact"
            hide-details
          />
          <ClampedNumberField
            v-model="defenderAdv"
            label="Преим. защита"
            :min="-ROLL_ADV_MAX"
            :max="ROLL_ADV_MAX"
            density="compact"
            hide-details
          />
        </div>
        <template v-if="isDefenderStep">
          <div class="text-caption text-medium-emphasis">Реакция защиты</div>
          <v-btn-toggle
            v-model="reaction"
            density="compact"
            color="primary"
            variant="outlined"
            divided
            class="hit-reaction-toggle"
          >
            <v-btn value="ignore" size="small">Игнор</v-btn>
            <v-btn value="dodge" size="small" :disabled="!dodgeAffordable">
              Уклон · {{ dodgeAction?.odCost ?? 1 }} ОД
            </v-btn>
            <v-btn value="block" size="small" :disabled="!canBlock || !blockAffordable">
              Блок · {{ blockAction?.odCost ?? 2 }} ОД
            </v-btn>
          </v-btn-toggle>
          <v-checkbox
            v-if="flank && reaction !== 'ignore'"
            v-model="turn"
            :label="`Поворот · ${turnAbility.odCost} ОД`"
            density="compact"
            hide-details
            class="hit-check"
          />
          <v-select
            v-if="reaction === 'block'"
            v-model="blockItemRuleId"
            :items="blockProfiles"
            item-title="itemName"
            item-value="itemRuleId"
            density="compact"
            variant="outlined"
            hide-details
            label="Профиль блока"
          />
          <DimensionalNumberInput
            v-if="reaction === 'dodge' || reaction === 'block'"
            v-model="defenseEfficiency"
            label="Эффективность защиты"
            :min="CHARACTERISTIC_BASE_RANGE.min"
            :max="CHARACTERISTIC_BASE_RANGE.max"
          />
        </template>
        <v-alert v-if="isWaiting" type="info" variant="tonal" density="compact" class="mb-0">
          Ждём {{ offer?.waitingOn === 'opponent' ? 'защитника' : 'атакующего' }}.
        </v-alert>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-0">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions class="py-2 px-4">
        <v-spacer />
        <v-btn variant="text" size="small" :disabled="busy" @click="close">Закрыть</v-btn>
        <v-btn v-if="offer && myTurn" variant="text" size="small" :disabled="busy" @click="submitRevise">Вернуть</v-btn>
        <v-btn color="primary" size="small" :loading="busy" :disabled="!canSubmit" @click="submit">
          {{ primaryLabel }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.hit-dialog {
  max-height: min(90vh, 720px);
}
.hit-dialog-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: min(70vh, 560px);
}
.hit-check {
  margin: 0;
  flex: 0 0 auto;
}
.hit-check :deep(.v-selection-control) {
  min-height: 32px;
}
.hit-reaction-toggle {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.hit-reaction-toggle :deep(.v-btn) {
  border-inline-width: 1px !important;
  border-radius: 4px !important;
  height: 32px !important;
}
</style>
