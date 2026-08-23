<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import type { CheckOffer, CheckOfferProposal, HitDefenseReaction } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { CHECK_HIT_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import { combatCardModel, combatEntityName } from '@/modules/Roleplay/Game/Utils/combatCardModel';
import { replaceCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';
import { listBlockProfiles, rollMeleeHit, type HitCheckRoll } from '@/modules/Roleplay/Game/Utils/hitRoll';
import { resolveStrikeProcedure } from '@/modules/Roleplay/Game/Utils/resolveStrikeProcedure';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { resolveDamageTypeHooks } from '@/modules/Roleplay/Game/Utils/resolveDamageTypeHooks';
import {
  DEFAULT_ATTACK_AP,
  applyAttackDamage,
  actionPointsResource,
  enduranceOf,
  hasPaySrHook,
  spendActionPoints,
} from '@/modules/Roleplay/Game/Utils/applyAttackDamage';
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
  reactionOdCost,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import { asDamageTypeSpec } from '@/modules/Roleplay/Rule/Utils/damageTypeSpec';

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
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  settled: [];
  offered: [];
  'overlay-changed': [];
}>();

const chatStore = useChatStore();
const overlays = ref<GameCombatOverlay[]>([]);
const offer = ref<CheckOffer | null>(null);
const opponentKey = ref<CombatEntityKey | null>(null);
const reaction = ref<HitDefenseReaction | null>(null);
const attackerAdv = ref(0);
const defenderAdv = ref(0);
const defenseEfficiency = ref<DimensionalNumberValue>({ base: 4, size: -1 });
const blockItemRuleId = ref<string | null>(null);
const payX = ref(0);
const selectedActionRuleId = ref<string | null>(null);
const agreedInitiatorAdv = ref(0);
const agreedOpponentAdv = ref(0);
const busy = ref(false);
const error = ref<string | null>(null);

const procedure = computed(() => resolveStrikeProcedure(props.rules, props.mechanics));

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

  return combatEntityName(key, props.characters, props.npcs);
}

function versionOf(key: CombatEntityKey | null): CharacterVersion | null {
  if (!key) return null;
  const overlay = overlays.value.find((item) => item.entityKey === key) ?? null;

  return combatCardModel(key, props.characters, props.npcs, props.canEdit, props.currentUserId, overlay)
    .effectiveVersion;
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

const currentAttack = computed(() => (offer.value ? attackFromOffer(offer.value) : props.attack));

const strikeOnly = computed(() => currentAttack.value?.profileType === 'strike');

const damageHooks = computed(() =>
  resolveDamageTypeHooks(currentAttack.value?.damageTypeCode, props.rules, props.mechanics),
);
const showPayX = computed(() => hasPaySrHook(damageHooks.value));

const attackOptions = computed(() =>
  listAttackActions(props.rules, overviewOf(resolvedAttackerKey.value), currentAttack.value?.profileType ?? 'strike'),
);

const selectedAction = computed(
  () => attackActionById(props.rules, selectedActionRuleId.value) ?? attackOptions.value[0] ?? null,
);

function remainingAp(key: CombatEntityKey | null): number {
  const overview = overviewOf(key);
  if (!overview) return 0;
  const resource = actionPointsResource(overview, props.rules);

  return resource ? Math.max(0, resource.current.base) : 0;
}

const attackerAp = computed(() => remainingAp(resolvedAttackerKey.value));
const defenderAp = computed(() => remainingAp(opponentKey.value));

const attackSelectItems = computed(() =>
  attackOptions.value.map((option) => ({
    ...option,
    title: `${option.name} · ${option.odCost} ОД`,
    props: { disabled: option.odCost > attackerAp.value },
  })),
);

const advantageDirty = computed(() => {
  if (!offer.value) return false;

  return attackerAdv.value !== agreedInitiatorAdv.value || defenderAdv.value !== agreedOpponentAdv.value;
});

const dodgeAffordable = computed(() => (dodgeAction.value?.odCost ?? 1) <= defenderAp.value);
const blockAffordable = computed(() => (blockAction.value?.odCost ?? 2) <= defenderAp.value);

const dodgeAction = computed(() => reactionAction(props.rules, 'dodge'));
const blockAction = computed(() => reactionAction(props.rules, 'block'));

watch(attackOptions, (options) => {
  if (selectedActionRuleId.value && options.some((item) => item.ruleId === selectedActionRuleId.value)) return;
  const preferredCode =
    currentAttack.value?.profileType === 'strike' ? SIMPLE_MELEE_ATTACK_CODE : SIMPLE_RANGED_ATTACK_CODE;
  selectedActionRuleId.value =
    options.find((item) => item.code === preferredCode)?.ruleId ?? options[0]?.ruleId ?? null;
});

const blockProfiles = computed(() => listBlockProfiles(versionOf(opponentKey.value), props.rules));

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
  overlays.value = await getGameApi()
    .getCombatOverlays(props.gameId)
    .catch(() => []);
  if (props.resumeOffer) {
    offer.value = props.resumeOffer;
    opponentKey.value = props.resumeOffer.opponent;
    const hit = props.resumeOffer.proposal.hit;
    reaction.value = hit?.reaction ?? null;
    attackerAdv.value = props.resumeOffer.proposal.initiatorAdv;
    defenderAdv.value = props.resumeOffer.proposal.opponentAdv;
    agreedInitiatorAdv.value = props.resumeOffer.proposal.initiatorAdv;
    agreedOpponentAdv.value = props.resumeOffer.proposal.opponentAdv;
    blockItemRuleId.value = hit?.blockItemRuleId ?? null;
    payX.value = hit?.payX ?? 0;
    selectedActionRuleId.value = hit?.actionRuleId ?? selectedActionRuleId.value;
    if (hit?.defenseEfficiency) {
      defenseEfficiency.value = { ...hit.defenseEfficiency };
    } else {
      applyReactionDefaults(reaction.value);
    }

    return;
  }
  offer.value = null;
  opponentKey.value = opponentOptions.value[0]?.value ?? null;
  reaction.value = null;
  attackerAdv.value = 0;
  defenderAdv.value = 0;
  agreedInitiatorAdv.value = 0;
  agreedOpponentAdv.value = 0;
  blockItemRuleId.value = null;
  payX.value = 0;
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
  return {
    itemRuleId: attack.itemRuleId,
    itemName: attack.itemName,
    profileType: attack.profileType,
    accuracy: attack.accuracy,
    reaction: nextReaction,
    defenseEfficiency: nextReaction === 'ignore' || nextReaction === null ? null : defenseEfficiency.value,
    blockItemRuleId: nextReaction === 'block' ? blockItemRuleId.value : null,
    payX: payX.value,
    damageTypeCode: attack.damageTypeCode,
    damage: attack.damage,
    penetration: attack.penetration,
    actionRuleId: selectedAction.value?.ruleId ?? null,
    actionName: selectedAction.value?.name,
    actionOd: selectedAction.value?.odCost,
  };
}

async function sendOffer(): Promise<void> {
  const attack = currentAttack.value;
  const initiator = resolvedAttackerKey.value;
  if (!attack || !initiator || !opponentKey.value) throw new Error('Выберите цель');
  if (!selectedAction.value) throw new Error('Выберите действие атаки');
  const attackerApCost = selectedAction.value.odCost || DEFAULT_ATTACK_AP;
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
      hit: hitProposal(attack, null),
    },
  });
}

function defenderProposal(): CheckOfferProposal {
  const current = offer.value;
  const attack = currentAttack.value;
  if (!current || !attack) throw new Error('Нет оферты');
  if (!reaction.value) throw new Error('Выберите игнор, уклон или блок');
  if (reaction.value === 'block' && !blockItemRuleId.value) throw new Error('Выберите профиль блока');
  const reactionCost = reactionOdCost(reaction.value, props.rules);
  if (reactionCost > remainingAp(current.opponent)) throw new Error('Недостаточно ОД для реакции');

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
  const attack = currentAttack.value;
  const actor = current ? actingEntity(current) : null;
  if (!current || !attack || !actor) throw new Error('Нет оферты');
  const proposal = defenderProposal();
  const accepted = await getGameApi().acceptCheckOffer(current.id, actor, proposal);
  const hit = accepted.proposal.hit;
  if (!hit?.reaction) throw new Error('Защитник ещё не выбрал реакцию');
  const rolled = rollMeleeHit(
    {
      attackerLabel: nameOf(accepted.initiator),
      defenderLabel: nameOf(accepted.opponent),
      attackerKey: accepted.initiator,
      defenderKey: accepted.opponent,
      attack: { itemName: hit.itemName, profileType: hit.profileType, accuracy: hit.accuracy },
      attackerOverview: overviewOf(accepted.initiator),
      defenderOverview: overviewOf(accepted.opponent),
      reaction: hit.reaction,
      defenseEfficiency: hit.defenseEfficiency,
      attackerAdv: accepted.proposal.initiatorAdv,
      defenderAdv: accepted.proposal.opponentAdv,
    },
    Math.random,
    props.rules,
    props.mechanics,
  );
  await applyClickAttack(accepted, hit, rolled.attacker.check?.rating ?? 0, attack, rolled);
  offer.value = accepted;
}

async function spendAp(key: CombatEntityKey, cost: number): Promise<number> {
  if (cost <= 0) return 0;
  const overview = overviewOf(key);
  if (!overview) return 0;
  const resource = actionPointsResource(overview, props.rules);
  if (!resource) return 0;
  const spent = Math.min(cost, Math.max(0, resource.current.base));
  const next = spendActionPoints(resource.current, spent);
  const overlay = await getGameApi().setCombatResource(props.gameId, key, resource.ruleId, next);
  overlays.value = replaceCombatOverlay(overlays.value, overlay);

  return spent;
}

async function applyExhaustion(key: CombatEntityKey, amount: number): Promise<void> {
  if (amount <= 0) return;
  const rule = props.rules.find((item) => item.code === 'exhaustion' && item.type === 'state');
  if (!rule) return;
  const states = versionOf(key)?.states ?? [];
  const index = states.findIndex((state) => state.stateRuleId === rule.id);
  const overlay =
    index >= 0
      ? await getGameApi().setCombatStateValue(props.gameId, key, index, (states[index]?.value ?? 0) + amount)
      : await getGameApi().addCombatState(props.gameId, key, {
          stateRuleId: rule.id,
          value: amount,
        } as CharacterStateValue);
  overlays.value = replaceCombatOverlay(overlays.value, overlay);
}

async function applyClickAttack(
  accepted: CheckOffer,
  hit: NonNullable<CheckOfferProposal['hit']>,
  sr: number,
  attack: AttackOverview,
  rolled: HitCheckRoll,
): Promise<void> {
  const hooks = resolveDamageTypeHooks(attack.damageTypeCode, props.rules, props.mechanics);
  const defenderOverview = overviewOf(accepted.opponent);
  const typeRule = attack.damageTypeCode
    ? props.rules.find((rule) => rule.code === attack.damageTypeCode && rule.type === 'damage_type')
    : undefined;
  const defenseIgnored = asDamageTypeSpec(typeRule)?.defense_ignored === true;
  const result = applyAttackDamage({
    weaponDamage: attack.damage,
    sr: Math.max(0, sr),
    payX: hit.payX ?? payX.value,
    damageTypeCode: attack.damageTypeCode,
    defense: defenderOverview?.defense ?? null,
    endurance: defenderOverview ? enduranceOf(defenderOverview, props.rules) : 1,
    hooks,
    defenseIgnored,
  });
  const action = selectedAction.value ??
    attackActionById(props.rules, hit.actionRuleId) ?? {
      ruleId: hit.actionRuleId ?? '',
      code: '',
      name: hit.actionName ?? 'Простая атака',
      odCost: hit.actionOd ?? DEFAULT_ATTACK_AP,
    };
  const attackerAp = action.odCost || DEFAULT_ATTACK_AP;
  const defenderAp = reactionOdCost(hit.reaction, props.rules);
  const spentAttack = await spendAp(accepted.initiator, attackerAp);
  const spentDefense = await spendAp(accepted.opponent, defenderAp);
  await applyExhaustion(accepted.opponent, result.exhaustion);
  emit('overlay-changed');
  if (props.chatId === null) return;
  const speaker = speakerFor(accepted.initiator);
  await chatStore.sendMessage(
    formatAttackActionMessage({
      attackerKey: accepted.initiator,
      attackerName: nameOf(accepted.initiator),
      action,
      attackerAp: spentAttack,
      rules: props.rules,
    }),
    [],
    props.chatId,
    speaker,
  );
  await chatStore.sendMessage(
    formatStrikeNarrativeMessage({
      attackerKey: accepted.initiator,
      attackerName: nameOf(accepted.initiator),
      defenderKey: accepted.opponent,
      defenderName: nameOf(accepted.opponent),
      weaponRuleId: hit.itemRuleId,
      weaponName: hit.itemName,
      damageTypeCode: attack.damageTypeCode,
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
  await chatStore.sendMessage(
    '',
    payloads.map((payload) => ({ type: ROLL_ATTACHMENT_TYPE, payload })),
    props.chatId,
    speaker,
  );
  await chatStore.sendMessage(
    formatAttackResultMessage({
      attackerKey: accepted.initiator,
      attackerName: nameOf(accepted.initiator),
      defenderKey: accepted.opponent,
      defenderName: nameOf(accepted.opponent),
      remainingSr: result.remainingSr,
      exhaustion: result.exhaustion,
    }),
    [
      {
        type: ATTACK_CALC_ATTACHMENT_TYPE,
        payload: buildAttackCalcPayload({
          weaponDamage: attack.damage,
          damageTypeCode: attack.damageTypeCode,
          rules: props.rules,
          payX: hit.payX ?? payX.value,
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

async function submit(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    if (!offer.value) {
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

const canSubmit = computed(() => {
  if (!strikeOnly.value) return false;
  if (isCompose.value) {
    return (
      opponentKey.value !== null &&
      selectedAction.value !== null &&
      selectedAction.value.odCost <= attackerAp.value
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
  <v-dialog :model-value="open" max-width="520" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-subtitle-1">Атака</v-card-title>
      <v-card-text>
        <div v-if="currentAttack" class="text-body-2 mb-3">
          {{ nameOf(resolvedAttackerKey) }} · {{ currentAttack.itemName }} ({{ currentAttack.profileTypeLabel }}) ·
          точность
          {{ new DimensionalNumber(currentAttack.accuracy).toString() }}
        </div>
        <v-select
          v-if="isCompose"
          v-model="selectedActionRuleId"
          :items="attackSelectItems"
          item-title="title"
          item-value="ruleId"
          density="compact"
          variant="outlined"
          hide-details
          label="Действие атаки"
          class="mb-3"
        />
        <div v-if="!isCompose" class="text-body-2 mb-3">
          {{ selectedAction?.name ?? 'Действие атаки' }} · {{ selectedAction?.odCost ?? DEFAULT_ATTACK_AP }} ОД
        </div>
        <ClampedNumberField
          v-if="showPayX"
          v-model="payX"
          label="Оплатить X РУ (надёжность)"
          :min="0"
          :max="20"
          class="mb-3"
          :disabled="!isCompose && !isDefenderStep"
        />
        <v-alert v-if="!strikeOnly" type="info" variant="tonal" density="compact" class="mb-3">
          Выстрел и бросок в этом заходе не запускаются.
        </v-alert>
        <div class="text-caption text-medium-emphasis mb-2">
          Процедура {{ procedure.code }}@{{ procedure.version }} · игнор
          {{ new DimensionalNumber(procedure.ignoreDefense).toString() }}
        </div>
        <v-select
          v-model="opponentKey"
          :items="opponentOptions"
          item-title="title"
          item-value="value"
          density="compact"
          variant="outlined"
          hide-details
          label="Цель"
          class="mb-3"
          :disabled="!isCompose"
        />
        <v-alert v-if="isCompose" type="info" variant="tonal" density="compact" class="mb-3">
          Игнор, уклон и блок выбирает защитник. После «На одобрение» окно закроется; ведущий сразу получит шаг защиты,
          игроку-защитнику нужно говорить от лица цели.
        </v-alert>
        <ClampedNumberField
          v-if="isCompose"
          v-model="attackerAdv"
          label="Преим. атака"
          :min="-ROLL_ADV_MAX"
          :max="ROLL_ADV_MAX"
          class="mb-3"
        />
        <template v-if="offer && myTurn">
          <div class="d-flex ga-2 mb-3">
            <ClampedNumberField v-model="attackerAdv" label="Преим. атака" :min="-ROLL_ADV_MAX" :max="ROLL_ADV_MAX" />
            <ClampedNumberField v-model="defenderAdv" label="Преим. защита" :min="-ROLL_ADV_MAX" :max="ROLL_ADV_MAX" />
          </div>
        </template>
        <template v-if="isDefenderStep">
          <div class="text-caption text-medium-emphasis mb-1">Реакция защиты</div>
          <v-btn-toggle
            v-model="reaction"
            density="compact"
            color="primary"
            variant="outlined"
            divided
            class="hit-reaction-toggle mb-3"
          >
            <v-btn value="ignore">Игнор</v-btn>
            <v-btn value="dodge" :disabled="!dodgeAffordable">Уклон · {{ dodgeAction?.odCost ?? 1 }} ОД</v-btn>
            <v-btn value="block" :disabled="!canBlock || !blockAffordable">
              Блок · {{ blockAction?.odCost ?? 2 }} ОД
            </v-btn>
          </v-btn-toggle>
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
            class="mb-3"
          />
          <DimensionalNumberInput
            v-if="reaction === 'dodge' || reaction === 'block'"
            v-model="defenseEfficiency"
            class="mb-3"
            label="Эффективность защиты"
            :min="CHARACTERISTIC_BASE_RANGE.min"
            :max="CHARACTERISTIC_BASE_RANGE.max"
          />
        </template>
        <v-alert v-if="isWaiting" type="info" variant="tonal" density="compact" class="mb-2">
          Ждём {{ offer?.waitingOn === 'opponent' ? 'защитника' : 'атакующего' }}.
        </v-alert>
        <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="close">Закрыть</v-btn>
        <v-btn v-if="offer && myTurn" variant="text" :disabled="busy" @click="submitRevise">Вернуть</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="!canSubmit" @click="submit">
          {{ primaryLabel }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.hit-reaction-toggle {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.hit-reaction-toggle :deep(.v-btn) {
  border-inline-width: 1px !important;
  border-radius: 4px !important;
}
</style>
