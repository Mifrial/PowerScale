<script setup lang="ts">
import { computed, ref } from 'vue';
import ExpandableItem from '@/modules/Core/UI/Component/ExpandableItem.vue';
import LightChip from '@/modules/Core/UI/Component/light/LightChip.vue';
import LightButton from '@/modules/Core/UI/Component/light/LightButton.vue';
import { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/init';
import { parameterLimitName } from '@/modules/Roleplay/Rule/Utils/parameterLimitName';
import { resourceShortName } from '@/modules/Roleplay/Rule/Utils/resourceShortName';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityParameter } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityParameter';
import type { EditorAbilityZone } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityZone';
import type { EditorAbilityInstance } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityInstance';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';

const props = defineProps<{
  ability: EditorAbility;
  keywords: Keyword[];
  /** Общий каталог правил (для имён ресурсов в шагах процесса). */
  rules?: Rule[];
  /** Способность, недоступная для взятия (группа исчерпала лимит выбора). */
  lockedRuleIds?: Set<string>;
  /** Зона цен способности (os/ol/or); по умолчанию «Основа». */
  zoneCode?: string;
  /** Подпись зоны в ценах («ОС»/«ОЛ»/«ОР»). */
  zoneLabel?: string;
  /** Доплата механики «Общие черты» за эту способность (ОС); 0/undefined — доплаты нет. */
  surchargeAmount?: number;
  /** Раскрыта ли панель (управляемое состояние — переживает ремаунты виртуализации). */
  open?: boolean;
}>();

const emit = defineEmits<{
  'set-level': [ruleId: string, level: number];
  'set-parameter': [ruleId: string, code: string, value: number | { base: number; size: number }];
  'add-instance': [ruleId: string, domain: string, domainCode: string | null];
  'set-instance-level': [ruleId: string, domain: string, level: number];
  'set-instance-domain': [ruleId: string, oldDomain: string, newDomain: string, domainCode: string | null];
  'remove-instance': [ruleId: string, domain: string];
  'set-ability-domain': [ruleId: string, domain: string, domainCode: string | null];
  'update:open': [open: boolean];
}>();

const { openRule } = useRuleDetailSlider();

// Управляемое раскрытие строки: геттер от пропа, сеттер — в эмит (переживает ремаунты виртуализации).
const open = computed({
  get: () => props.open ?? false,
  set: (value: boolean) => emit('update:open', value),
});

function zoneOf(ability: EditorAbility): EditorAbilityZone | null {
  return ability.zones.find((zone) => zone.zoneCode === (props.zoneCode ?? 'os')) ?? null;
}

function zoneLabelOf(): string {
  return props.zoneLabel ?? 'ОС';
}

function spentOf(ability: EditorAbility): number {
  const zone = zoneOf(ability);
  if (!zone) return 0;

  if (ability.multiple) {
    return ability.instances.reduce(
      (sum, instance) =>
        sum + zone.levelCosts.slice(0, instance.level).reduce((instanceSum, cost) => instanceSum + cost, 0),
      0,
    );
  }

  // Уровни дара (D100) бесплатны: оплачиваются только уровни сверх giftedLevel.
  return zone.levelCosts.slice(ability.giftedLevel, ability.level).reduce((sum, cost) => sum + cost, 0);
}

function costKind(ability: EditorAbility): EditorAbilityZone['kind'] | null {
  return zoneOf(ability)?.kind ?? null;
}

function maxLevel(ability: EditorAbility): number {
  return zoneOf(ability)?.maxLevel ?? 0;
}

function nextLevelCost(ability: EditorAbility): number | null {
  const zone = zoneOf(ability);
  if (!zone) return null;
  const next = zone.levelCosts[ability.level];

  return next ?? null;
}

// Стоимость текущего уровня (освобождается при снятии уровня вниз).
function prevLevelCost(ability: EditorAbility): number | null {
  const zone = zoneOf(ability);
  if (!zone || ability.level <= 0) return null;

  return zone.levelCosts[ability.level - 1] ?? null;
}

function canRaise(ability: EditorAbility): boolean {
  const zone = zoneOf(ability);
  if (!zone) return false;
  if (props.lockedRuleIds?.has(ability.ruleId)) return false;
  const next = ability.level + 1;
  if (next > zone.maxLevel) return false;

  // Взятие уровня N подразумевает владение уровнями 1..N: продвижение возможно,
  // только если выполнены требования всех уровней вплоть до следующего.
  return ability.levels.filter((entry) => entry.level <= next).every((entry) => entry.met);
}

// Для array-чипов: доступен любой уровень не выше текущего (можно снять) либо уровень, для которого
// выполнены требования всех уровней вплоть до него (взятие уровня N подразумевает владение уровнями 1..N).
function chipEnabled(ability: EditorAbility, level: number): boolean {
  if (level <= displayLevel(ability)) return true;
  if (props.lockedRuleIds?.has(ability.ruleId)) return false;

  return ability.levels.filter((entry) => entry.level <= level).every((entry) => entry.met);
}

// Для array: клик по текущему уровню снимает способность до 0, по любому другому — ставит его.
function setArrayLevel(ability: EditorAbility, level: number): void {
  emit('set-level', ability.ruleId, level === displayLevel(ability) ? 0 : level);
}

function keywordName(ability: EditorAbility, keywordId: number): string {
  return props.keywords.find((keyword) => keyword.id === keywordId)?.name ?? `#${keywordId}`;
}

function typeLabel(ability: EditorAbility): string | null {
  return ability.type === null ? null : (ABILITY_TYPE_LABELS[ability.type] ?? ability.type);
}

// Имя ресурса по коду (action-points → «Очки действий»), как в AbilityCard.
function resourceName(code: string): string {
  const entry = props.rules?.find((rule) => rule.code === code && rule.type === 'resource');

  return entry?.name ?? code;
}

function stepCostLabel(cost: ProcessStep['costs'][number]): string {
  const amount = typeof cost.amount === 'number' ? String(cost.amount) : new DimensionalNumber(cost.amount).toString();
  const short = resourceShortName(cost.resource_code);

  return short ? `${amount} ${short}` : `${resourceName(cost.resource_code)}: ${amount}`;
}

const DOMAIN_LABELS: Record<string, string> = {
  language: 'Язык',
  culture: 'Культура',
  region: 'Регион',
  subject: 'Субъект',
  species: 'Вид',
  instrument: 'Инструмент',
  'communication-check': 'Тип проверки',
};

/** Подпись поля домена множественного навыка (без значения): «Язык», «Вид» и т.п. */
function domainBaseLabel(ability: EditorAbility): string {
  return ability.domainRef ? (DOMAIN_LABELS[ability.domainRef] ?? ability.domainRef) : 'Домен';
}

/** Код правила словаря для значения домена; null — свободный текст. */
function domainCodeFor(value: string): string | null {
  return props.ability.domainOptions.find((option) => option.name === value)?.code ?? null;
}

// --- Экземпляры множественного навыка ---

/** Значение нового домена в форме добавления (не сохраняется до клика «+»). */
const pendingDomain = ref('');

/** Имена из словаря домена (опции VCombobox: выбор из справочника или свой текст). */
const domainNames = computed(() => props.ability.domainOptions.map((option) => option.name));

/** Стоимость первого уровня экземпляра (кнопка «+ N ОР» формы добавления). */
function nextAddCost(ability: EditorAbility): number {
  return zoneOf(ability)?.levelCosts[0] ?? 0;
}

/** Доступно добавление экземпляра: непустое значение, не дубль, не заблокировано, уровень 1 доступен.
 *  Для экземплярных улучшений домен ограничен экземплярами родителя (свободный текст запрещён). */
function canAddInstance(): boolean {
  const pending = (pendingDomain.value ?? '').trim();
  if (!pending) return false;
  if (props.ability.instances.some((instance) => instance.domain === pending)) return false;
  if (props.lockedRuleIds?.has(props.ability.ruleId)) return false;
  if (props.ability.derived) return false;
  if (props.ability.multiple && props.ability.parentCode !== null) {
    if (!props.ability.domainOptions.some((option) => option.name === pending)) return false;
  }

  return props.ability.levels[0]?.met ?? true;
}

function addInstance(): void {
  const value = (pendingDomain.value ?? '').trim();
  if (!value) return;
  emit('add-instance', props.ability.ruleId, value, domainCodeFor(value));
  pendingDomain.value = '';
}

/** Стоимость следующего уровня конкретного экземпляра. */
function instanceNextCost(ability: EditorAbility, instance: EditorAbilityInstance): number {
  return zoneOf(ability)?.levelCosts[instance.level] ?? 0;
}

/** Можно ли повысить уровень экземпляра: не потолок, не заблокировано, требования уровня выполнены
 *  (пер-экземплярные levels — has_ability домен-скоупировано, «Письменность того же языка»). */
function canRaiseInstance(ability: EditorAbility, instance: EditorAbilityInstance): boolean {
  if (props.lockedRuleIds?.has(ability.ruleId)) return false;
  const next = instance.level + 1;
  if (next > maxLevel(ability)) return false;

  return instance.levels.filter((entry) => entry.level <= next).every((entry) => entry.met);
}

/** Причина невыполненных требований следующего уровня экземпляра (null — уровень достижим/потолок). */
function instanceBlockedReason(instance: EditorAbilityInstance): string | null {
  if (instance.level >= maxLevel(props.ability)) return null;

  return instance.levels[instance.level]?.reason ?? null;
}

/** Можно ли понизить уровень экземпляра (ниже 1 — только удаление через «×»). */
function canLowerInstance(instance: EditorAbilityInstance): boolean {
  return instance.level > 1;
}

function setInstanceLevel(instance: EditorAbilityInstance, level: number): void {
  emit('set-instance-level', props.ability.ruleId, instance.domain, level);
}

/** VCombobox при очистке отдаёт null — приводим к пустой строке (сервис отклоняет пустые). */
function onInstanceDomainEdit(instance: EditorAbilityInstance, value: string | null): void {
  const next = value ?? '';
  emit('set-instance-domain', props.ability.ruleId, instance.domain, next, domainCodeFor(next));
}

function removeInstance(instance: EditorAbilityInstance): void {
  emit('remove-instance', props.ability.ruleId, instance.domain);
}

// --- Домен одиночной способности (domain_ref без multiple) ---

/** VCombobox одиночного домена: очистка отдаёт null — приводим к пустой строке (снимает домен). */
function onDomainEdit(value: string | null): void {
  const next = value ?? '';
  emit('set-ability-domain', props.ability.ruleId, next, domainCodeFor(next));
}

// Автоматическая способность (даётся расой/видом бесплатно) считается взятой на уровне 1,
// хотя в черновике не хранится: не давать «0», который трактуется как «не взята».
function displayLevel(ability: EditorAbility): number {
  return ability.automatic ? 1 : ability.level;
}

// Выбранная способность (уровень > 0 или бесплатная) подсвечивается бледно-голубым.
function isChosen(ability: EditorAbility): boolean {
  return ability.automatic || ability.level > 0;
}

// --- Параметрические способности (kind 'parameter') ---

function paramOf(ability: EditorAbility): EditorAbilityParameter | null {
  return ability.parameters[0] ?? null;
}

function paramValue(param: EditorAbilityParameter): number {
  return param.value.base;
}

function paramMax(param: EditorAbilityParameter): number {
  return param.max.base;
}

function paramMin(param: EditorAbilityParameter): number {
  return Math.max(param.min.base, 1);
}

// Нижняя граница степпера: авто-значение расы (докупка сверх него) либо минимум параметра.
// Для параметров с отрицательным диапазоном (Врождённая Сила X) минимум — отрицательный.
function paramFloor(ability: EditorAbility, param: EditorAbilityParameter): number {
  if (ability.automatic) return param.freeValue;

  return param.min.base;
}

function canStepUp(ability: EditorAbility, param: EditorAbilityParameter): boolean {
  if (param.steps.length) {
    const index = param.steps.findIndex((step) => sameStep(step.value, param.value));

    return index >= 0 ? index < param.steps.length - 1 : paramValue(param) < paramMax(param);
  }
  if (param.freeValue > 0 && ability.automatic && paramValue(param) >= param.freeValue) {
    return paramValue(param) < paramMax(param);
  }
  const v = paramValue(param);

  return v <= 0 ? paramMin(param) <= paramMax(param) : v < paramMax(param);
}

function canStepDown(ability: EditorAbility, param: EditorAbilityParameter): boolean {
  if (param.steps.length) {
    const index = param.steps.findIndex((step) => sameStep(step.value, param.value));

    return index > 0;
  }

  return paramValue(param) > paramFloor(ability, param);
}

function sameStep(a: { base: number; size?: number }, b: { base: number; size?: number }): boolean {
  return a.base === b.base && (a.size ?? 0) === (b.size ?? 0);
}

function stepUp(ability: EditorAbility, param: EditorAbilityParameter): void {
  const v = paramValue(param);
  if (param.steps.length) {
    const index = param.steps.findIndex((step) => sameStep(step.value, param.value));
    const next = param.steps[index + 1];
    if (next) emit('set-parameter', ability.ruleId, param.code, next.value);

    return;
  }
  // Отрицательное X: шаг вверх идёт к 0 и выше (не прыгает на первый положительный).
  const next = v < 0 ? Math.min(v + 1, paramMax(param)) : v <= 0 ? paramMin(param) : Math.min(v + 1, paramMax(param));
  emit('set-parameter', ability.ruleId, param.code, next);
}

function stepDown(ability: EditorAbility, param: EditorAbilityParameter): void {
  const v = paramValue(param);
  if (param.steps.length) {
    const index = param.steps.findIndex((step) => sameStep(step.value, param.value));
    const prev = param.steps[index - 1];
    if (prev) emit('set-parameter', ability.ruleId, param.code, prev.value);

    return;
  }
  const floor = paramFloor(ability, param);
  const next = v <= floor ? 0 : v - 1;
  emit('set-parameter', ability.ruleId, param.code, next);
}

// Цена шага параметра: таблица цен по значению (parameter_table) или цена за единицу.
function stepPrice(param: EditorAbilityParameter, from: number, to: number): number {
  if (param.costs) {
    const fromCost = param.steps.find((step) => step.value.base === from)?.cost;
    const toCost = param.steps.find((step) => step.value.base === to)?.cost;

    return (toCost ?? param.costs[String(to)] ?? 0) - (fromCost ?? param.costs[String(from)] ?? 0);
  }

  return param.perUnit;
}

function stepDownTitle(ability: EditorAbility, param: EditorAbilityParameter): string {
  const v = paramValue(param);
  if (param.steps.length) {
    const index = param.steps.findIndex((step) => sameStep(step.value, param.value));

    return index > 0 ? 'Уменьшить X' : '';
  }
  const floor = paramFloor(ability, param);
  if (v <= floor) return '';

  if (v > 0 && v <= paramMin(param)) return 'Снять способность';

  return v > 0 ? 'Уменьшить X' : 'Увеличить X';
}

function stepUpTitle(ability: EditorAbility, param: EditorAbilityParameter): string {
  const v = paramValue(param);
  if (param.steps.length) {
    const index = param.steps.findIndex((step) => sameStep(step.value, param.value));
    const next = param.steps[index + 1];
    if (!next) return '';
    const paid = next.cost - (param.steps[index]?.cost ?? 0);

    return `Уровень X=${new DimensionalNumber(next.value).toString()}: ${paid} ОС`;
  }
  if (v > 0) {
    const paid = stepPrice(param, v, v + 1);
    if (param.freeValue > 0 && ability.automatic && v >= param.freeValue) {
      return `Докупка до X+1: ${paid} ОС`;
    }

    return `Уровень X+1: ${paid} ОС`;
  }

  if (v < 0) return `Увеличить X: ${stepPrice(param, v, v + 1)} ОС`;

  if (param.costs) return `Взять: ${param.costs[String(paramMin(param))] ?? 0} ОС`;

  return `Взять: ${paramMin(param) * param.perUnit} ОС`;
}

function levelChipLabel(ability: EditorAbility): string {
  if (ability.multiple) return `${ability.instances.length} экземпляров`;
  const param = paramOf(ability);
  if (param) {
    if (param.steps.length) return `X = ${stepLabel(param)}`;

    return `X = ${paramValue(param)}`;
  }
  if (ability.gifted) return `уровень ${displayLevel(ability)}`;

  return `${displayLevel(ability)} из ${maxLevel(ability)}`;
}

/** Имя с потолком расы: «Сопротивление магии X» → «Сопротивление магии до 2». */
function displayName(ability: EditorAbility): string {
  const param = paramOf(ability);
  if (param?.cappedByRace) return parameterLimitName(ability.name, { [param.code]: param.max });

  return ability.name;
}

/** Подпись параметра: «авто N · до M» для расовой автоматической, «X = N · до M» для покупной. */
function paramLabel(ability: EditorAbility, param: EditorAbilityParameter): string {
  const value = paramValue(param);
  const display = param.steps.length ? stepLabel(param) : String(value);
  const base = value !== 0 ? `X = ${display}` : 'X = —';
  const suffix = param.cappedByRace ? ` · до ${paramMax(param)}` : '';
  if (param.freeValue > 0) return `авто ${param.freeValue}${suffix}`;

  return `${base}${suffix}`;
}

/** Строковое представление текущего шага табличной цены (размерное значение: 3↓, 5, 3↑). */
function stepLabel(param: EditorAbilityParameter): string {
  const current = param.steps.find((step) => sameStep(step.value, param.value));
  if (current) return new DimensionalNumber(current.value).toString();

  return String(paramValue(param));
}
</script>

<template>
  <ExpandableItem v-model="open" class="ability-row" :class="{ 'chosen-ability': isChosen(ability) }">
    <template #title>
      <div class="d-flex align-center ga-2 w-100">
        <LightButton
          class="ability-row__slider-btn"
          title="Открыть правило"
          aria-label="Открыть правило"
          @click.stop="openRule(ability.ruleId)"
        >
          <i class="mdi mdi-open-in-new" aria-hidden="true" />
        </LightButton>
        <span class="font-weight-medium">{{ displayName(ability) }}</span>
        <LightChip v-if="typeLabel(ability)">{{ typeLabel(ability) }}</LightChip>
        <LightChip v-if="ability.racial" color="primary">расовая</LightChip>
        <LightChip v-if="ability.automatic" color="secondary">авто</LightChip>
        <LightChip v-if="ability.gifted" color="secondary">дар</LightChip>
        <LightChip v-if="ability.multiple" variant="outlined">множественный</LightChip>
        <LightChip variant="outlined">{{ spentOf(ability) }} {{ zoneLabelOf() }}</LightChip>

        <div class="ability-row__spacer" />
        <LightChip v-if="zoneOf(ability)">{{ levelChipLabel(ability) }}</LightChip>
        <LightChip
          v-if="props.surchargeAmount && props.surchargeAmount > 0"
          color="warning"
          title="Доплата за общую черту (3-я и последующие)"
        >
          +{{ props.surchargeAmount }} {{ zoneLabelOf() }}
        </LightChip>
        <div
          v-if="!ability.derived && (costKind(ability) === 'parameter' || costKind(ability) === 'parameter_table')"
          class="d-flex align-center ga-1"
        >
          <template v-if="paramOf(ability)">
            <LightButton
              :disabled="!canStepDown(ability, paramOf(ability)!)"
              :title="stepDownTitle(ability, paramOf(ability)!)"
              @click.stop="stepDown(ability, paramOf(ability)!)"
            >
              <i class="mdi mdi-minus" aria-hidden="true" />
            </LightButton>
            <span class="text-caption text-medium-emphasis">
              {{ paramLabel(ability, paramOf(ability)!) }}
            </span>
            <LightButton
              :disabled="!canStepUp(ability, paramOf(ability)!)"
              :title="stepUpTitle(ability, paramOf(ability)!)"
              @click.stop="stepUp(ability, paramOf(ability)!)"
            >
              <i class="mdi mdi-plus" aria-hidden="true" />
            </LightButton>
          </template>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </div>
        <div
          v-else-if="!ability.derived && !ability.multiple && costKind(ability) === 'array'"
          class="d-flex align-center ga-1"
        >
          <LightButton
            v-for="(cost, index) in zoneOf(ability)!.levelCosts"
            :key="index"
            :active="index + 1 === displayLevel(ability)"
            :disabled="!chipEnabled(ability, index + 1)"
            @click.stop="setArrayLevel(ability, index + 1)"
          >
            {{ cost }} {{ zoneLabelOf() }}
          </LightButton>
        </div>
        <div
          v-else-if="!ability.derived && !ability.multiple && costKind(ability) === 'progression'"
          class="d-flex align-center ga-1"
        >
          <LightButton
            :disabled="ability.automatic || ability.level === 0"
            :title="ability.automatic ? 'Автоматическая способность' : 'Снять уровень'"
            @click.stop="emit('set-level', ability.ruleId, ability.level - 1)"
          >
            {{ prevLevelCost(ability) ?? 0 }} {{ zoneLabelOf() }} · <i class="mdi mdi-minus" aria-hidden="true" />
          </LightButton>
          <LightButton
            :disabled="ability.automatic || !canRaise(ability)"
            :title="
              nextLevelCost(ability) !== null
                ? `Уровень ${ability.level + 1}: ${nextLevelCost(ability)} ${zoneLabelOf()}`
                : undefined
            "
            @click.stop="emit('set-level', ability.ruleId, ability.level + 1)"
          >
            <i class="mdi mdi-plus" aria-hidden="true" /> {{ nextLevelCost(ability) ?? 0 }} {{ zoneLabelOf() }}
          </LightButton>
        </div>
      </div>
    </template>

    <div class="ability-row__body">
      <div
        v-if="!ability.multiple && ability.levels.some((level) => !level.met)"
        class="text-caption text-medium-emphasis mb-1"
      >
        Требования: {{ ability.levels.find((level) => !level.met)?.reason }}
      </div>
      <p class="text-body-2 body-description">{{ ability.description || '—' }}</p>
      <div v-if="!ability.multiple && ability.domainRef && displayLevel(ability) > 0" class="ability-row__domain">
        <v-combobox
          :model-value="ability.domain ?? ''"
          :items="domainNames"
          :label="domainBaseLabel(ability)"
          class="ability-instance__field"
          density="compact"
          hide-details
          hide-no-data
          clearable
          placeholder="Выберите тип проверки общения"
          @update:model-value="onDomainEdit"
        />
      </div>
      <div v-if="ability.type === 'process' && ability.processSteps.length" class="body-steps">
        <div class="text-subtitle-2 mb-1">Шаги</div>
        <ol>
          <li v-for="step in ability.processSteps" :key="step.code">
            <div class="d-flex align-center ga-1">
              <span class="font-weight-medium">{{ step.name }}</span>
              <span v-if="step.costs.length" class="text-caption text-medium-emphasis">
                · {{ step.costs.map((cost) => stepCostLabel(cost)).join(', ') }}
              </span>
            </div>
            <p class="text-body-2 body-step-description">{{ step.description }}</p>
          </li>
        </ol>
      </div>
      <div v-if="ability.multiple && open" class="ability-row__instances">
        <div v-for="(instance, index) in ability.instances" :key="index" class="ability-instance">
          <div class="ability-instance__controls">
            <v-combobox
              :model-value="instance.domain"
              :items="domainNames"
              :label="`${domainBaseLabel(ability)} ${index + 1}`"
              class="ability-instance__field"
              density="compact"
              hide-details
              hide-no-data
              clearable
              placeholder="Значение по справочнику или свой текст"
              @update:model-value="onInstanceDomainEdit(instance, $event)"
            />
            <LightButton
              :disabled="!canLowerInstance(instance)"
              title="Понизить уровень"
              @click.stop="setInstanceLevel(instance, instance.level - 1)"
            >
              <i class="mdi mdi-minus" aria-hidden="true" />
            </LightButton>
            <LightChip>{{ instance.level }} из {{ maxLevel(ability) }}</LightChip>
            <LightButton
              :disabled="!canRaiseInstance(ability, instance)"
              :title="`Уровень ${instance.level + 1}: ${instanceNextCost(ability, instance)} ${zoneLabelOf()}`"
              @click.stop="setInstanceLevel(instance, instance.level + 1)"
            >
              <i class="mdi mdi-plus" aria-hidden="true" /> {{ instanceNextCost(ability, instance) }}
              {{ zoneLabelOf() }}
            </LightButton>
            <LightButton title="Удалить экземпляр" @click.stop="removeInstance(instance)">
              <i class="mdi mdi-close" aria-hidden="true" />
            </LightButton>
          </div>
          <div v-if="instanceBlockedReason(instance)" class="text-caption text-medium-emphasis ability-instance__note">
            Требования: {{ instanceBlockedReason(instance) }}
          </div>
        </div>

        <div class="ability-instance ability-instance--add">
          <v-combobox
            v-model="pendingDomain"
            :items="domainNames"
            :label="domainBaseLabel(ability)"
            class="ability-instance__field"
            density="compact"
            hide-details
            hide-no-data
            clearable
            placeholder="Новый домен: из справочника или свой текст"
          />
          <LightButton :disabled="!canAddInstance()" title="Добавить экземпляр" @click.stop="addInstance">
            <i class="mdi mdi-plus" aria-hidden="true" /> {{ nextAddCost(ability) }} {{ zoneLabelOf() }}
          </LightButton>
        </div>
      </div>
      <div v-if="ability.keywordIds.length" class="d-flex align-center ga-2 flex-wrap body-keywords">
        <span class="text-caption text-medium-emphasis">Признаки:</span>
        <LightChip v-for="keywordId in ability.keywordIds" :key="keywordId" variant="outlined">
          {{ keywordName(ability, keywordId) }}
        </LightChip>
      </div>
      <!-- Вложенные способности-«Улучшения» (дерево внутри тела родителя). -->
      <slot name="nested" />
    </div>
  </ExpandableItem>
</template>

<style scoped>
/* Лёгкий бордер по всем сторонам каждого итема списка. */
.ability-row {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Шапка строки: минимальная высота, отступы как у прежней панели. */
:deep(.expandable-item__trigger) {
  min-height: 48px;
  padding: 0 16px;
}

/* Выбранная способность — еле заметная бледно-голубая заливка из темы (primaryLight). */
.chosen-ability {
  background-color: rgb(var(--v-theme-primaryLight));
}

/* Тело: отступы под контент. */
.ability-row__body {
  padding: 8px 16px 16px;
}

/* Спейсер, выталкивающий кнопки цен вправо. */
.ability-row__spacer {
  flex: 1 1 auto;
}

/* Признаки — внизу тела, отделены лёгким верхним бордером. */
.body-keywords {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Компактная кнопка-иконка слева от названия (без боковых полей). */
.ability-row__slider-btn {
  min-width: 24px;
  padding: 0 4px;
}

/* Шаги процесса — отдельный блок между описанием и признаками. */
.body-steps {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.body-steps ol {
  margin: 0;
  padding-left: 20px;
}

.body-steps p {
  margin: 0 0 8px;
}

/* Переносы строк внутри описаний (текс моков содержит \n для абзацев). */
.body-description,
.body-step-description {
  white-space: pre-line;
}

/* Блок экземпляров множественного навыка: отделён от описания лёгким верхним бордером. */
.ability-row__instances {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* Домен одиночной способности (domain_ref без multiple): поле выбора между описанием и признаками. */
.ability-row__domain {
  margin-top: 12px;
  max-width: 420px;
}

/* Экземпляр: колонка из строки управления и заметки о требованиях. */
.ability-instance {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

/* Строка управления экземпляра: домен (поле) + уровень + кнопки на одной линии. */
.ability-instance__controls {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

/* Кнопки и чип уровня экземпляра — в высоту компактного комбобокса домена. */
.ability-instance__controls :deep(.light-btn),
.ability-instance__controls :deep(.light-chip) {
  height: 40px;
}

/* Поле домена (VCombobox) — занимает свободное место, прижимает кнопки вправо. */
.ability-instance__field {
  flex: 1 1 auto;
  min-width: 0;
}

/* Заметка о невыполненных требованиях следующего уровня экземпляра. */
.ability-instance__note {
  padding-left: 2px;
}

/* Форма добавления нового экземпляра — строка «поле + кнопка» в пунктирной рамке. */
.ability-instance--add {
  display: flex;
  flex-direction: row;
  align-items: flex-end;
  gap: 8px;
  padding: 8px;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
}

.ability-instance--add :deep(.light-btn) {
  height: 40px;
}
</style>
