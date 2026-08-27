<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';

import { getGameApi } from '@/modules/Roleplay/Game/init';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';

import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { itemCheckAdvantagesService } from '@/modules/Roleplay/Character/init';
import { abilityCheckAdvantagesService } from '@/modules/Roleplay/Character/init';
import { SIMPLE_CHECK_ZERO_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Check/SIMPLE_CHECK_ZERO_DIFFICULTY';

import { initiativeCharacteristics } from '@/modules/Roleplay/Game/Utils/initiativeCharacteristic';
import type { InitiativeCharacteristicView } from '@/modules/Roleplay/Game/Utils/initiativeCharacteristic';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';

import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
import { checkLaunchService } from '@/modules/Roleplay/Rule/Service/Instance/checkLaunchService';
import { CHECK_SIMPLE_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import { ROLL_DICE_COUNT_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MAX';
import { ROLL_DICE_COUNT_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MIN';
import { ROLL_EFFICIENCY_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MAX';
import { ROLL_EFFICIENCY_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MIN';

/**
 * Запуск проверки: соло (ask / from_state / {0|0}) или pairwise-оферта.
 * Кубы совместной — только после accept. Инициатива и удар сюда не входят.
 */
const props = defineProps<{
  open: boolean;
  gameId: number;
  spaceId: number;
  chatId: number | null;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  mechanics: Mechanic[];
  canEdit: boolean;
  currentUserId: number | null;
  activeSpeakerKey: string | null;
  resumeOffer: CheckOffer | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  settled: [];
}>();

const sendChat = combatChatSendService.sendCombatChat(props.gameId);

const overlays = ref<GameCombatOverlay[]>([]);
const checkCode = ref('');
const launchKind = ref<'solo' | 'pairwise'>('solo');
const initiatorKey = ref<CombatEntityKey | null>(null);
const opponentKey = ref<CombatEntityKey | null>(null);
const initiatorCharacteristic = ref<string | null>(null);
const opponentCharacteristic = ref<string | null>(null);
const initiatorAdv = ref(0);
const opponentAdv = ref(0);
const initiatorUseFree = ref(false);
const opponentUseFree = ref(false);
const initiatorFreeDice = ref(3);
const initiatorFreeSize = ref(0);
const initiatorFreeEfficiency = ref(3);
const opponentFreeDice = ref(3);
const opponentFreeSize = ref(0);
const opponentFreeEfficiency = ref(3);
const askBase = ref(0);
const askSize = ref(0);
const opponentTouched = ref(false);
const offer = ref<CheckOffer | null>(null);
const initiatorChars = ref<Map<string, InitiativeCharacteristicView>>(new Map());
const opponentChars = ref<Map<string, InitiativeCharacteristicView>>(new Map());
const busy = ref(false);
const error = ref<string | null>(null);

const checks = computed(() => checkLaunchService.launchableChecks(props.rules));
const checkItems = computed(() => checks.value.map((rule) => ({ value: rule.code, title: rule.name })));
const selectedCheck = computed(() => checks.value.find((rule) => rule.code === checkCode.value) ?? null);
const selectedSpec = computed(() => checkResolutionService.asCheckSpec(selectedCheck.value ?? undefined));

const canSolo = computed(() => (selectedCheck.value ? checkLaunchService.checkAllowsSolo(selectedCheck.value) : false));
const canPair = computed(() =>
  selectedCheck.value ? checkLaunchService.checkAllowsPairwise(selectedCheck.value) : false,
);
const overrideAllowed = computed(() => {
  if (!selectedSpec.value?.characteristic_code) return true;

  return selectedSpec.value.allow_characteristic_override === true;
});

const entityOptions = computed(() => {
  const characters = props.characters
    .filter((membership) => membership.membershipStatus === 'approved')
    .map((membership) => ({
      value: `character:${membership.characterId}` as CombatEntityKey,
      title: membership.characterName,
    }));
  const npcs = props.npcs
    .filter((npc) => npc.status === 'active')
    .map((npc) => ({ value: `npc:${npc.id}` as CombatEntityKey, title: npc.name }));

  return [...characters, ...npcs];
});

const opponentOptions = computed(() => entityOptions.value.filter((item) => item.value !== initiatorKey.value));

const speakerEntity = computed<CombatEntityKey | null>(() => {
  const key = props.activeSpeakerKey;
  if (!key || key === 'gm') return null;

  return key as CombatEntityKey;
});

const lockedParticipants = computed(() => offer.value !== null);
const waitingOnOther = computed(() => {
  const current = offer.value;
  if (!current || current.status !== 'pending') return false;
  const actor = actorRole(current);

  return actor === null || current.waitingOn !== actor;
});
const myTurn = computed(() => {
  const current = offer.value;
  if (!current || current.status !== 'pending') return false;
  const actor = actorRole(current);

  return actor !== null && current.waitingOn === actor;
});
const formLocked = computed(() => lockedParticipants.value && !myTurn.value);

const fromState = computed(() => selectedSpec.value?.difficulty_input.kind === 'from_state');
const askDifficulty = computed(() => selectedSpec.value?.difficulty_input.kind === 'ask');

function actorRole(current: CheckOffer): 'initiator' | 'opponent' | null {
  if (speakerEntity.value === current.initiator) return 'initiator';
  if (speakerEntity.value === current.opponent) return 'opponent';

  return props.canEdit ? 'initiator' : null;
}

function overlayOf(key: CombatEntityKey | null): GameCombatOverlay | null {
  if (!key) return null;

  return overlays.value.find((item) => item.entityKey === key) ?? null;
}

function modelOf(key: CombatEntityKey | null) {
  if (!key) return null;

  return combatCardModelService.combatCardModel(
    key,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlayOf(key),
  );
}

function nameOf(key: CombatEntityKey | null): string {
  if (!key) return '';

  return combatCardModelService.combatEntityName(key, props.characters, props.npcs);
}

function charItems(map: Map<string, InitiativeCharacteristicView>) {
  const items = [...map.values()].map((view) => ({
    code: view.code,
    name: view.name,
    valueLabel: new DimensionalNumber(view.value).toString(),
  }));
  const resolved = checkResolutionService.resolveCheckCharacteristicCode(checkCode.value, props.rules);
  if (resolved && !map.has(resolved)) {
    const rule = props.rules.find((candidate) => candidate.code === resolved);
    items.unshift({
      code: resolved,
      name: rule?.name ?? resolved,
      valueLabel: 'нет на листе',
    });
  }

  return items;
}

const initiatorCharItems = computed(() => charItems(initiatorChars.value));
const opponentCharItems = computed(() => charItems(opponentChars.value));

async function loadChars(key: CombatEntityKey | null, target: typeof initiatorChars): Promise<void> {
  const version = modelOf(key)?.effectiveVersion ?? null;
  if (!version) {
    target.value = new Map();

    return;
  }
  try {
    target.value = await initiativeCharacteristics(version, props.spaceId, props.rules);
  } catch {
    target.value = new Map();
  }
}

function defaultCharacteristic(map: Map<string, InitiativeCharacteristicView>): string | null {
  const resolved = checkResolutionService.resolveCheckCharacteristicCode(checkCode.value, props.rules);
  if (resolved) return resolved;
  const first = map.keys().next().value;

  return first ?? null;
}

function applyCheckKind(): void {
  if (canPair.value && !canSolo.value) launchKind.value = 'pairwise';
  else if (canSolo.value && !canPair.value) launchKind.value = 'solo';
}

function proposal(): CheckOfferProposal {
  return {
    initiatorCharacteristic: initiatorUseFree.value ? null : initiatorCharacteristic.value,
    opponentCharacteristic: opponentUseFree.value ? null : opponentCharacteristic.value,
    initiatorAdv: initiatorAdv.value,
    opponentAdv: opponentAdv.value,
    initiatorFree: initiatorUseFree.value
      ? {
          diceCount: initiatorFreeDice.value,
          dieSize: initiatorFreeSize.value,
          efficiency: initiatorFreeEfficiency.value,
        }
      : null,
    opponentFree: opponentUseFree.value
      ? {
          diceCount: opponentFreeDice.value,
          dieSize: opponentFreeSize.value,
          efficiency: opponentFreeEfficiency.value,
        }
      : null,
  };
}

function applyProposal(data: CheckOfferProposal): void {
  initiatorCharacteristic.value = data.initiatorCharacteristic;
  opponentCharacteristic.value = data.opponentCharacteristic;
  initiatorAdv.value = data.initiatorAdv;
  opponentAdv.value = data.opponentAdv;
  initiatorUseFree.value = data.initiatorCharacteristic === null;
  opponentUseFree.value = data.opponentCharacteristic === null;
  if (data.initiatorFree) {
    initiatorFreeDice.value = data.initiatorFree.diceCount;
    initiatorFreeSize.value = data.initiatorFree.dieSize;
    initiatorFreeEfficiency.value = data.initiatorFree.efficiency;
  }
  if (data.opponentFree) {
    opponentFreeDice.value = data.opponentFree.diceCount;
    opponentFreeSize.value = data.opponentFree.dieSize;
    opponentFreeEfficiency.value = data.opponentFree.efficiency;
  }
  opponentTouched.value = data.opponentCharacteristic !== data.initiatorCharacteristic;
}

async function hydrateNew(): Promise<void> {
  offer.value = null;
  error.value = null;
  opponentTouched.value = false;
  initiatorAdv.value = 0;
  opponentAdv.value = 0;
  askBase.value = 0;
  askSize.value = 0;
  const defaults = rollPoolDefaults(props.rules);
  initiatorFreeDice.value = defaults.freeDiceCount;
  opponentFreeDice.value = defaults.freeDiceCount;
  initiatorFreeEfficiency.value = defaults.efficiency;
  opponentFreeEfficiency.value = defaults.efficiency;
  initiatorFreeSize.value = 0;
  opponentFreeSize.value = 0;
  checkCode.value = checks.value.find((rule) => rule.code === CHECK_SIMPLE_CODE)?.code ?? checks.value[0]?.code ?? '';
  initiatorKey.value = speakerEntity.value ?? entityOptions.value[0]?.value ?? null;
  opponentKey.value = opponentOptions.value[0]?.value ?? null;
  applyCheckKind();
  await loadChars(initiatorKey.value, initiatorChars);
  initiatorUseFree.value = initiatorChars.value.size === 0;
  initiatorCharacteristic.value = defaultCharacteristic(initiatorChars.value);
  opponentCharacteristic.value = initiatorCharacteristic.value;
  await loadChars(opponentKey.value, opponentChars);
  opponentUseFree.value = opponentChars.value.size === 0;
  if (!opponentTouched.value) opponentCharacteristic.value = initiatorCharacteristic.value;
}

async function hydrateOffer(current: CheckOffer): Promise<void> {
  offer.value = current;
  error.value = null;
  checkCode.value = current.checkCode;
  launchKind.value = 'pairwise';
  initiatorKey.value = current.initiator;
  opponentKey.value = current.opponent;
  applyProposal(current.proposal);
  await loadChars(initiatorKey.value, initiatorChars);
  await loadChars(opponentKey.value, opponentChars);
}

watch(
  () => props.open,
  async (open) => {
    if (!open) return;
    overlays.value = await getGameApi()
      .getCombatOverlays(props.gameId)
      .catch(() => []);
    if (props.resumeOffer) await hydrateOffer(props.resumeOffer);
    else await hydrateNew();
  },
);

watch(
  () => props.resumeOffer,
  async (current) => {
    if (!props.open || !current) return;
    await hydrateOffer(current);
  },
);

watch(checkCode, () => {
  if (offer.value) return;
  applyCheckKind();
  initiatorCharacteristic.value = defaultCharacteristic(initiatorChars.value);
  if (!opponentTouched.value) opponentCharacteristic.value = initiatorCharacteristic.value;
});

watch(initiatorKey, async (key) => {
  if (offer.value) return;
  await loadChars(key, initiatorChars);
  initiatorUseFree.value = initiatorChars.value.size === 0;
  initiatorCharacteristic.value = defaultCharacteristic(initiatorChars.value);
  if (!opponentTouched.value) opponentCharacteristic.value = initiatorCharacteristic.value;
  if (opponentKey.value === key) opponentKey.value = opponentOptions.value[0]?.value ?? null;
});

watch(opponentKey, async (key) => {
  await loadChars(key, opponentChars);
  if (!offer.value) opponentUseFree.value = opponentChars.value.size === 0;
});

watch(initiatorCharacteristic, (code) => {
  if (!opponentTouched.value) opponentCharacteristic.value = code;
});

function poolSpec(
  key: CombatEntityKey | null,
  code: string | null,
  adv: number,
  map: Map<string, InitiativeCharacteristicView>,
  useFree: boolean,
  free: { diceCount: number; dieSize: number; efficiency: number },
): DiceRollSpec {
  const query = checkCode.value
    ? ({ kind: 'check', code: checkCode.value } as const)
    : code
      ? ({ kind: 'characteristic', code } as const)
      : undefined;
  const version = modelOf(key)?.effectiveVersion;
  const stateAdv = stateRuntimeEffectsService.checkAdvantageModifiers(version, props.rules, query);
  const itemAdv = itemCheckAdvantagesService.checkAdvantageModifiersFromItems(version, props.rules, query);
  const abilityAdv = abilityCheckAdvantagesService.checkAdvantageModifiersFromAbilities(version, props.rules, query);
  if (useFree || !code || !map.has(code)) {
    const defaults = rollPoolDefaults(props.rules);

    return {
      diceCount: Math.max(ROLL_DICE_COUNT_MIN, free.diceCount),
      dieFaces: defaults.dieFaces,
      efficiency: free.efficiency,
      advantages: [...aggregateSourceDeltasService.advantageEntries(adv), ...stateAdv, ...itemAdv, ...abilityAdv],
      dieSize: free.dieSize,
      poolSize: free.dieSize,
      efficiencySize: 0,
      label: nameOf(key),
      actorKey: key ?? undefined,
    };
  }
  const view = map.get(code);
  if (!view) throw new Error(`Нет характеристики для ${nameOf(key)}`);
  const spec = checkRollService.namedCheckSpec(
    `${nameOf(key)}: ${view.name}`,
    view.value,
    adv,
    props.rules,
    key ?? undefined,
  );

  return { ...spec, advantages: [...spec.advantages, ...stateAdv, ...itemAdv, ...abilityAdv] };
}

function soloDifficulty(): DimensionalNumberValue {
  const input = selectedSpec.value?.difficulty_input;
  if (input?.kind === 'from_state') {
    const states = modelOf(initiatorKey.value)?.effectiveVersion?.states ?? [];
    const value = combatCardModelService.combatExhaustion(states, props.rules);

    return { base: value ?? 0, size: 0 };
  }
  if (input?.kind === 'ask' && props.canEdit) return { base: askBase.value, size: askSize.value };

  return SIMPLE_CHECK_ZERO_DIFFICULTY;
}

const fromStateLabel = computed(() => {
  const difficulty = soloDifficulty();

  return new DimensionalNumber(difficulty).toString();
});

function speakerFor(key: CombatEntityKey | null): ChatSpeaker {
  const model = modelOf(key);
  if (!model) return { kind: 'gm' };

  return model.kind === 'character'
    ? { kind: 'character', characterId: model.entityId, characterName: model.name }
    : { kind: 'npc', npcId: model.entityId, npcName: model.name };
}

async function postRolls(text: string, payloads: unknown[]): Promise<void> {
  if (props.chatId === null) return;
  await sendChat(
    text,
    payloads.map((payload) => ({ type: ROLL_ATTACHMENT_TYPE, payload })),
    props.chatId,
    speakerFor(initiatorKey.value),
  );
}

function close(): void {
  emit('update:open', false);
}

async function runSolo(): Promise<void> {
  if (!initiatorKey.value) throw new Error('Выберите, кто бросает');
  const spec = poolSpec(
    initiatorKey.value,
    initiatorCharacteristic.value,
    initiatorAdv.value,
    initiatorChars.value,
    initiatorUseFree.value,
    {
      diceCount: initiatorFreeDice.value,
      dieSize: initiatorFreeSize.value,
      efficiency: initiatorFreeEfficiency.value,
    },
  );
  const result = checkRollService.rollNamedCheck(
    spec,
    checkCode.value,
    soloDifficulty(),
    Math.random,
    props.rules,
    props.mechanics,
  );
  await postRolls(selectedCheck.value?.name ?? 'Проверка', [result]);
}

async function sendOffer(): Promise<void> {
  if (!initiatorKey.value || !opponentKey.value) throw new Error('Нужны оба участника');
  offer.value = await getGameApi().createCheckOffer(props.gameId, {
    checkCode: checkCode.value,
    initiator: initiatorKey.value,
    opponent: opponentKey.value,
    proposal: proposal(),
  });
}

async function revise(): Promise<void> {
  const current = offer.value;
  if (!current || !speakerEntity.value) throw new Error('Нет оферты');
  offer.value = await getGameApi().reviseCheckOffer(current.id, speakerEntity.value, proposal());
}

async function accept(): Promise<void> {
  const current = offer.value;
  if (!current) throw new Error('Нет оферты');
  const actor = speakerEntity.value ?? current.opponent;
  const accepted = await getGameApi().acceptCheckOffer(current.id, actor);
  const left = poolSpec(
    accepted.initiator,
    accepted.proposal.initiatorCharacteristic,
    accepted.proposal.initiatorAdv,
    initiatorChars.value,
    accepted.proposal.initiatorCharacteristic === null,
    accepted.proposal.initiatorFree ?? {
      diceCount: initiatorFreeDice.value,
      dieSize: initiatorFreeSize.value,
      efficiency: initiatorFreeEfficiency.value,
    },
  );
  const right = poolSpec(
    accepted.opponent,
    accepted.proposal.opponentCharacteristic,
    accepted.proposal.opponentAdv,
    opponentChars.value,
    accepted.proposal.opponentCharacteristic === null,
    accepted.proposal.opponentFree ?? {
      diceCount: opponentFreeDice.value,
      dieSize: opponentFreeSize.value,
      efficiency: opponentFreeEfficiency.value,
    },
  );
  const rolled = checkRollService.rollJointCheck(
    left,
    right,
    accepted.checkCode,
    Math.random,
    props.rules,
    props.mechanics,
  );
  await postRolls(selectedCheck.value?.name ?? 'Проверка', [rolled.left, rolled.right]);
  offer.value = accepted;
}

async function cancel(): Promise<void> {
  const current = offer.value;
  if (!current) return;
  const actor = speakerEntity.value ?? current.initiator;
  await getGameApi().cancelCheckOffer(current.id, actor);
}

async function submit(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    if (launchKind.value === 'solo') {
      await runSolo();
      emit('settled');
      close();

      return;
    }
    if (!offer.value) {
      await sendOffer();
      emit('settled');

      return;
    }
    if (myTurn.value) {
      await accept();
      emit('settled');
      close();
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось запустить проверку';
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
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось вернуть оферту';
  } finally {
    busy.value = false;
  }
}

async function submitCancel(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    await cancel();
    emit('settled');
    close();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось игнорировать проверку';
  } finally {
    busy.value = false;
  }
}

const primaryLabel = computed(() => {
  if (launchKind.value === 'solo') return 'Бросить';
  if (!offer.value) return 'На одобрение';
  if (myTurn.value) return 'Принять и бросить';

  return 'Ждём ответа';
});

const statusHint = computed(() => {
  const current = offer.value;
  if (!current || current.status !== 'pending') return null;
  const waitingName = current.waitingOn === 'opponent' ? nameOf(current.opponent) : nameOf(current.initiator);

  return waitingOnOther.value ? `Ждём ответа: ${waitingName}` : `Ваш ход: принять или вернуть правку`;
});
</script>

<template>
  <v-dialog :model-value="open" max-width="640" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-subtitle-1 d-flex align-center">
        <v-icon icon="mdi-shield-check-outline" class="mr-2" />
        Проверка
      </v-card-title>
      <v-card-text>
        <v-autocomplete
          v-model="checkCode"
          :items="checkItems"
          item-title="title"
          item-value="value"
          density="compact"
          variant="outlined"
          hide-details
          label="Проверка"
          class="mb-3"
          auto-select-first
          :disabled="lockedParticipants"
        />
        <v-btn-toggle
          v-if="canSolo && canPair && !lockedParticipants"
          v-model="launchKind"
          mandatory
          density="compact"
          color="primary"
          variant="outlined"
          class="mb-3"
        >
          <v-btn value="solo">Соло</v-btn>
          <v-btn value="pairwise">Совместная</v-btn>
        </v-btn-toggle>

        <v-select
          v-model="initiatorKey"
          :items="entityOptions"
          item-title="title"
          item-value="value"
          density="compact"
          variant="outlined"
          hide-details
          label="Кто бросает"
          class="mb-3"
          :disabled="lockedParticipants"
        />

        <div class="check-launch-row">
          <v-btn-toggle
            :model-value="initiatorUseFree ? 'free' : 'characteristic'"
            density="compact"
            variant="outlined"
            color="primary"
            class="mb-0"
            mandatory
            :disabled="formLocked"
            @update:model-value="initiatorUseFree = $event === 'free' || initiatorChars.size === 0"
          >
            <v-btn value="characteristic" :disabled="initiatorChars.size === 0">Характеристика</v-btn>
            <v-btn value="free">Свободный пул</v-btn>
          </v-btn-toggle>
        </div>
        <div v-if="!initiatorUseFree" class="check-launch-row mt-2">
          <v-select
            v-model="initiatorCharacteristic"
            :items="initiatorCharItems"
            item-title="name"
            item-subtitle="valueLabel"
            item-value="code"
            density="compact"
            variant="outlined"
            hide-details
            label="Характеристика"
            :disabled="formLocked || (!overrideAllowed && !!selectedSpec?.characteristic_code)"
          />
          <ClampedNumberField
            v-model="initiatorAdv"
            :min="-ROLL_ADV_MAX"
            :max="ROLL_ADV_MAX"
            density="compact"
            hide-details
            label="Преим./помехи"
            :disabled="formLocked"
          />
        </div>
        <div v-else class="check-launch-row mt-2">
          <ClampedNumberField
            v-model="initiatorFreeDice"
            :min="ROLL_DICE_COUNT_MIN"
            :max="ROLL_DICE_COUNT_MAX"
            density="compact"
            hide-details
            label="Кубы"
            :disabled="formLocked"
          />
          <ClampedNumberField
            v-model="initiatorFreeEfficiency"
            :min="ROLL_EFFICIENCY_MIN"
            :max="ROLL_EFFICIENCY_MAX"
            density="compact"
            hide-details
            label="Эффективность"
            :disabled="formLocked"
          />
          <ClampedNumberField
            v-model="initiatorFreeSize"
            :min="-3"
            :max="3"
            density="compact"
            hide-details
            label="Размер"
            :disabled="formLocked"
          />
          <ClampedNumberField
            v-model="initiatorAdv"
            :min="-ROLL_ADV_MAX"
            :max="ROLL_ADV_MAX"
            density="compact"
            hide-details
            label="Преим./помехи"
            :disabled="formLocked"
          />
        </div>

        <template v-if="launchKind === 'solo'">
          <div v-if="fromState" class="text-caption text-medium-emphasis mt-3">Сложность: {{ fromStateLabel }}</div>
          <div v-else-if="askDifficulty && canEdit" class="check-launch-row mt-3">
            <ClampedNumberField v-model="askBase" :min="0" :max="40" density="compact" hide-details label="Сложность" />
            <ClampedNumberField v-model="askSize" :min="-3" :max="3" density="compact" hide-details label="Размер" />
          </div>
          <div v-else class="text-caption text-medium-emphasis mt-3">Сложность {0|0}</div>
        </template>

        <template v-else>
          <v-select
            v-model="opponentKey"
            :items="opponentOptions"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details
            label="Оппонент"
            class="mt-3 mb-3"
            :disabled="lockedParticipants"
          />
          <div class="check-launch-row">
            <v-btn-toggle
              :model-value="opponentUseFree ? 'free' : 'characteristic'"
              density="compact"
              variant="outlined"
              color="primary"
              mandatory
              :disabled="formLocked"
              @update:model-value="opponentUseFree = $event === 'free' || opponentChars.size === 0"
            >
              <v-btn value="characteristic" :disabled="opponentChars.size === 0">Характеристика</v-btn>
              <v-btn value="free">Свободный пул</v-btn>
            </v-btn-toggle>
          </div>
          <div v-if="!opponentUseFree" class="check-launch-row mt-2">
            <v-select
              :model-value="opponentCharacteristic"
              :items="opponentCharItems"
              item-title="name"
              item-subtitle="valueLabel"
              item-value="code"
              density="compact"
              variant="outlined"
              hide-details
              label="Характеристика оппонента"
              :disabled="formLocked || (!overrideAllowed && !!selectedSpec?.characteristic_code)"
              @update:model-value="
                opponentTouched = true;
                opponentCharacteristic = $event as string;
              "
            />
            <ClampedNumberField
              v-model="opponentAdv"
              :min="-ROLL_ADV_MAX"
              :max="ROLL_ADV_MAX"
              density="compact"
              hide-details
              label="Преим./помехи"
              :disabled="formLocked"
            />
          </div>
          <div v-else class="check-launch-row mt-2">
            <ClampedNumberField
              v-model="opponentFreeDice"
              :min="ROLL_DICE_COUNT_MIN"
              :max="ROLL_DICE_COUNT_MAX"
              density="compact"
              hide-details
              label="Кубы"
              :disabled="formLocked"
            />
            <ClampedNumberField
              v-model="opponentFreeEfficiency"
              :min="ROLL_EFFICIENCY_MIN"
              :max="ROLL_EFFICIENCY_MAX"
              density="compact"
              hide-details
              label="Эффективность"
              :disabled="formLocked"
            />
            <ClampedNumberField
              v-model="opponentFreeSize"
              :min="-3"
              :max="3"
              density="compact"
              hide-details
              label="Размер"
              :disabled="formLocked"
            />
            <ClampedNumberField
              v-model="opponentAdv"
              :min="-ROLL_ADV_MAX"
              :max="ROLL_ADV_MAX"
              density="compact"
              hide-details
              label="Преим./помехи"
              :disabled="formLocked"
            />
          </div>
        </template>

        <v-alert v-if="statusHint" type="info" variant="tonal" density="compact" class="mt-3">{{ statusHint }}</v-alert>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-3">{{ error }}</v-alert>
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="launchKind === 'pairwise' && offer"
          variant="text"
          color="error"
          :loading="busy"
          @click="submitCancel"
          >Игнорировать</v-btn
        >
        <v-spacer />
        <v-btn v-if="myTurn" variant="tonal" :loading="busy" :disabled="formLocked" @click="submitRevise">
          Вернуть правку
        </v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          prepend-icon="mdi-dice-d6"
          :loading="busy"
          :disabled="waitingOnOther || (launchKind === 'pairwise' && !opponentKey) || !initiatorKey"
          @click="submit"
        >
          {{ primaryLabel }}
        </v-btn>
        <v-btn variant="text" @click="close">Закрыть</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.check-launch-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: flex-start;
}
.check-launch-row :deep(.v-select) {
  flex: 1 1 220px;
  min-width: 180px;
}
.check-launch-row :deep(.v-number-input) {
  flex: 1 1 150px;
  min-width: 130px;
  max-width: 220px;
}
</style>
