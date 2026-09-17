<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from 'vue';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';

import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';
import { CONCENTRATION_TOKEN_ASK_INJECT_KEY } from '@/modules/Roleplay/Game/Constant/CONCENTRATION_TOKEN_ASK_INJECT_KEY';

import { bloodLossService } from '@/modules/Roleplay/Game/Service/Instance/bloodLossService';

import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import { reservedExhaustion } from '@/modules/Roleplay/Game/Utils/bloodLossMath';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';

import {
  BLOOD_LOSS_STATE_CODE,
  EXHAUSTION_STATE_CODE,
  POISONING_STATE_CODE,
  WOUND_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import {
  characterOverviewService,
  weaponProficiencyService,
  characterBuildService,
  characterEditorService,
  CharacterCombatSheet,
} from '@/modules/Roleplay/Character/init';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { characteristicRollService } from '@/modules/Roleplay/Game/Service/Instance/characteristicRollService';

import type { CombatStateOption } from '@/modules/Roleplay/Game/Dto/CombatStateOption';
import type { CombatStateRow } from '@/modules/Roleplay/Game/Dto/CombatStateRow';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { asProcessAbilitySpec, findRuleByRef } from '@/modules/Roleplay/Game/Utils/combatActions';

import type { CharacterPoisonValue } from '@/modules/Roleplay/Character/Dto/CharacterPoisonValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import CombatCardCharacteristicTile from '@/modules/Roleplay/Game/Component/Detail/CombatCardCharacteristicTile.vue';
import CombatResourceTile from '@/modules/Roleplay/Game/Component/Detail/CombatResourceTile.vue';
import CombatProcessTile from '@/modules/Roleplay/Game/Component/Detail/CombatProcessTile.vue';
import CombatStateTile from '@/modules/Roleplay/Game/Component/Detail/CombatStateTile.vue';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { CombatProcessRow } from '@/modules/Roleplay/Game/Dto/CombatProcessRow';
import { electrochargeService } from '@/modules/Roleplay/Game/Service/Instance/electrochargeService';
import { spellCastOptionsService } from '@/modules/Roleplay/Game/Service/Instance/spellCastOptionsService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { combatProcessListService } from '@/modules/Roleplay/Game/Service/Instance/combatProcessListService';
import { committedActionFlowService } from '@/modules/Roleplay/Game/Service/Instance/committedActionFlowService';
import { formatSustainDropMessage } from '@/modules/Roleplay/Game/Utils/attackDamageMessage';
import { formatProcessEffect } from '@/modules/Roleplay/Game/Utils/processMessage';
import type { CombatStateDetailRow } from '@/modules/Roleplay/Game/Dto/CombatStateDetailRow';
import type { CombatStateEditKind } from '@/modules/Roleplay/Game/Enum/CombatStateEditKind';
import type { CombatStateLinkedAction } from '@/modules/Roleplay/Game/Dto/CombatStateLinkedAction';
import type { ActionLaunchHint } from '@/modules/Roleplay/Game/Dto/ActionLaunchHint';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { InventoryItemOverview } from '@/modules/Roleplay/Character/Dto/Overview/InventoryItemOverview';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/init';

const props = defineProps<{
  /** Слайд-овер открыт (v-model). */
  open: boolean;
  /** Участник карточки (entityKey); null — карточка не выбрана. */
  entityKey: CombatEntityKey | null;
  gameId: number;
  chatId: number | null;
  memberships: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  mechanics: Mechanic[];
  /** ГМ управляет любым участником (CD-6); игрок — только своим approved-персонажем. */
  canEdit: boolean;
  currentUserId: number | null;
  /** Макросы быстрых бросков per entityKey (CD-8) — для звёздочки на тайлах. */
  quickRolls: Record<string, string[]>;
  spaceId: number;
  rulesRevision: number;
  /** Счётчик боевых мутаций снаружи (удар, истощение) — перечитать оверлеи. */
  overlayRevision?: number;
  processSessions?: Record<CombatEntityKey, ProcessSession>;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  /** Переключить макрос быстрого броска (add/remove) для участника карточки. */
  'toggle-quick-roll': [entityKey: string, ruleCode: string];
  /** Оверлей боевых изменений мутировал (ресурс/состояние) — для обновления соседних блоков (школа инициативы). */
  'overlay-changed': [];
  'launch-hit': [payload: { attackerKey: CombatEntityKey; attack: AttackOverview }];
  'launch-injury': [];
  'launch-charge-cast': [payload: { casterKey: CombatEntityKey; sustainId: string }];
  'launch-action': [hint: ActionLaunchHint];
}>();

const sendChat = combatChatSendService.sendCombatChat(props.gameId);
const askTokenSpend = inject(CONCENTRATION_TOKEN_ASK_INJECT_KEY, undefined);

const isOpen = ref(props.open);
watch(
  () => props.open,
  (value) => {
    isOpen.value = value;
  },
);
watch(isOpen, (value) => {
  if (!value) emit('update:open', false);
});

const overlays = ref<GameCombatOverlay[]>([]);
const activeSpells = ref<ActiveSpell[]>([]);
const pendingEffects = ref<PendingActionEffect[]>([]);
const committedSessions = ref<Record<CombatEntityKey, CommittedActionSession>>({});
/** Счётчик, чтобы overview пересобрался даже если версия листа та же ссылка (мутация НПС). */
const viewEpoch = ref(0);
const error = ref<string | null>(null);
const pickerOpen = ref(false);
const poisonAddOpen = ref(false);
const poisonDraft = ref<CharacterPoisonValue | null>(null);
const poisonDraftRuleId = ref('');
const poisonDraftType = ref('');
const poisonDraftStrength = ref<DimensionalNumberValue>({ base: 1, size: 0 });
const cardTab = ref('overview');
const collapsed = ref<string[]>([]);
const { keywords, fetchTags } = useKeywords();

const activeProcess = computed(() =>
  props.entityKey && props.processSessions ? (props.processSessions[props.entityKey] ?? null) : null,
);
const activeEffectLabels = computed(() =>
  pendingEffects.value.map((pending) => {
    const source = props.rules.find((rule) => rule.code === pending.sourceRuleCode)?.name ?? 'Временный эффект';

    return `${source}: ${actionEffectService.describe(pending.effect)}`;
  }),
);

onMounted(() => {
  if (keywords.value.length === 0) void fetchTags();
});

const overlay = computed(() => {
  if (props.entityKey === null) return null;

  return overlays.value.find((item) => item.entityKey === props.entityKey) ?? null;
});

const model = computed(() => {
  if (props.entityKey === null) return null;

  return combatCardModelService.combatCardModel(
    props.entityKey,
    props.memberships,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlay.value,
  );
});

const effectiveVersion = computed(() => model.value?.effectiveVersion ?? null);

const sheetConfig = computed<CharacterCreationConfig>(() => {
  const version = effectiveVersion.value;
  if (!version) return { osTotal: null, orTotal: null, moneyBudget: null };

  return {
    osTotal: version.budgets?.osTotal ?? null,
    orTotal: version.points.orTotal ?? null,
    moneyBudget: version.budgets?.moneyBudget ?? null,
  };
});

const sheetBuild = computed(() =>
  effectiveVersion.value ? characterBuildService.fromVersion(effectiveVersion.value, props.spaceId, props.rules) : null,
);

const sheetModel = computed(() => {
  if (!sheetBuild.value || props.rules.length === 0) return null;

  return characterEditorService.build(
    sheetBuild.value,
    props.rules,
    sheetConfig.value,
    keywords.value,
    props.mechanics,
  );
});

const overview = computed(() => {
  void viewEpoch.value;
  const version = effectiveVersion.value;
  if (!version) return null;
  const live = sheetModel.value;
  const patched = live
    ? {
        ...version,
        characteristics: live.characteristics.map((characteristic) => ({
          ruleCode: characteristic.ruleCode,
          base: characteristic.base,
          modifiers: characteristic.modifiers,
        })),
      }
    : version;

  return characterOverviewService.build(patched, props.rules);
});

const stateRows = computed(() =>
  effectiveVersion.value ? combatCardModelService.combatStateRows(effectiveVersion.value.states, props.rules) : [],
);

const processRows = computed(() => {
  if (!props.entityKey) return [];

  return combatProcessListService.listRows({
    entityKey: props.entityKey,
    processSession: activeProcess.value,
    committedAction: props.entityKey ? (committedSessions.value[props.entityKey] ?? null) : null,
    activeSpells: activeSpells.value,
    version: effectiveVersion.value,
    states: effectiveVersion.value?.states ?? [],
    rules: props.rules,
  });
});

type CombatStateTileModel = {
  key: string;
  name: string;
  iconCode: string | null;
  leftLabel: string;
  valueLabel: string;
  editKind: CombatStateEditKind;
  current: number;
  minValue: number;
  details: CombatStateDetailRow[];
  index: number;
  code: string;
  dimensionalValue: DimensionalNumberValue | null;
  poison: CharacterPoisonValue | null;
  actionLabel: string | null;
  sustainId: string | null;
  woundInternal: boolean;
  woundHeld: boolean;
  linkedActions: CombatStateLinkedAction[];
};

function stateTileDetails(state: CharacterStateValue, row: CombatStateRow): CombatStateDetailRow[] {
  const rows: CombatStateDetailRow[] = [];
  if (row.code === WOUND_STATE_CODE) {
    const migrated = woundInstanceService.migrate(state);
    const payload = woundInstanceService.payload(migrated);
    rows.push({ label: 'Перевязка', value: String(payload.bandage) });
    rows.push({ label: 'Свёртывание', value: String(payload.clotting) });
    rows.push({ label: 'Кровопотеря в ход', value: String(woundInstanceService.tick(migrated)) });
    if (!props.canEdit) {
      rows.push({ label: 'Внутренняя', value: payload.internal ? 'да' : 'нет' });
    }
    rows.push({ label: 'Зажата', value: payload.heldBy ? 'да' : 'нет' });

    return rows;
  }
  if (state.maim) {
    rows.push({ label: 'Срок', value: state.maim.permanent ? 'постоянное' : 'временное' });
    if (!state.maim.permanent && state.maim.healTotal != null && state.maim.healUnit) {
      const unit =
        state.maim.healUnit === 'days'
          ? 'дн.'
          : state.maim.healUnit === 'months'
            ? 'мес.'
            : state.maim.healUnit === 'years'
              ? 'лет'
              : 'дек.';
      rows.push({ label: '−1 силы за', value: `${state.maim.healTotal} ${unit}` });
      const total = state.maim.healTotal * Math.max(0, state.value ?? 0);
      rows.push({ label: 'Полностью пройдёт за', value: `${total} ${unit}` });
    }
    rows.push({ label: 'Обезображивающее', value: state.maim.disfiguring ? 'да' : 'нет' });
    rows.push({ label: 'Смертельное', value: state.maim.lethal ? 'да' : 'нет' });
  } else if (state.boundSustainId) {
    const sustain = activeSpells.value.find((spell) => spell.id === state.boundSustainId);
    const spellName = sustain
      ? (findRuleByRef(props.rules, sustain.spellCode)?.name ?? sustain.spellCode)
      : state.boundSustainId;
    rows.push({ label: 'Поддержание', value: spellName });
  } else if (state.boundSourceKey) {
    const sourceName =
      spellCastOptionsService
        .listSources(effectiveVersion.value, props.rules)
        .find((entry) => entry.key === state.boundSourceKey)?.name ?? state.boundSourceKey;
    rows.push({ label: 'Ядро', value: sourceName });
  } else if (row.code !== POISONING_STATE_CODE && row.valueType !== 'dimensional' && row.summary) {
    rows.push({ label: 'Сводка', value: row.summary });
  }

  return rows;
}

function stateTileValue(state: CharacterStateValue | undefined, row: CombatStateRow): string {
  if (!state) return row.valueType === 'flag' ? '•' : '0';
  if (row.code === POISONING_STATE_CODE) {
    const strength = combatCardModelService.resolvedPoisonStrength(state, props.rules);

    return strength ? new DimensionalNumber(strength).toString() : '—';
  }
  if (row.valueType === 'number') return String(state.value ?? 0);
  if (state.dimensionalValue) return new DimensionalNumber(state.dimensionalValue).toString();
  if (row.valueType === 'flag') return '•';

  return stateValue(state) || '•';
}

function tileEditKind(row: CombatStateRow): CombatStateEditKind {
  if (row.code === WOUND_STATE_CODE) return 'wound';
  if (row.code === POISONING_STATE_CODE) return 'poison';
  if (row.valueType === 'dimensional') return 'dimensional';
  if (row.valueType === 'number') return 'numeric';

  return 'none';
}

const stateTiles = computed((): CombatStateTileModel[] => {
  const states = effectiveVersion.value?.states ?? [];
  const version = effectiveVersion.value;
  const reserved = version
    ? reservedExhaustion(injuryCheckService.overlayStateTotal(version, props.rules, BLOOD_LOSS_STATE_CODE))
    : 0;
  const tiles: CombatStateTileModel[] = [];
  for (const row of stateRows.value) {
    for (const index of row.indices) {
      const state = states[index];
      const timeLabel = state ? combatCardModelService.maimTotalDurationLabel(state) : '';
      const isPoison = row.code === POISONING_STATE_CODE;
      const name = isPoison && state ? combatCardModelService.poisonName(state, props.rules) : row.name;
      const current = row.valueType === 'number' ? (state?.value ?? 0) : 0;
      tiles.push({
        key: `${row.ruleCode}-${index}`,
        name,
        iconCode: row.iconCode,
        leftLabel: timeLabel ? `${name} ${timeLabel}` : name,
        valueLabel: stateTileValue(state, row),
        editKind: tileEditKind(row),
        current,
        minValue: row.code === EXHAUSTION_STATE_CODE ? reserved : 0,
        details: state ? stateTileDetails(state, row) : [],
        index,
        code: row.code,
        dimensionalValue: state?.dimensionalValue ?? null,
        poison: isPoison && state ? combatCardModelService.resolvedPoisonValue(state, props.rules) : null,
        actionLabel:
          state && electrochargeService.canOpenCast(state, activeSpells.value, props.rules) ? 'Сотворить' : null,
        sustainId: state?.boundSustainId ?? null,
        woundInternal: state ? woundInstanceService.payload(state).internal : false,
        woundHeld: state ? woundInstanceService.isHeld(state) : false,
        linkedActions: row.linkedActions,
      });
    }
  }

  return tiles;
});

const poisonSelectItems = computed(() => [
  { title: 'Свой яд', value: '' },
  ...combatCardModelService.poisonRuleOptions(props.rules).map((item) => ({ title: item.name, value: item.ruleCode })),
]);

const damageTypeSelectItems = computed(() =>
  ruleReferenceService.damageTypeOptions(props.rules).map((item) => ({ title: item.name, value: item.code })),
);

const stateOptions = computed(() => combatCardModelService.statePickerOptions(props.rules));

const proficiencyLevels = computed(() =>
  effectiveVersion.value
    ? weaponProficiencyService.weaponProficiencyLevels(effectiveVersion.value.abilities, props.rules)
    : new Map(),
);

const senses = computed(() => effectiveVersion.value?.senses ?? []);

const primarySimple = computed(
  () => overview.value?.characteristics.filter((item) => item.group === 'primary' && !item.derived) ?? [],
);
const primaryDerived = computed(
  () => overview.value?.characteristics.filter((item) => item.group === 'primary' && item.derived) ?? [],
);
const importantCharacteristics = computed(
  () => overview.value?.characteristics.filter((item) => item.group === 'important') ?? [],
);
const magicCharacteristics = computed(
  () => overview.value?.characteristics.filter((item) => item.group === 'magic') ?? [],
);
const secondaryCharacteristics = computed(
  () => overview.value?.characteristics.filter((item) => item.group === 'secondary') ?? [],
);

function isSectionOpen(key: string): boolean {
  return !collapsed.value.includes(key);
}

function toggleSection(key: string): void {
  collapsed.value = isSectionOpen(key) ? [...collapsed.value, key] : collapsed.value.filter((item) => item !== key);
}

const speaker = computed<ChatSpeaker>(() => {
  const current = model.value;
  if (!current) return { kind: 'gm' };

  return current.kind === 'character'
    ? { kind: 'character', characterId: current.entityId, characterName: current.name }
    : { kind: 'npc', npcId: current.entityId, npcName: current.name };
});

let overlaysLoadId = 0;

async function loadOverlays(): Promise<void> {
  if (!props.open || props.entityKey === null) return;
  const loadId = ++overlaysLoadId;
  error.value = null;
  try {
    const next = await getGameApi().getCombatOverlays(props.gameId);
    const allPendingEffects = await getGameApi().getPendingActionEffects(props.gameId);
    const spells = await getGameApi().getActiveSpells(props.gameId);
    const committed = await getGameApi()
      .getCommittedActionSessions(props.gameId)
      .catch(() => ({}));
    if (loadId !== overlaysLoadId) return;
    overlays.value = combatOverlayService.preferNewerCombatOverlays(overlays.value, next);
    pendingEffects.value = allPendingEffects[props.entityKey] ?? [];
    activeSpells.value = spells;
    committedSessions.value = committed;
    viewEpoch.value += 1;
  } catch (e) {
    if (loadId !== overlaysLoadId) return;
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить данные боя';
  }
}

watch(
  () => [props.open, props.entityKey, props.overlayRevision] as const,
  () => void loadOverlays(),
  { immediate: true },
);

async function abortProcessRow(row: CombatProcessRow): Promise<void> {
  if (!props.entityKey || !model.value?.canEdit) return;
  error.value = null;
  try {
    if (row.kind === 'process') {
      await abortProcessSession();
    } else if (row.kind === 'committed-action') {
      await abortCommittedAction();
    } else {
      await abortSustainedSpell(row.id);
    }
    emit('overlay-changed');
    await loadOverlays();
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось оборвать';
  }
}

async function abortProcessSession(): Promise<void> {
  const key = props.entityKey;
  const session = activeProcess.value;
  if (!key || !session) return;
  const processRule = findRuleByRef(props.rules, session.processRuleCode);
  const processSpec = processRule ? asProcessAbilitySpec(processRule) : null;
  if (
    !processRule ||
    !processSpec ||
    !processSessionService.canInterruptNormally(processSpec, session.currentStepCode)
  ) {
    throw new Error('Текущий процесс нельзя прервать обычным способом');
  }
  await getGameApi().setProcessSession(props.gameId, key, null);
  const completionEffects = actionEffectService.effectsAfterProcess(processRule);
  const nextEffects = [...pendingEffects.value, ...completionEffects];
  pendingEffects.value = nextEffects;
  await getGameApi().setCombatActionEffects(props.gameId, key, nextEffects);
  if (props.chatId === null) return;
  const effectText = completionEffects.length
    ? ` Эффект: ${completionEffects.map((item) => formatProcessEffect(item.effect, props.rules)).join('; ')}.`
    : '';
  await sendChat(`${processRule.name} прекращён.${effectText}`, [], props.chatId, speaker.value);
}

async function abortCommittedAction(): Promise<void> {
  const key = props.entityKey;
  if (!key) return;
  const session = committedSessions.value[key];
  if (!session) return;
  await committedActionFlowService.abort(props.gameId, session, props.rules, props.chatId, speaker.value, sendChat);
}

async function abortSustainedSpell(id: string): Promise<void> {
  const spell = activeSpells.value.find((entry) => entry.id === id);
  if (!spell) return;
  await dropBoundCharges(spell.casterKey, spell.id);
  await getGameApi().dropActiveSpell(props.gameId, spell.id);
  activeSpells.value = activeSpells.value.filter((entry) => entry.id !== spell.id);
  if (props.chatId === null) return;
  const rule = findRuleByRef(props.rules, spell.spellCode);
  await sendChat(
    formatSustainDropMessage({
      casterKey: spell.casterKey,
      casterName: model.value?.name ?? '',
      spellRuleCode: spell.spellCode,
      spellName: rule?.name ?? spell.spellCode,
      lostSource: false,
      rules: props.rules,
    }),
    [],
    props.chatId,
    speaker.value,
  );
}

async function dropBoundCharges(entityKey: CombatEntityKey, sustainId: string): Promise<void> {
  const states = effectiveVersion.value?.states ?? [];
  for (const index of electrochargeService.boundIndices(states, sustainId)) {
    await getGameApi().removeCombatState(props.gameId, entityKey, index);
  }
}

function launchChargeCast(sustainId: string): void {
  if (!props.entityKey || !model.value?.canEdit) return;
  emit('launch-charge-cast', { casterKey: props.entityKey, sustainId });
}

function launchStateAction(tile: CombatStateTileModel, actionCode: string): void {
  if (!props.entityKey) return;
  emit('launch-action', { actionCode, targetKey: props.entityKey, stateIndex: tile.index });
}

function launchHit(attack: AttackOverview): void {
  if (!props.entityKey) return;
  if (props.processSessions?.[props.entityKey]) return;
  if (committedSessions.value[props.entityKey]) return;
  emit('launch-hit', { attackerKey: props.entityKey, attack });
}

function applyOverlay(result: GameCombatOverlay): void {
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, result);
  viewEpoch.value += 1;
  emit('overlay-changed');
}

function characteristicLabel(characteristic: CharacteristicOverview): string {
  return characteristic.shortName ?? characteristic.name;
}

async function roll(characteristic: CharacteristicOverview, name?: string): Promise<void> {
  const rollName = name ?? characteristicLabel(characteristic);
  if (props.chatId === null) return;
  error.value = null;
  try {
    const rule = props.rules.find((candidate) => candidate.code === characteristic.ruleCode);
    const result = characteristicRollService.rollCharacteristic(
      {
        name: rollName,
        value: characteristic.value,
        ruleCode: characteristic.ruleCode,
        characteristicCode: rule?.type === 'characteristic' ? rule.code : null,
        actorKey: props.entityKey ?? undefined,
      },
      props.rules,
      props.mechanics,
    );
    await sendChat(rollName, [{ type: ROLL_ATTACHMENT_TYPE, payload: result }], props.chatId, speaker.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить бросок';
  }
}

function onTileRoll(characteristic: CharacteristicOverview, name: string): void {
  void roll(characteristic, name);
}

function onStarToggle(characteristic: CharacteristicOverview): void {
  if (!model.value) return;
  emit('toggle-quick-roll', model.value.entityKey, characteristic.ruleCode);
}

function isStarred(ruleCode: string): boolean {
  if (!model.value) return false;

  return (props.quickRolls[model.value.entityKey] ?? []).includes(ruleCode);
}

function clampResource(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

async function changeResource(resource: ResourceOverview, delta: number): Promise<void> {
  if (!model.value) return;
  error.value = null;
  try {
    const current: DimensionalNumberValue = {
      base: clampResource(resource.current.base + delta, 0, resource.max.base),
      size: resource.current.size,
    };
    const result = await getGameApi().setCombatResource(
      props.gameId,
      model.value.entityKey,
      resource.ruleCode,
      current,
    );
    const spentAp = resource.ruleCode === ACTION_POINTS_CODE ? resource.current.base - current.base : 0;
    if (spentAp > 0) {
      const nextEffects = actionEffectService.afterDeclaredAction(pendingEffects.value, spentAp, {
        isAttack: false,
        component: 'strike',
        baseCost: spentAp,
      });
      pendingEffects.value = nextEffects;
      await getGameApi().setCombatActionEffects(props.gameId, model.value.entityKey, nextEffects);
    }
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось изменить ресурс';
  }
}

async function addState(option: CombatStateOption): Promise<void> {
  if (!model.value) return;
  if (option.code === POISONING_STATE_CODE) {
    pickerOpen.value = false;
    const first = combatCardModelService.poisonRuleOptions(props.rules)[0];
    fillPoisonDraft(combatCardModelService.poisonValueFromRule(props.rules, first?.ruleCode ?? null));
    poisonAddOpen.value = true;

    return;
  }
  error.value = null;
  pickerOpen.value = false;
  try {
    const state: CharacterStateValue = {
      stateRuleCode: option.ruleCode,
      ...combatCardModelService.defaultStateEntry(option, props.rules),
    };
    const result = await getGameApi().addCombatState(props.gameId, model.value.entityKey, state);
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось добавить состояние';
  }
}

function fillPoisonDraft(value: CharacterPoisonValue): void {
  poisonDraft.value = value;
  poisonDraftRuleId.value = value.poisonRuleCode ?? '';
  poisonDraftType.value = value.damage_type_code ?? '';
  poisonDraftStrength.value = { ...(value.strength ?? { base: 1, size: 0 }) };
}

function onPoisonAddRuleChange(next: unknown): void {
  fillPoisonDraft(
    combatCardModelService.poisonValueFromRule(props.rules, typeof next === 'string' && next ? next : null),
  );
}

async function confirmPoisonAdd(): Promise<void> {
  if (!model.value) return;
  const option = stateOptions.value.find((item) => item.code === POISONING_STATE_CODE);
  if (!option) return;
  error.value = null;
  poisonAddOpen.value = false;
  try {
    const poison: CharacterPoisonValue = {
      poisonRuleCode: poisonDraftRuleId.value || null,
      damage_type_code: poisonDraftType.value || undefined,
      strength: { ...poisonDraftStrength.value },
      periodicity: poisonDraft.value?.periodicity,
      decay: poisonDraft.value?.decay,
    };
    const result = await getGameApi().addCombatState(props.gameId, model.value.entityKey, {
      stateRuleCode: option.ruleCode,
      poison,
    });
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось добавить состояние';
  }
}

async function setStateValue(index: number, value?: number): Promise<void> {
  if (!model.value) return;
  error.value = null;
  try {
    const result = await getGameApi().setCombatStateValue(props.gameId, model.value.entityKey, index, value);
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось изменить состояние';
  }
}

async function removeState(index: number): Promise<void> {
  if (!model.value) return;
  error.value = null;
  try {
    const result = await getGameApi().removeCombatState(props.gameId, model.value.entityKey, index);
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось убрать состояние';
  }
}

function stateValue(state: CharacterStateValue): string {
  if (state.value !== undefined) return String(state.value);
  if (state.dimensionalValue) return new DimensionalNumber(state.dimensionalValue).toString();

  return '';
}

function cardSpeaker(): ChatSpeaker {
  const card = model.value;
  if (!card) return { kind: 'gm' };

  return card.kind === 'npc'
    ? { kind: 'npc', npcId: card.entityId, npcName: card.name }
    : { kind: 'character', characterId: card.entityId, characterName: card.name };
}

async function afterStateSideEffects(
  code: string,
  bloodDelta: number,
  exhaustionChange: 'increase' | 'decrease' = 'increase',
): Promise<void> {
  const version = effectiveVersion.value;
  const card = model.value;
  if (!version || !card) return;
  const send = (content: string, attachments: ChatAttachment[], chatId: number, speaker: ChatSpeaker) =>
    sendChat(content, attachments, chatId, speaker);
  if (code === BLOOD_LOSS_STATE_CODE && bloodDelta > 0) {
    const nextOverlay = await bloodLossService.applyBloodLossTick({
      version,
      overlay: overlay.value,
      delta: bloodDelta,
      endurance: overview.value ? attackDamageService.enduranceOf(overview.value, props.rules) : 1,
      rules: props.rules,
      mechanics: props.mechanics,
      gameId: props.gameId,
      targetKey: card.entityKey,
      targetName: card.name,
      chatId: props.chatId,
      speaker: cardSpeaker(),
      sendMessage: send,
      askTokenSpend,
    });
    if (nextOverlay) applyOverlay(nextOverlay);

    return;
  }
  if (code === EXHAUSTION_STATE_CODE) {
    const exhaustion = await exhaustionCheckService.applyExhaustionCheck({
      version,
      overlay: overlay.value,
      rules: props.rules,
      mechanics: props.mechanics,
      gameId: props.gameId,
      targetKey: card.entityKey,
      targetName: card.name,
      chatId: props.chatId,
      speaker: cardSpeaker(),
      change: exhaustionChange,
      sendMessage: send,
      askTokenSpend,
    });
    if (exhaustion.overlay) applyOverlay(exhaustion.overlay);
  }
}

async function applyStateTile(tile: CombatStateTileModel, next: number): Promise<void> {
  if (tile.code === WOUND_STATE_CODE) {
    await applyWoundTile(tile, { value: next, internal: tile.woundInternal });

    return;
  }
  const version = effectiveVersion.value;
  const current = version?.states[tile.index]?.value ?? 0;
  let value = Math.max(0, Math.floor(next));
  if (tile.code === EXHAUSTION_STATE_CODE && version) {
    const reserved = reservedExhaustion(
      injuryCheckService.overlayStateTotal(version, props.rules, BLOOD_LOSS_STATE_CODE),
    );
    value = Math.max(value, reserved);
  }
  if (value === current) return;
  if (tile.code === BLOOD_LOSS_STATE_CODE && value > current) {
    await afterStateSideEffects(tile.code, value - current);

    return;
  }
  if (value <= 0) {
    await removeState(tile.index);
    if (tile.code === EXHAUSTION_STATE_CODE) await afterStateSideEffects(tile.code, 0, 'decrease');

    return;
  }
  await setStateValue(tile.index, value);
  if (tile.code === EXHAUSTION_STATE_CODE) {
    await afterStateSideEffects(tile.code, 0, value > current ? 'increase' : 'decrease');
  }
}

async function replaceStateAt(index: number, state: CharacterStateValue): Promise<void> {
  if (!model.value) return;
  error.value = null;
  try {
    const result = await getGameApi().replaceCombatState(props.gameId, model.value.entityKey, index, state);
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось изменить состояние';
  }
}

async function applyDimensionalTile(tile: CombatStateTileModel, next: DimensionalNumberValue): Promise<void> {
  const version = effectiveVersion.value;
  const prev = version?.states[tile.index];
  if (!prev) return;
  if (new DimensionalNumber(next).toNumber() <= 0) {
    await removeState(tile.index);

    return;
  }
  await replaceStateAt(tile.index, { ...prev, dimensionalValue: next });
}

async function applyPoisonTile(tile: CombatStateTileModel, next: CharacterPoisonValue): Promise<void> {
  const version = effectiveVersion.value;
  const prev = version?.states[tile.index];
  if (!prev) return;
  await replaceStateAt(tile.index, { ...prev, poison: next });
}

async function applyWoundTile(tile: CombatStateTileModel, next: { value: number; internal: boolean }): Promise<void> {
  const prev = effectiveVersion.value?.states[tile.index];
  if (!prev) return;
  await replaceStateAt(
    tile.index,
    woundInstanceService.setInternal(woundInstanceService.setStrength(prev, next.value), next.internal),
  );
}

async function applyWoundInternal(tile: CombatStateTileModel, internal: boolean): Promise<void> {
  const prev = effectiveVersion.value?.states[tile.index];
  if (!prev) return;
  await replaceStateAt(tile.index, woundInstanceService.setInternal(prev, internal));
}

async function releaseWoundHold(tile: CombatStateTileModel): Promise<void> {
  const prev = effectiveVersion.value?.states[tile.index];
  if (!prev) return;
  await replaceStateAt(tile.index, woundInstanceService.releaseSqueeze(prev));
}

async function removeStateTile(tile: CombatStateTileModel): Promise<void> {
  await removeState(tile.index);
}

function kindIcon(): string {
  return model.value?.kind === 'npc' ? 'mdi-robot-outline' : 'mdi-account';
}

async function toggleEquipped(item: InventoryItemOverview): Promise<void> {
  if (!model.value || !model.value.canEdit) return;
  error.value = null;
  try {
    const result = await getGameApi().setCombatItemEquipped(
      props.gameId,
      model.value.entityKey,
      item.id,
      !item.equipped,
    );
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось изменить экипировку';
  }
}

async function setOccupyHands(itemId: number, occupyHands: number): Promise<void> {
  if (!model.value || !model.value.canEdit) return;
  error.value = null;
  try {
    const result = await getGameApi().setCombatItemOccupyHands(
      props.gameId,
      model.value.entityKey,
      itemId,
      occupyHands,
    );
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось изменить занятость рук';
  }
}

function onSheetToggleEquipped(itemId: number): void {
  const item = overview.value?.inventory.find((entry) => entry.id === itemId);
  if (item) void toggleEquipped(item);
}
</script>

<template>
  <SlidePanel v-model="isOpen" width="680px">
    <template #header>
      <div class="combat-card-header">
        <v-icon :icon="kindIcon()" size="18" color="primary" class="flex-shrink-0" />
        <span class="combat-card-header__name text-subtitle-2 font-weight-medium text-truncate">
          {{ model?.name ?? 'Карточка' }}
        </span>
        <v-chip v-if="model && !model.canEdit" size="x-small" variant="tonal" class="flex-shrink-0">просмотр</v-chip>
        <v-tabs
          v-if="model && effectiveVersion !== null"
          v-model="cardTab"
          density="compact"
          color="primary"
          class="combat-card-header__tabs"
        >
          <v-tab value="overview">Обзор</v-tab>
          <v-tab value="abilities">Способности</v-tab>
          <v-tab value="inventory">Инвентарь</v-tab>
        </v-tabs>
      </div>
    </template>

    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-3">{{ error }}</v-alert>

    <div v-if="model && effectiveVersion === null" class="text-medium-emphasis text-center pa-8">
      Лист участника не заполнен
    </div>

    <template v-if="model && effectiveVersion !== null">
      <v-window v-model="cardTab">
        <v-window-item value="overview">
          <div class="combat-card-panel__body">
            <section class="combat-card-section">
              <button type="button" class="combat-card-section__title" @click="toggleSection('characteristics')">
                <v-icon size="18">{{
                  isSectionOpen('characteristics') ? 'mdi-chevron-down' : 'mdi-chevron-right'
                }}</v-icon>
                Характеристики
              </button>
              <div v-show="isSectionOpen('characteristics')">
                <template v-if="primarySimple.length || primaryDerived.length">
                  <div class="combat-card-section__subtitle">Основные</div>
                  <div v-if="primarySimple.length" class="combat-card-characteristics">
                    <CombatCardCharacteristicTile
                      v-for="characteristic in primarySimple"
                      :key="characteristic.ruleCode"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleCode)"
                      :star-enabled="model.canEdit"
                      @roll="onTileRoll"
                      @star-toggle="onStarToggle"
                    />
                  </div>
                  <div v-if="primaryDerived.length" class="combat-card-characteristics mt-1">
                    <CombatCardCharacteristicTile
                      v-for="characteristic in primaryDerived"
                      :key="characteristic.ruleCode"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleCode)"
                      :star-enabled="model.canEdit"
                      @roll="onTileRoll"
                      @star-toggle="onStarToggle"
                    />
                  </div>
                </template>
                <template v-if="magicCharacteristics.length">
                  <div class="combat-card-section__subtitle">Магические</div>
                  <div class="combat-card-characteristics">
                    <CombatCardCharacteristicTile
                      v-for="characteristic in magicCharacteristics"
                      :key="characteristic.ruleCode"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleCode)"
                      :star-enabled="model.canEdit"
                      @roll="onTileRoll"
                      @star-toggle="onStarToggle"
                    />
                  </div>
                </template>
                <template v-if="importantCharacteristics.length">
                  <div class="combat-card-section__subtitle">Важные</div>
                  <div class="combat-card-characteristics">
                    <CombatCardCharacteristicTile
                      v-for="characteristic in importantCharacteristics"
                      :key="characteristic.ruleCode"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleCode)"
                      :star-enabled="model.canEdit"
                      @roll="onTileRoll"
                      @star-toggle="onStarToggle"
                    />
                  </div>
                </template>
                <template v-if="secondaryCharacteristics.length">
                  <div class="combat-card-section__subtitle">Вторичные</div>
                  <div class="combat-card-characteristics">
                    <CombatCardCharacteristicTile
                      v-for="characteristic in secondaryCharacteristics"
                      :key="characteristic.ruleCode"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleCode)"
                      :star-enabled="model.canEdit"
                      @roll="onTileRoll"
                      @star-toggle="onStarToggle"
                    />
                  </div>
                </template>
              </div>
            </section>

            <!-- Бой: статы и оружия — тайлы как обычные характеристики -->
            <section v-if="overview?.combat" class="combat-card-section">
              <button type="button" class="combat-card-section__title" @click="toggleSection('combat')">
                <v-icon size="18">{{ isSectionOpen('combat') ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
                Бой
              </button>
              <div v-show="isSectionOpen('combat')">
                <v-alert v-if="activeEffectLabels.length" type="info" variant="tonal" density="compact" class="mb-3">
                  <div class="text-subtitle-2 mb-1">Активные эффекты</div>
                  <div v-for="label in activeEffectLabels" :key="label">{{ label }}</div>
                </v-alert>
                <template
                  v-for="(section, sectionKey) in { melee: overview.combat.melee, ranged: overview.combat.ranged }"
                  :key="sectionKey"
                >
                  <template v-if="section">
                    <div class="combat-card-section__subtitle">
                      {{ sectionKey === 'melee' ? 'Ближний бой' : 'Дальний бой' }}
                    </div>
                    <div class="combat-card-characteristics">
                      <CombatCardCharacteristicTile
                        :characteristic="section.stat"
                        :rules="rules"
                        :senses="senses"
                        :proficiency-levels="proficiencyLevels"
                        :rollable="chatId !== null"
                        :roll-name="sectionKey === 'melee' ? 'Ближний бой' : 'Дальний бой'"
                        :starred="isStarred(section.stat.ruleCode)"
                        :star-enabled="model.canEdit"
                        @roll="onTileRoll"
                        @star-toggle="onStarToggle"
                      />
                      <CombatCardCharacteristicTile
                        v-for="weapon in section.weapons"
                        :key="weapon.ruleCode"
                        :characteristic="weapon"
                        :rules="rules"
                        :senses="senses"
                        :proficiency-levels="proficiencyLevels"
                        :rollable="chatId !== null"
                        :starred="isStarred(weapon.ruleCode)"
                        :star-enabled="model.canEdit"
                        @roll="onTileRoll"
                        @star-toggle="onStarToggle"
                      />
                    </div>
                  </template>
                </template>
              </div>
            </section>

            <!-- Ресурсы: тайлы как характеристики (CD-9) -->
            <section class="combat-card-section">
              <button type="button" class="combat-card-section__title" @click="toggleSection('resources')">
                <v-icon size="18">{{ isSectionOpen('resources') ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
                Ресурсы
              </button>
              <div v-show="isSectionOpen('resources')">
                <div v-if="(overview?.resources ?? []).length === 0" class="text-medium-emphasis text-body-2">
                  Ресурсов нет
                </div>
                <div v-else class="combat-card-characteristics">
                  <CombatResourceTile
                    v-for="resource in overview?.resources ?? []"
                    :key="resource.ruleCode"
                    :resource="resource"
                    :rules="rules"
                    :can-edit="model.canEdit"
                    @change="changeResource"
                  />
                </div>
              </div>
            </section>

            <!-- Состояния: типовые контролы + пикер из ревизии -->
            <section class="combat-card-section">
              <button type="button" class="combat-card-section__title" @click="toggleSection('states')">
                <v-icon size="18">{{ isSectionOpen('states') ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
                Состояния
              </button>
              <div v-show="isSectionOpen('states')">
                <div v-if="stateTiles.length === 0" class="text-medium-emphasis text-body-2">Состояний нет</div>
                <div v-else class="combat-card-states">
                  <CombatStateTile
                    v-for="tile in stateTiles"
                    :key="tile.key"
                    :name="tile.name"
                    :icon-code="tile.iconCode"
                    :left-label="tile.leftLabel"
                    :value-label="tile.valueLabel"
                    :details="tile.details"
                    :can-edit="model.canEdit"
                    :edit-kind="tile.editKind"
                    :current="tile.current"
                    :min-value="tile.minValue"
                    :dimensional-value="tile.dimensionalValue"
                    :poison="tile.poison"
                    :poison-items="poisonSelectItems"
                    :damage-type-items="damageTypeSelectItems"
                    :poison-template="(id) => combatCardModelService.poisonValueFromRule(rules, id)"
                    :action-label="tile.actionLabel"
                    :wound-internal="tile.woundInternal"
                    :wound-held="tile.woundHeld"
                    :is-master="canEdit"
                    :linked-actions="tile.linkedActions"
                    @apply="(next) => applyStateTile(tile, next)"
                    @apply-dimensional="(next) => applyDimensionalTile(tile, next)"
                    @apply-poison="(next) => applyPoisonTile(tile, next)"
                    @apply-wound="(next) => applyWoundTile(tile, next)"
                    @apply-internal="(internal) => applyWoundInternal(tile, internal)"
                    @release-hold="releaseWoundHold(tile)"
                    @remove="removeStateTile(tile)"
                    @action="tile.sustainId && launchChargeCast(tile.sustainId)"
                    @launch-action="(code) => launchStateAction(tile, code)"
                  />
                </div>

                <div v-if="model.canEdit && stateOptions.length > 0" class="mt-2 d-flex flex-wrap ga-2">
                  <v-menu v-model="pickerOpen" :close-on-content-click="false" attach :z-index="2200">
                    <template #activator="{ props: menuProps }">
                      <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-plus" v-bind="menuProps">
                        Добавить состояние
                      </v-btn>
                    </template>
                    <v-card min-width="240" max-width="300" elevation="8" border>
                      <v-card-text class="pa-2">
                        <v-list dense max-height="240">
                          <v-list-item
                            v-for="option in stateOptions"
                            :key="option.ruleCode"
                            density="compact"
                            :prepend-icon="option.iconCode ?? 'mdi-star-outline'"
                            :title="option.name"
                            @click="addState(option)"
                          />
                        </v-list>
                      </v-card-text>
                    </v-card>
                  </v-menu>
                  <v-btn size="small" variant="tonal" prepend-icon="mdi-bone" @click="emit('launch-injury')">
                    Увечье
                  </v-btn>
                </div>
              </div>
            </section>

            <section class="combat-card-section">
              <button type="button" class="combat-card-section__title" @click="toggleSection('processes')">
                <v-icon size="18">{{ isSectionOpen('processes') ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
                Процессы
              </button>
              <div v-show="isSectionOpen('processes')">
                <div v-if="processRows.length === 0" class="text-medium-emphasis text-body-2">Процессов нет</div>
                <div v-else class="combat-card-states">
                  <CombatProcessTile
                    v-for="row in processRows"
                    :key="row.id"
                    :row="row"
                    :can-edit="model.canEdit"
                    @abort="abortProcessRow(row)"
                    @charge-cast="launchChargeCast(row.id)"
                  />
                </div>
              </div>
            </section>

            <CharacterCombatSheet
              v-if="model && overview"
              pane="overview"
              :overview="overview"
              :version="effectiveVersion"
              :build="sheetBuild"
              :model="sheetModel"
              :rules="rules"
              :keywords="keywords"
              :can-edit="model.canEdit"
              :character-id="model.entityId"
              :show-favorites="model.kind === 'character'"
              :space-id="spaceId"
              :rules-revision="rulesRevision"
              :defense-open="isSectionOpen('defense')"
              :attacks-open="isSectionOpen('attacks')"
              show-slider
              @toggle-defense="toggleSection('defense')"
              @toggle-attacks="toggleSection('attacks')"
              @launch="launchHit"
              @toggle-equipped="onSheetToggleEquipped"
              @set-occupy-hands="setOccupyHands"
            />
          </div>
        </v-window-item>

        <v-window-item value="abilities">
          <div class="combat-card-panel__tab pa-2">
            <CharacterCombatSheet
              v-if="model"
              pane="abilities"
              :overview="overview"
              :version="effectiveVersion"
              :build="sheetBuild"
              :model="sheetModel"
              :rules="rules"
              :keywords="keywords"
              :can-edit="model.canEdit"
              :character-id="model.entityId"
              :show-favorites="model.kind === 'character'"
              :space-id="spaceId"
              :rules-revision="rulesRevision"
            />
          </div>
        </v-window-item>

        <v-window-item value="inventory">
          <div class="combat-card-panel__tab pa-2">
            <CharacterCombatSheet
              v-if="model"
              pane="inventory"
              :overview="overview"
              :version="effectiveVersion"
              :build="sheetBuild"
              :model="sheetModel"
              :rules="rules"
              :keywords="keywords"
              :can-edit="model.canEdit"
              :character-id="model.entityId"
              :show-favorites="model.kind === 'character'"
              :space-id="spaceId"
              :rules-revision="rulesRevision"
              @toggle-equipped="onSheetToggleEquipped"
              @set-occupy-hands="setOccupyHands"
            />
          </div>
        </v-window-item>
      </v-window>
    </template>
  </SlidePanel>

  <v-dialog v-model="poisonAddOpen" max-width="420">
    <v-card>
      <v-card-title class="text-body-1">Отравление</v-card-title>
      <v-card-text>
        <v-select
          :model-value="poisonDraftRuleId"
          :items="poisonSelectItems"
          label="Яд"
          density="compact"
          hide-details
          @update:model-value="onPoisonAddRuleChange"
        />
        <v-select
          v-model="poisonDraftType"
          class="mt-3"
          :items="damageTypeSelectItems"
          label="Тип урона"
          density="compact"
          hide-details
        />
        <DimensionalNumberInput v-model="poisonDraftStrength" class="mt-3" label="Сила" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="poisonAddOpen = false">Отмена</v-btn>
        <v-btn color="primary" variant="tonal" @click="confirmPoisonAdd">Добавить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.combat-card-panel__body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 12px;
}
.min-width-0 {
  min-width: 0;
}
.combat-card-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.combat-card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-width: 0;
  padding: 4px 8px 0 12px;
}
.combat-card-header__name {
  min-width: 0;
  flex: 1 1 auto;
}
.combat-card-header__tabs {
  flex: 0 0 auto;
  margin-left: auto;
}
.combat-card-header__tabs :deep(.v-tab) {
  min-width: auto;
  padding: 0 10px;
  font-size: 12px;
  letter-spacing: 0.02em;
}
.combat-card-section__heading {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}
.combat-card-section__title {
  display: flex;
  align-items: center;
  gap: 4px;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  text-transform: uppercase;
  letter-spacing: 0.02em;
  text-align: left;
}
.combat-card-pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.combat-card-section__subtitle {
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-top: 4px;
}
.combat-card-row {
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 28px;
}
.combat-card-row__label {
  flex: 1;
  font-size: 13px;
}
.combat-card-row__value {
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
}
.combat-card-characteristics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px 8px;
}
.combat-card-states {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 8px;
}
.combat-card-state__entries {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  padding-left: 8px;
}
.combat-card-state__entry {
  display: flex;
  align-items: center;
  gap: 2px;
}
</style>
