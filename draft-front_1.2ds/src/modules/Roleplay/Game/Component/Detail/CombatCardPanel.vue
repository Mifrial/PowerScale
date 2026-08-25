<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import { sendCombatChat } from '@/modules/Roleplay/Game/Utils/combatChatSend';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import { applyExhaustionCheck } from '@/modules/Roleplay/Game/Utils/applyExhaustionCheck';
import { applyBloodLossTick } from '@/modules/Roleplay/Game/Utils/applyBloodLoss';
import { overlayStateTotal } from '@/modules/Roleplay/Game/Utils/applyInjuryCheck';
import { reservedExhaustion } from '@/modules/Roleplay/Game/Utils/bloodLossMath';
import { enduranceOf } from '@/modules/Roleplay/Game/Utils/applyAttackDamage';
import { BLOOD_LOSS_STATE_CODE, EXHAUSTION_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { rollCharacteristic } from '@/modules/Roleplay/Game/Utils/characteristicRoll';
import {
  combatCardModel,
  combatStateRows,
  defaultStateEntry,
  maimTotalDurationLabel,
  statePickerOptions,
  type CombatStateOption,
  type CombatStateRow,
} from '@/modules/Roleplay/Game/Utils/combatCardModel';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import { weaponProficiencyLevels } from '@/modules/Roleplay/Character/Utils/weaponProficiency';
import CombatCardCharacteristicTile from '@/modules/Roleplay/Game/Component/Detail/CombatCardCharacteristicTile.vue';
import CombatResourceTile from '@/modules/Roleplay/Game/Component/Detail/CombatResourceTile.vue';
import CombatStateTile from '@/modules/Roleplay/Game/Component/Detail/CombatStateTile.vue';
import AbilityTab from '@/modules/Roleplay/Character/Component/Detail/AbilityTab.vue';
import InventoryTab from '@/modules/Roleplay/Character/Component/Editor/InventoryTab.vue';
import AttackTile from '@/modules/Roleplay/Character/Component/Detail/Attacks/AttackTile.vue';
import DefenseValue from '@/modules/Roleplay/Character/Component/Detail/Defense/DefenseValue.vue';
import ArmorTile from '@/modules/Roleplay/Character/Component/Detail/Defense/ArmorTile.vue';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';
import RuleSlider from '@/modules/Roleplay/Rule/Component/RuleSlider.vue';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { InventoryItemOverview } from '@/modules/Roleplay/Character/Dto/Overview/InventoryItemOverview';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { preferNewerCombatOverlays, replaceCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';

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
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  /** Переключить макрос быстрого броска (add/remove) для участника карточки. */
  'toggle-quick-roll': [entityKey: string, ruleId: string];
  /** Оверлей боевых изменений мутировал (ресурс/состояние) — для обновления соседних блоков (школа инициативы). */
  'overlay-changed': [];
  'launch-hit': [payload: { attackerKey: CombatEntityKey; attack: AttackOverview }];
  'launch-injury': [];
}>();

const sendChat = sendCombatChat(props.gameId);

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
/** Счётчик, чтобы overview пересобрался даже если версия листа та же ссылка (мутация НПС). */
const viewEpoch = ref(0);
const error = ref<string | null>(null);
const pickerOpen = ref(false);
const cardTab = ref('overview');
const collapsed = ref<string[]>([]);
const ruleSlider = useRuleDetailSlider();
const keywordStore = useKeywordStore();

onMounted(() => {
  if (keywordStore.keywords.length === 0) void keywordStore.fetchTags();
});

const overlay = computed(() => {
  if (props.entityKey === null) return null;

  return overlays.value.find((item) => item.entityKey === props.entityKey) ?? null;
});

const model = computed(() => {
  if (props.entityKey === null) return null;

  return combatCardModel(
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
    keywordStore.keywords,
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
          ruleId: characteristic.ruleId,
          base: characteristic.base,
          modifiers: characteristic.modifiers,
        })),
      }
    : version;

  return characterOverviewService.build(patched, props.rules);
});

const stateRows = computed(() =>
  effectiveVersion.value ? combatStateRows(effectiveVersion.value.states, props.rules) : [],
);

type CombatStateDetailRow = { label: string; value: string };

type CombatStateTileModel = {
  key: string;
  name: string;
  iconCode: string | null;
  leftLabel: string;
  valueLabel: string;
  numeric: boolean;
  current: number;
  minValue: number;
  details: CombatStateDetailRow[];
  index: number;
  code: string;
};

function stateTileDetails(state: CharacterStateValue, row: CombatStateRow): CombatStateDetailRow[] {
  const rows: CombatStateDetailRow[] = [];
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
  } else if (row.summary) {
    rows.push({ label: 'Сводка', value: row.summary });
  }

  return rows;
}

function stateTileValue(state: CharacterStateValue | undefined, row: CombatStateRow): string {
  if (!state) return row.valueType === 'flag' ? '•' : '0';
  if (row.valueType === 'number') return String(state.value ?? 0);
  if (state.dimensionalValue) return new DimensionalNumber(state.dimensionalValue).toString();
  if (row.valueType === 'flag') return '•';

  return stateValue(state) || '•';
}

const stateTiles = computed((): CombatStateTileModel[] => {
  const states = effectiveVersion.value?.states ?? [];
  const version = effectiveVersion.value;
  const reserved = version ? reservedExhaustion(overlayStateTotal(version, props.rules, BLOOD_LOSS_STATE_CODE)) : 0;
  const tiles: CombatStateTileModel[] = [];
  for (const row of stateRows.value) {
    for (const index of row.indices) {
      const state = states[index];
      const timeLabel = state ? maimTotalDurationLabel(state) : '';
      const current = row.valueType === 'number' ? (state?.value ?? 0) : 0;
      tiles.push({
        key: `${row.ruleId}-${index}`,
        name: row.name,
        iconCode: row.iconCode,
        leftLabel: timeLabel ? `${row.name} ${timeLabel}` : row.name,
        valueLabel: stateTileValue(state, row),
        numeric: row.valueType === 'number',
        current,
        minValue: row.code === EXHAUSTION_STATE_CODE ? reserved : 0,
        details: state ? stateTileDetails(state, row) : [],
        index,
        code: row.code,
      });
    }
  }

  return tiles;
});

const stateOptions = computed(() => statePickerOptions(props.rules));

const proficiencyLevels = computed(() =>
  effectiveVersion.value ? weaponProficiencyLevels(effectiveVersion.value.abilities, props.rules) : new Map(),
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
    if (loadId !== overlaysLoadId) return;
    overlays.value = preferNewerCombatOverlays(overlays.value, next);
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

function launchHit(attack: AttackOverview): void {
  if (!props.entityKey) return;
  emit('launch-hit', { attackerKey: props.entityKey, attack });
}

function applyOverlay(result: GameCombatOverlay): void {
  overlays.value = replaceCombatOverlay(overlays.value, result);
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
    const rule = props.rules.find((candidate) => candidate.id === characteristic.ruleId);
    const result = rollCharacteristic(
      {
        name: rollName,
        value: characteristic.value,
        ruleId: characteristic.ruleId,
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
  emit('toggle-quick-roll', model.value.entityKey, characteristic.ruleId);
}

function isStarred(ruleId: string): boolean {
  if (!model.value) return false;

  return (props.quickRolls[model.value.entityKey] ?? []).includes(ruleId);
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
    const result = await getGameApi().setCombatResource(props.gameId, model.value.entityKey, resource.ruleId, current);
    applyOverlay(result);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось изменить ресурс';
  }
}

async function addState(option: CombatStateOption): Promise<void> {
  if (!model.value) return;
  error.value = null;
  pickerOpen.value = false;
  try {
    const state: CharacterStateValue = { stateRuleId: option.ruleId, ...defaultStateEntry(option) };
    const result = await getGameApi().addCombatState(props.gameId, model.value.entityKey, state);
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
    const overlay = await applyBloodLossTick({
      version,
      delta: bloodDelta,
      endurance: overview.value ? enduranceOf(overview.value, props.rules) : 1,
      rules: props.rules,
      mechanics: props.mechanics,
      gameId: props.gameId,
      targetKey: card.entityKey,
      targetName: card.name,
      chatId: props.chatId,
      speaker: cardSpeaker(),
      sendMessage: send,
    });
    if (overlay) applyOverlay(overlay);

    return;
  }
  if (code === EXHAUSTION_STATE_CODE) {
    const exhaustion = await applyExhaustionCheck({
      version,
      rules: props.rules,
      mechanics: props.mechanics,
      gameId: props.gameId,
      targetKey: card.entityKey,
      targetName: card.name,
      chatId: props.chatId,
      speaker: cardSpeaker(),
      change: exhaustionChange,
      sendMessage: send,
    });
    if (exhaustion.overlay) applyOverlay(exhaustion.overlay);
  }
}

async function applyStateTile(tile: CombatStateTileModel, next: number): Promise<void> {
  const version = effectiveVersion.value;
  const current = version?.states[tile.index]?.value ?? 0;
  let value = Math.max(0, Math.floor(next));
  if (tile.code === EXHAUSTION_STATE_CODE && version) {
    const reserved = reservedExhaustion(overlayStateTotal(version, props.rules, BLOOD_LOSS_STATE_CODE));
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
                      :key="characteristic.ruleId"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleId)"
                      :star-enabled="model.canEdit"
                      @roll="onTileRoll"
                      @star-toggle="onStarToggle"
                    />
                  </div>
                  <div v-if="primaryDerived.length" class="combat-card-characteristics mt-1">
                    <CombatCardCharacteristicTile
                      v-for="characteristic in primaryDerived"
                      :key="characteristic.ruleId"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleId)"
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
                      :key="characteristic.ruleId"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleId)"
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
                      :key="characteristic.ruleId"
                      :characteristic="characteristic"
                      :rules="rules"
                      :senses="senses"
                      :proficiency-levels="proficiencyLevels"
                      :rollable="chatId !== null"
                      :starred="isStarred(characteristic.ruleId)"
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
                        :starred="isStarred(section.stat.ruleId)"
                        :star-enabled="model.canEdit"
                        @roll="onTileRoll"
                        @star-toggle="onStarToggle"
                      />
                      <CombatCardCharacteristicTile
                        v-for="weapon in section.weapons"
                        :key="weapon.ruleId"
                        :characteristic="weapon"
                        :rules="rules"
                        :senses="senses"
                        :proficiency-levels="proficiencyLevels"
                        :rollable="chatId !== null"
                        :starred="isStarred(weapon.ruleId)"
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
                    :key="resource.ruleId"
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
                    :numeric="tile.numeric"
                    :current="tile.current"
                    :min-value="tile.minValue"
                    @apply="(next) => applyStateTile(tile, next)"
                    @remove="removeStateTile(tile)"
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
                            :key="option.ruleId"
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

            <section v-if="overview?.defense" class="combat-card-section">
              <div class="combat-card-section__heading">
                <button type="button" class="combat-card-section__title" @click="toggleSection('defense')">
                  <v-icon size="18">{{ isSectionOpen('defense') ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
                  Защита
                </button>
                <span class="flex-shrink-0" @click.stop>
                  <DefenseValue v-if="overview.defense.constantDefense > 0" :tiers="overview.defense.tiers" />
                </span>
              </div>
              <div v-show="isSectionOpen('defense')" class="combat-card-pair">
                <ArmorTile v-for="armor in overview.defense.armor" :key="armor.itemRuleId" :item="armor" />
                <v-sheet v-if="overview.defense.shield" class="pa-2 rounded border">
                  <div class="d-flex align-center ga-2">
                    <v-icon icon="mdi-shield-outline" color="primary" />
                    <RuleLink :rule-id="overview.defense.shield.itemRuleId" class="text-body-2 font-weight-medium">
                      {{ overview.defense.shield.itemName }} — блокирование
                    </RuleLink>
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    Защита {{ overview.defense.shield.defense }} · эффективность
                    {{ overview.defense.shield.efficiency }}
                  </div>
                </v-sheet>
                <div
                  v-if="overview.defense.armor.length === 0 && !overview.defense.shield"
                  class="text-medium-emphasis text-body-2"
                >
                  Экипированной защиты нет
                </div>
              </div>
            </section>

            <section v-if="(overview?.attacks ?? []).length > 0" class="combat-card-section">
              <button type="button" class="combat-card-section__title" @click="toggleSection('attacks')">
                <v-icon size="18">{{ isSectionOpen('attacks') ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
                Атаки
              </button>
              <div v-show="isSectionOpen('attacks')" class="combat-card-pair">
                <AttackTile
                  v-for="attack in overview?.attacks ?? []"
                  :key="`${attack.itemRuleId}_${attack.profileType}`"
                  variant="combat"
                  :attack="attack"
                  @launch="launchHit"
                />
              </div>
            </section>
          </div>
        </v-window-item>

        <v-window-item value="abilities">
          <div class="combat-card-panel__tab pa-2">
            <AbilityTab
              v-if="effectiveVersion"
              :version="effectiveVersion"
              :rules="rules"
              :rules-loading="false"
              :character-id="model.entityId"
              :show-favorites="model.kind === 'character'"
            />
          </div>
        </v-window-item>

        <v-window-item value="inventory">
          <div class="combat-card-panel__tab pa-2">
            <InventoryTab
              v-if="sheetBuild && sheetModel"
              variant="sheet"
              :build="sheetBuild"
              :model="sheetModel"
              :draft-key="null"
              :rules="rules"
              :keywords="keywordStore.keywords"
              :can-edit="model.canEdit"
              :on-toggle-equipped="onSheetToggleEquipped"
              list-height="calc(100vh - 160px)"
            />
            <div v-else class="text-medium-emphasis text-body-2 pa-4">Инвентарь недоступен</div>
          </div>
        </v-window-item>
      </v-window>
    </template>
  </SlidePanel>

  <RuleSlider
    v-model:open="ruleSlider.state.open"
    :rule-id="ruleSlider.state.ruleId"
    :space-id="spaceId"
    :rules-revision="rulesRevision"
  />
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
