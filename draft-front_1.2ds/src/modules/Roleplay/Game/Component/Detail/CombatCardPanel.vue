<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { rollCharacteristic } from '@/modules/Roleplay/Game/Utils/characteristicRoll';
import {
  combatCardModel,
  combatStateRows,
  defaultStateEntry,
  statePickerOptions,
  type CombatStateOption,
  type CombatStateRow,
} from '@/modules/Roleplay/Game/Utils/combatCardModel';
import { weaponProficiencyLevels } from '@/modules/Roleplay/Character/Utils/weaponProficiency';
import CombatCardCharacteristicTile from '@/modules/Roleplay/Game/Component/Detail/CombatCardCharacteristicTile.vue';
import CombatResourceTile from '@/modules/Roleplay/Game/Component/Detail/CombatResourceTile.vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

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
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  /** Переключить макрос быстрого броска (add/remove) для участника карточки. */
  'toggle-quick-roll': [entityKey: string, ruleId: string];
  /** Оверлей боевых изменений мутировал (ресурс/состояние) — для обновления соседних блоков (школа инициативы). */
  'overlay-changed': [];
}>();

const chatStore = useChatStore();

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
const error = ref<string | null>(null);
const pickerOpen = ref(false);

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

const overview = computed(() =>
  effectiveVersion.value ? characterOverviewService.build(effectiveVersion.value, props.rules) : null,
);

const stateRows = computed(() =>
  effectiveVersion.value ? combatStateRows(effectiveVersion.value.states, props.rules) : [],
);

const stateOptions = computed(() => statePickerOptions(props.rules));

const proficiencyLevels = computed(() =>
  effectiveVersion.value ? weaponProficiencyLevels(effectiveVersion.value.abilities, props.rules) : new Map(),
);

const senses = computed(() => effectiveVersion.value?.senses ?? []);

const speaker = computed<ChatSpeaker>(() => {
  const current = model.value;
  if (!current) return { kind: 'gm' };

  return current.kind === 'character'
    ? { kind: 'character', characterId: current.entityId, characterName: current.name }
    : { kind: 'npc', npcId: current.entityId, npcName: current.name };
});

async function loadOverlays(): Promise<void> {
  if (!props.open || props.entityKey === null) return;
  error.value = null;
  try {
    overlays.value = await getGameApi().getCombatOverlays(props.gameId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить данные боя';
  }
}

watch(
  () => [props.open, props.entityKey],
  () => void loadOverlays(),
  { immediate: true },
);

function applyOverlay(result: GameCombatOverlay): void {
  overlays.value = overlays.value.map((item) => (item.entityKey === result.entityKey ? result : item));
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
    const result = rollCharacteristic({ name: rollName, value: characteristic.value }, props.rules, props.mechanics);
    await chatStore.sendMessage(
      rollName,
      [{ type: ROLL_ATTACHMENT_TYPE, payload: result }],
      props.chatId,
      speaker.value,
    );
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

async function removeRowStates(row: CombatStateRow): Promise<void> {
  // Удаляем с конца, чтобы индексы не съехали.
  for (const index of [...row.indices].reverse()) {
    await removeState(index);
  }
}

function stateValue(state: CharacterStateValue): string {
  if (state.value !== undefined) return String(state.value);
  if (state.dimensionalValue) return new DimensionalNumber(state.dimensionalValue).toString();

  return '';
}

function stateEntryLabel(row: CombatStateRow, index: number): string {
  const state = effectiveVersion.value?.states[index];
  if (!state) return '';

  return stateValue(state);
}

function rowTotal(row: CombatStateRow): number {
  const states = effectiveVersion.value?.states ?? [];

  return row.indices.reduce((acc, index) => acc + (states[index]?.value ?? 0), 0);
}

function kindIcon(): string {
  return model.value?.kind === 'npc' ? 'mdi-robot-outline' : 'mdi-account';
}

function onClose(): void {
  emit('update:open', false);
}
</script>

<template>
  <SlidePanel v-model="isOpen" width="420px">
    <template #header>
      <div class="d-flex align-center ga-2 w-100 min-width-0">
        <v-icon :icon="kindIcon()" size="18" color="primary" class="flex-shrink-0" />
        <span class="text-subtitle-1 font-weight-medium text-truncate">{{ model?.name ?? 'Карточка' }}</span>
        <v-chip v-if="model && !model.canEdit" size="x-small" variant="tonal" class="flex-shrink-0">просмотр</v-chip>
        <v-spacer />
        <v-btn icon="mdi-close" size="small" variant="text" @click="onClose" />
      </div>
    </template>

    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-3">{{ error }}</v-alert>

    <div v-if="model && effectiveVersion === null" class="text-medium-emphasis text-center pa-8">
      Лист участника не заполнен
    </div>

    <template v-if="model && effectiveVersion !== null">
      <div class="combat-card-panel__body">
        <!-- Характеристики: тайл — попап (модификаторы/производные/чувства), кубик — бросок -->
        <section class="combat-card-section">
          <div class="combat-card-section__title">Характеристики</div>
          <template
            v-for="group in [
              { key: 'primary', label: 'Основные' },
              { key: 'important', label: 'Важные' },
              { key: 'secondary', label: 'Вторичные' },
            ]"
            :key="group.key"
          >
            <template v-if="(overview?.characteristics ?? []).some((c) => c.group === group.key)">
              <div class="combat-card-section__subtitle">{{ group.label }}</div>
              <div class="combat-card-characteristics">
                <CombatCardCharacteristicTile
                  v-for="characteristic in overview?.characteristics.filter((c) => c.group === group.key)"
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
          </template>
        </section>

        <!-- Бой: статы и оружия — тайлы как обычные характеристики -->
        <section v-if="overview?.combat" class="combat-card-section">
          <div class="combat-card-section__title">Бой</div>
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
        </section>

        <!-- Ресурсы: тайлы как характеристики (CD-9) -->
        <section class="combat-card-section">
          <div class="combat-card-section__title">Ресурсы</div>
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
        </section>

        <!-- Состояния: типовые контролы + пикер из ревизии -->
        <section class="combat-card-section">
          <div class="combat-card-section__title">Состояния</div>
          <div v-if="stateRows.length === 0" class="text-medium-emphasis text-body-2">Состояний нет</div>

          <div v-for="row in stateRows" :key="row.ruleId" class="combat-card-state">
            <div class="combat-card-row">
              <span class="combat-card-row__label text-truncate d-flex align-center ga-1">
                <v-icon v-if="row.iconCode" :icon="row.iconCode" size="small" />
                {{ row.name }}
              </span>
              <span class="combat-card-row__value">{{ row.summary ?? 'Есть' }}</span>
              <template v-if="model.canEdit">
                <!-- number/sum: степпер по сумме -->
                <template v-if="row.valueType === 'number' && row.aggregation === 'sum' && row.indices.length > 0">
                  <v-btn
                    icon="mdi-minus"
                    size="x-small"
                    variant="text"
                    class="flex-shrink-0"
                    :disabled="rowTotal(row) <= 0"
                    @click="setStateValue(row.indices[0], Math.max(0, rowTotal(row) - 1))"
                  />
                  <v-btn
                    icon="mdi-plus"
                    size="x-small"
                    variant="text"
                    class="flex-shrink-0"
                    @click="setStateValue(row.indices[0], rowTotal(row) + 1)"
                  />
                </template>
                <!-- flag: тумблер наличия -->
                <template v-else-if="row.valueType === 'flag' && !row.poison">
                  <v-btn
                    v-if="row.indices.length === 0"
                    icon="mdi-plus"
                    size="x-small"
                    variant="text"
                    class="flex-shrink-0"
                    @click="
                      addState({
                        ruleId: row.ruleId,
                        code: row.code,
                        name: row.name,
                        iconCode: row.iconCode,
                        valueType: row.valueType,
                        aggregation: row.aggregation,
                      })
                    "
                  />
                  <v-btn
                    v-else
                    icon="mdi-minus"
                    size="x-small"
                    variant="text"
                    class="flex-shrink-0"
                    @click="removeRowStates(row)"
                  />
                </template>
                <!-- всё остальное (independent/poison/…) — «Убрать» -->
                <template v-else>
                  <v-btn
                    v-if="row.indices.length > 0"
                    icon="mdi-minus"
                    size="x-small"
                    variant="text"
                    class="flex-shrink-0"
                    @click="removeRowStates(row)"
                  />
                </template>
              </template>
            </div>

            <!-- independent/dimensional: записи по отдельности (рана/увечье) -->
            <template v-if="model.canEdit && row.valueType === 'number' && row.aggregation === 'independent'">
              <div class="combat-card-state__entries">
                <div v-for="index in row.indices" :key="index" class="combat-card-state__entry">
                  <v-chip size="x-small" variant="tonal">{{ stateEntryLabel(row, index) }}</v-chip>
                  <v-btn icon="mdi-minus" size="x-small" variant="text" @click="removeState(index)" />
                </div>
                <v-btn
                  icon="mdi-plus"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  :title="`Добавить «${row.name}»`"
                  @click="
                    addState({
                      ruleId: row.ruleId,
                      code: row.code,
                      name: row.name,
                      iconCode: row.iconCode,
                      valueType: row.valueType,
                      aggregation: row.aggregation,
                    })
                  "
                />
              </div>
            </template>
          </div>

          <div v-if="model.canEdit && stateOptions.length > 0" class="mt-2">
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
          </div>
        </section>

        <!-- Защита и атаки (read) -->
        <section v-if="overview?.defense" class="combat-card-section">
          <div class="combat-card-section__title">Защита</div>
          <div v-for="armor in overview.defense.armor" :key="armor.itemRuleId" class="combat-card-row">
            <span class="combat-card-row__label text-truncate">{{ armor.itemName }}</span>
            <span class="combat-card-row__value">
              {{
                armor.lines
                  .map((line) => `${line.kind === 'defense' ? 'Защита' : 'Сопротивление'} ${line.valueLabel}`)
                  .join(' · ')
              }}
            </span>
          </div>
          <div v-if="overview.defense.shield" class="combat-card-row">
            <span class="combat-card-row__label text-truncate"
              >{{ overview.defense.shield.itemName }} — блокирование</span
            >
            <span class="combat-card-row__value">
              Защита {{ overview.defense.shield.defense }} · эффективность {{ overview.defense.shield.efficiency }}
            </span>
          </div>
          <div
            v-if="overview.defense.armor.length === 0 && !overview.defense.shield"
            class="text-medium-emphasis text-body-2"
          >
            Экипированной защиты нет
          </div>
        </section>

        <section v-if="(overview?.attacks ?? []).length > 0" class="combat-card-section">
          <div class="combat-card-section__title">Атаки</div>
          <div
            v-for="attack in overview?.attacks ?? []"
            :key="`${attack.itemRuleId}_${attack.profileType}`"
            class="combat-card-row"
          >
            <span class="combat-card-row__label text-truncate"
              >{{ attack.itemName }} — {{ attack.profileTypeLabel }}</span
            >
            <span class="combat-card-row__value">{{ attack.damageLabel }}</span>
          </div>
        </section>

        <!-- Способности (read) -->
        <section v-if="(overview?.abilities ?? []).length > 0" class="combat-card-section">
          <div class="combat-card-section__title">Способности</div>
          <div v-for="ability in overview?.abilities ?? []" :key="ability.ruleId" class="combat-card-row">
            <span class="combat-card-row__label text-truncate">{{ ability.name }}</span>
            <span class="combat-card-row__value">ур. {{ ability.level }}</span>
          </div>
        </section>

        <!-- Разное (read) -->
        <section v-if="(overview?.misc ?? []).length > 0" class="combat-card-section">
          <div class="combat-card-section__title">Разное</div>
          <div v-for="item in overview?.misc ?? []" :key="item.code" class="combat-card-row">
            <span class="combat-card-row__label text-truncate">{{ item.label }}</span>
            <span class="combat-card-row__value">{{ item.valueLabel }}</span>
          </div>
        </section>

        <!-- Инвентарь (read) -->
        <section v-if="(overview?.inventory ?? []).length > 0" class="combat-card-section">
          <div class="combat-card-section__title">Инвентарь</div>
          <div v-for="item in overview?.inventory ?? []" :key="item.id" class="combat-card-row">
            <span class="combat-card-row__label text-truncate">{{ item.name }}</span>
            <span class="combat-card-row__value">{{ item.quantity }} шт.</span>
          </div>
        </section>
      </div>
    </template>
  </SlidePanel>
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
.combat-card-section__title {
  font-size: 13px;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  text-transform: uppercase;
  letter-spacing: 0.02em;
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
