<script setup lang="ts">
import { computed } from 'vue';
import ExpandableItem from '@/modules/Core/UI/Component/ExpandableItem.vue';
import LightChip from '@/modules/Core/UI/Component/light/LightChip.vue';
import LightButton from '@/modules/Core/UI/Component/light/LightButton.vue';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import { itemWeaponProfilesService } from '@/modules/Roleplay/Character/Service/Instance/itemWeaponProfilesService';
import { itemMasteryService } from '@/modules/Roleplay/Character/Service/Instance/itemMasteryService';
import { splitParagraphs } from '@/modules/Core/UI/Utils/textParagraphs';
import type { WeaponProfileView } from '@/modules/Roleplay/Character/Dto/WeaponProfileView';
import type { ItemMasteryView } from '@/modules/Roleplay/Character/Dto/ItemMasteryView';
import type { InventoryModifierOption } from '@/modules/Roleplay/Character/Dto/Editor/InventoryModifierOption';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { itemModifierService } from '@/modules/Roleplay/Rule/init';
import type { InventoryCatalogItem } from '@/modules/Roleplay/Character/Dto/Editor/InventoryCatalogItem';
import type { InventoryRowMode } from '@/modules/Roleplay/Character/Enum/InventoryRowMode';

const props = defineProps<{
  item: InventoryCatalogItem;
  /** Правила ревизии (для имён/семей в профилях и блоке владения). */
  rules: Rule[];
  /** Значения характеристик по коду — для оценки профилей оружия. */
  characteristicValues: Map<string, DimensionalNumberValue>;
  /** Способности черновика — для уровня владения семьи. */
  abilities: CharacterAbility[];
  /** Количество в инвентаре (для степпера и кнопки экипировки). */
  ownedQty: number;
  /** Количество на базовой линии (ниже — отмена недоступна, R2). */
  baselineQty: number;
  /** Экипирован ли этот экземпляр (owned) или любой экземпляр (shop). */
  equipped: boolean;
  /** Раскрыта ли панель (управляемое состояние — переживает ремаунты виртуализации). */
  open: boolean;
  /** Применимые модификаторы (уже отфильтрованы по признакам предмета). */
  modifiers: InventoryModifierOption[];
  selectedModifierRuleIds: string[];
  displayCost: number;
  /** Коды признаков предмета (для applyStack: цена качества, keyword-ops). */
  keywordCodes: string[];
  /** shop — каталог; owned — экземпляр снаряжения. */
  mode: InventoryRowMode;
  /** Кнопки покупки/отмены. На карточке персонажа — выкл. */
  showPurchase?: boolean;
  /** Кнопка «Экип.» (владелец на карточке / редактор). */
  allowEquip?: boolean;
  /** Прокачка владения и выбор модов. На карточке в этом заходе — выкл. */
  allowAbilityEdit?: boolean;
}>();

const emit = defineEmits<{
  'update:open': [open: boolean];
  buy: [ruleId: string];
  cancel: [ruleId: string, quantity: number];
  'cancel-instance': [];
  'toggle-equipped': [];
  train: [mastery: ItemMasteryView, level: number];
  'open-skills': [familyCode: string, keywordCode: string | null];
  'open-modifiers': [];
}>();

const selectedEffects = computed(() =>
  props.modifiers.filter((modifier) => props.selectedModifierRuleIds.includes(modifier.ruleId)),
);

const effectiveSpec = computed<ItemSpec | undefined>(() => {
  const spec = props.item.spec;
  if (!spec) return undefined;
  const modifiers = props.selectedModifierRuleIds
    .map((id) => props.rules.find((rule) => rule.id === id))
    .filter((rule): rule is Rule => rule !== undefined);

  return itemModifierService.applyStack(spec, modifiers, props.keywordCodes).spec;
});

const { openRule } = useRuleDetailSlider();

const showPurchase = computed(() => props.showPurchase !== false && !props.item.spec?.innate);
const allowEquip = computed(() => props.allowEquip !== false);
const allowAbilityEdit = computed(() => props.allowAbilityEdit !== false);
const canEquip = computed(
  () =>
    allowEquip.value &&
    !props.item.spec?.innate &&
    Boolean(props.item.spec?.weapon || props.item.spec?.armor || props.item.spec?.shield),
);

const canCancel = computed(() => showPurchase.value && props.ownedQty > props.baselineQty);

/** Параметры оружия/щита/доспеха (вес, мин. сила, прочность, блок, защита…) — над профилями. */
const params = computed(() =>
  itemWeaponProfilesService.itemParamsView(effectiveSpec.value, props.characteristicValues, props.rules),
);

/** Имя правила по коду — для человекочитаемых формул профилей. */
function resolveRuleName(code: string): string | null {
  return props.rules.find((rule) => rule.code === code)?.name ?? null;
}

/**
 * Контекст оценки профилей: только характеристики (профили оружия используют actionCharacteristic/
 * fixed/dimensional, abilityLevels им не нужен).
 */
const profileContext = computed<FormulaContext>(() => ({
  characteristicValues: props.characteristicValues,
  abilityLevels: new Map(),
}));

/** Профили атак оружия (значения от текущих характеристик); считаются лениво — только у видимых строк. */
const profiles = computed<WeaponProfileView[]>(() =>
  itemWeaponProfilesService.weaponProfileViews(effectiveSpec.value, profileContext.value, resolveRuleName),
);

/** Блок «Владение оружием» (семья + лестница + уровень); null — у предмета нет семьи. */
const mastery = computed<ItemMasteryView | null>(() =>
  itemMasteryService.itemMasteryView(props.item.spec, props.abilities, props.rules),
);

/** Извлечь ключевое слово оружия из тэгов предмета. */
const weaponKeywordCode = computed<string | null>(() => {
  //Fallback: по familyName вычисляем тэг оружия.
  if (!mastery.value) return null;
  const famName = mastery.value.familyName.toLowerCase();
  if (famName.includes('кинж') || famName.includes('нож')) return 'dagger';
  if (famName.includes('меч') && !famName.includes('длинн')) return 'sword';
  if (famName.includes('сабл')) return 'saber';
  if (famName.includes('посох')) return 'staff';
  if (famName.includes('топор')) return 'simple-axe';
  if (famName.includes('копь')) return 'spear';
  if (famName.includes('молот')) return 'war-hammer';
  if (famName.includes('палиц') || famName.includes('булав')) return 'club';
  if (famName.includes('кнут')) return 'whip';
  if (famName.includes('секир') || famName.includes('алебар')) return 'poleaxe';
  if (famName.includes('меч') && famName.includes('длинн')) return 'longsword';
  if (famName.includes('фламберг')) return 'flamberge';
  if (famName.includes('гибки') && famName.includes('клинок')) return 'flexible-blade';
  if (famName.includes('пата')) return 'pata';
  if (famName.includes('рог')) return 'horns';
  if (famName.includes('уруми')) return 'urumi';
  if (famName.includes('текко')) return 'tekko-kagi';
  if (famName.includes('когот')) return 'claws';
  if (famName.includes('щит')) return 'small-shield';

  return null;
});

/** Разделитель перед описанием: есть инфо-блоки выше (параметры/защита/профили) и нет владения
 *  (у блока владения уже есть нижняя черта). */
const descriptionDivided = computed(() => !mastery.value && (params.value !== null || profiles.value.length > 0));

/** Абзацы описания (каждый перевод строки — заметный абзац); пустое описание → «—». */
const descriptionParagraphs = computed(() => {
  const paragraphs = splitParagraphs(props.item.description);

  return paragraphs.length > 0 ? paragraphs : ['—'];
});

// Управляемое раскрытие строки: геттер от пропа, сеттер — в эмит (переживает ремаунты виртуализации).
const open = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
});
</script>

<template>
  <ExpandableItem v-model="open" class="item-row" :class="{ 'item-row--owned': mode === 'owned' || ownedQty > 0 }">
    <template #title>
      <div class="d-flex align-center ga-2 w-100">
        <LightButton
          v-if="item.ruleId"
          class="item-row__slider-btn"
          title="Открыть правило"
          aria-label="Открыть правило"
          @click.stop="openRule(item.ruleId)"
        >
          <i class="mdi mdi-open-in-new" aria-hidden="true" />
        </LightButton>
        <span class="font-weight-medium text-truncate">{{ item.name }}</span>
        <LightChip>{{ item.subtitle }}</LightChip>
        <LightChip v-if="item.section" variant="outlined">{{ item.section }}</LightChip>
        <LightChip v-if="item.spec?.innate" color="secondary">врождённое</LightChip>
        <LightChip v-if="equipped && mode === 'owned'" color="primary">экипировано</LightChip>

        <div class="item-row__spacer" />

        <LightChip v-for="name in selectedEffects.map((modifier) => modifier.name)" :key="name" variant="outlined">
          {{ name }}
        </LightChip>
        <LightChip v-if="!item.spec?.innate" variant="outlined">{{ displayCost }} гм</LightChip>
        <div class="d-flex align-center ga-1">
          <LightButton
            v-if="showPurchase"
            :disabled="!canCancel"
            title="Отменить покупку"
            aria-label="Отменить покупку"
            @click.stop="mode === 'owned' ? emit('cancel-instance') : emit('cancel', item.ruleId, 1)"
          >
            <i class="mdi mdi-minus" aria-hidden="true" />
          </LightButton>
          <span v-if="showPurchase && mode === 'shop'" class="text-caption text-medium-emphasis">{{ ownedQty }}</span>
          <LightButton
            v-if="showPurchase && mode === 'shop'"
            title="Купить"
            aria-label="Купить"
            @click.stop="emit('buy', item.ruleId)"
          >
            <i class="mdi mdi-plus" aria-hidden="true" />
          </LightButton>
          <LightButton
            v-if="canEquip && mode === 'owned'"
            :active="equipped"
            :title="equipped ? 'Снять экипировку' : 'Экипировать'"
            @click.stop="emit('toggle-equipped')"
          >
            <i class="mdi mdi-shield-outline" aria-hidden="true" /> Экип.
          </LightButton>
        </div>
      </div>
    </template>

    <div class="item-row__body">
      <div v-if="params" class="item-row__params">
        <div v-if="params.weightLabel" class="param-cell">
          <span class="param-cell__label">Вес:</span> {{ params.weightLabel }}
        </div>
        <div v-if="params.durabilityLabel" class="param-cell">
          <span class="param-cell__label">Прочность:</span> {{ params.durabilityLabel }}
        </div>
        <div v-if="params.minStrengthLabel" class="param-cell">
          <span class="param-cell__label">Минимальная сила:</span> {{ params.minStrengthLabel }}
        </div>
        <div v-if="params.characteristicLimitsLabel" class="param-cell">{{ params.characteristicLimitsLabel }}</div>
        <div v-if="params.maxAgilityLabel" class="param-cell">
          <span class="param-cell__label">Макс. ловкость:</span> {{ params.maxAgilityLabel }}
        </div>
        <div v-if="params.strengthPenaltyLabel" class="param-cell">
          <span class="param-cell__label">Штраф к силе:</span> {{ params.strengthPenaltyLabel }}
        </div>
        <div v-if="params.blockDefenseLabel" class="param-cell-group">
          <div class="param-cell">
            <span class="param-cell__label">Защита блокирования:</span> {{ params.blockDefenseLabel }}
          </div>
          <div class="param-cell">
            <span class="param-cell__label">Эффективность блокирования:</span> {{ params.blockEfficiencyLabel }}
          </div>
        </div>
        <div v-for="resistance in params.resistanceLabels" :key="resistance" class="param-cell">
          {{ resistance }}
        </div>
      </div>

      <div v-if="params?.defenseLines.length" class="item-row__defense">
        <div v-for="line in params.defenseLines" :key="`${line.defense}_${line.durability}`" class="defense-cell">
          <span class="font-weight-medium">Защита: {{ line.defense }}</span>
          <span v-if="line.sourceLabel" class="text-medium-emphasis"> от {{ line.sourceLabel }}</span>
          <span class="text-medium-emphasis"> с надёжностью {{ line.durability }}</span>
        </div>
      </div>

      <div v-if="profiles.length" class="item-row__profiles">
        <div class="profile-header">
          <div class="profile-cell profile-cell--type">Тип</div>
          <div class="profile-cell">Урон</div>
          <div class="profile-cell">Пробитие</div>
          <div class="profile-cell">Точность</div>
          <div class="profile-cell">Дальность / Дальнобойность</div>
        </div>
        <div v-for="(profile, index) in profiles" :key="index" class="profile-line">
          <div class="profile-cell profile-cell--type">
            <span class="font-weight-medium">{{ profile.profileTypeLabel }}</span>
          </div>
          <div class="profile-cell">
            <span class="font-weight-medium">{{ profile.damageLabel }}</span>
            <span v-if="profile.damageFormula" class="profile-cell__formula">({{ profile.damageFormula }})</span>
          </div>
          <div class="profile-cell">
            <span class="font-weight-medium">{{ profile.penetrationLabel }}</span>
            <span v-if="profile.penetrationFormula" class="profile-cell__formula">
              ({{ profile.penetrationFormula }})
            </span>
          </div>
          <div class="profile-cell">
            <span class="font-weight-medium">{{ profile.accuracyLabel }}</span>
          </div>
          <div class="profile-cell">
            <span class="font-weight-medium">{{ profile.distanceLabel }}</span>
            <template v-if="profile.falloffLabel">
              <span class="profile-cell__sep">/</span>
              <span class="font-weight-medium">{{ profile.falloffLabel }}</span>
            </template>
          </div>
        </div>
      </div>
      <div v-if="mastery" class="item-row__mastery d-flex align-center ga-2 flex-wrap">
        <span class="mastery-label">Владение оружием — {{ mastery.familyName }}:</span>
        <div v-if="allowAbilityEdit" class="d-flex align-center ga-1 flex-wrap">
          <LightButton
            v-for="(cost, index) in mastery.ladder"
            :key="index"
            :active="index + 1 === mastery.level"
            @click="emit('train', mastery, index + 1 === mastery.level ? 0 : index + 1)"
          >
            {{ cost }} ОР
          </LightButton>
        </div>
        <span class="text-caption text-medium-emphasis">уровень {{ mastery.level }} из {{ mastery.maxLevel }}</span>
        <LightButton
          v-if="allowAbilityEdit && weaponKeywordCode"
          class="item-row__skills"
          @click.stop="emit('open-skills', mastery.familyCode, weaponKeywordCode)"
        >
          Навыки владения
        </LightButton>
      </div>
      <p
        v-for="(paragraph, index) in descriptionParagraphs"
        :key="index"
        class="body-description"
        :class="{ 'body-description--divided': descriptionDivided && index === 0 }"
      >
        {{ paragraph }}
      </p>
      <div v-if="mode === 'owned' && modifiers.length" class="item-row__mods">
        <div class="d-flex align-center ga-2 flex-wrap">
          <span class="text-caption text-medium-emphasis">Модификаторы</span>
          <LightChip v-for="modifier in selectedEffects" :key="modifier.ruleId" variant="outlined">
            {{ modifier.name }}
          </LightChip>
          <LightButton v-if="allowAbilityEdit" class="item-row__mods-btn" @click.stop="emit('open-modifiers')">
            {{ selectedEffects.length > 0 ? 'Изменить' : 'Выбрать' }}
          </LightButton>
        </div>
        <div v-for="modifier in selectedEffects" :key="`${modifier.ruleId}-fx`" class="item-row__mod-effect">
          <strong>{{ modifier.name }}</strong>
          <span v-if="modifier.category" class="text-medium-emphasis"> · {{ modifier.category }}</span>
          <span v-if="modifier.priceLabel" class="text-medium-emphasis"> · {{ modifier.priceLabel }}</span>
          <div v-for="(text, index) in modifier.effects" :key="index">{{ text }}</div>
        </div>
      </div>
      <div v-if="item.featureKeywords.length" class="d-flex align-center ga-2 flex-wrap body-keywords">
        <span class="text-caption text-medium-emphasis">Признаки:</span>
        <LightChip v-for="keyword in item.featureKeywords" :key="keyword.id" variant="outlined">
          {{ keyword.name }}
        </LightChip>
      </div>
    </div>
  </ExpandableItem>
</template>

<style scoped>
.item-row {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

:deep(.expandable-item__trigger) {
  min-height: 48px;
  padding: 0 16px;
}

.item-row--owned {
  background-color: rgb(var(--v-theme-primaryLight));
}

.item-row__body {
  padding: 8px 16px 16px;
}

.item-row__spacer {
  flex: 1 1 auto;
}

/* Профили оружия: один под другим, между профилями — черта. */
.item-row__profiles {
  display: flex;
  flex-direction: column;
  margin-bottom: 4px;
}

/* Параметры оружия/щита над профилями: одиночные ячейки с пробелами между ними,
   две ячейки блокирования — одна группа без внутреннего пробела; ряд центрируется. */
.item-row__params {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  margin-bottom: 4px;
}

.param-cell {
  padding: 4px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  white-space: nowrap;
}

.param-cell__label {
  color: rgba(var(--v-theme-on-surface), 0.72);
}

/* Группа блокирования: общий бордер, внутри — две ячейки через вертикальную черту. */
.param-cell-group {
  display: flex;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  overflow: hidden;
}

.param-cell-group .param-cell {
  border: none;
  border-radius: 0;
}

.param-cell-group .param-cell + .param-cell {
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Слои защиты доспеха: ячейки в одну строку (переносятся при нехватке места), как параметры. */
.item-row__defense {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  margin-bottom: 4px;
}

.defense-cell {
  padding: 4px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  white-space: nowrap;
}

/* Строка профиля и шапка — колонки с вертикальными границами (тип | урон | пробитие | точность | дальность). */
.profile-line,
.profile-header {
  display: grid;
  grid-template-columns: 56px repeat(4, minmax(0, 1fr));
  font-size: 12px;
}

.profile-header {
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.profile-header .profile-cell {
  padding-top: 2px;
  padding-bottom: 2px;
}

.profile-line + .profile-line {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.profile-cell {
  min-width: 0;
  padding: 6px 10px;
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.profile-cell:first-child {
  border-left: none;
  padding-left: 0;
}

.profile-cell__formula {
  color: rgba(var(--v-theme-on-surface), 0.72);
  white-space: nowrap;
  margin-left: 4px;
}

.profile-cell__sep {
  margin: 0 4px;
  color: rgba(var(--v-theme-on-surface), 0.5);
}

/* Описание после профилей отделяется чертой. */
.body-description--divided {
  margin-top: 0;
  padding-top: 10px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Блок «Владение оружием» между профилями и описанием: черта сверху и снизу. */
.item-row__mastery {
  margin-top: 12px;
  padding: 10px 0;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.mastery-label {
  font-weight: 500;
}

.item-row__skills {
  margin-left: auto;
}

.item-row__mods {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.item-row__mods-btn {
  margin-left: auto;
}

.item-row__mod-effect {
  margin-top: 10px;
  font-size: 13px;
  line-height: 1.4;
  white-space: pre-line;
}

.body-keywords {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Абзацы описания: перевод строки → заметное расстояние, строки не слипаются. */
.body-description + .body-description {
  margin-top: 8px;
}

.body-description {
  white-space: pre-line;
}

.item-row__slider-btn {
  min-width: 24px;
  padding: 0 4px;
}
</style>
