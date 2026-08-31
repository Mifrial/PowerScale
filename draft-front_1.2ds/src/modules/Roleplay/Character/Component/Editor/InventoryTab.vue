<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import VirtualList from '@/modules/Core/UI/Component/VirtualList.vue';
import type { InventoryCatalogItem } from '@/modules/Roleplay/Character/Dto/Editor/InventoryCatalogItem';
import type { InventoryItemType } from '@/modules/Roleplay/Character/Enum/InventoryItemType';
import InventoryItemRow from '@/modules/Roleplay/Character/Component/Editor/InventoryItemRow.vue';
import ItemModifierPickerDialog from '@/modules/Roleplay/Character/Component/Editor/ItemModifierPickerDialog.vue';
import WeaponSkillsSlider from '@/modules/Roleplay/Character/Component/Editor/WeaponSkillsSlider.vue';
import { ITEM_LABELS } from '@/modules/Roleplay/Character/Constant/ITEM_LABELS';
import type { ItemMasteryView } from '@/modules/Roleplay/Character/Dto/ItemMasteryView';
import type { InventoryModifierOption } from '@/modules/Roleplay/Character/Dto/Editor/InventoryModifierOption';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { itemModifierService } from '@/modules/Roleplay/Rule/init';

const props = withDefaults(
  defineProps<{
    build: CharacterBuild;
    model: CharacterEditorModel;
    draftKey: string | null;
    rules: Rule[];
    keywords: Keyword[];
    /** editor — каталог + закупка; sheet — только экземпляры, экип. */
    variant?: 'editor' | 'sheet';
    canEdit?: boolean;
    /** Copy-on-write черновика перед первой мутацией на карточке. */
    ensureDraft?: () => void;
    /** Боевая карточка: экип через оверлей, без черновика редактора. */
    onToggleEquipped?: (itemId: number) => void;
    listHeight?: string;
  }>(),
  { variant: 'editor', canEdit: true, listHeight: 'calc(100vh - 300px)' },
);

const draftStore = useCharacterDraftStore();

const showWeaponSkills = ref(false);
const weaponSkillsFamilyCode = ref<string | null>(null);
const weaponSkillsKeywordCode = ref<string | null>(null);
const modifierPickerItemId = ref<number | null>(null);

const keywordCodeById = computed(() => new Map(props.keywords.map((keyword) => [keyword.id, keyword.code])));
const keywordNameById = computed(() => new Map(props.keywords.map((keyword) => [keyword.id, keyword.name])));

const SECTIONS = computed(() =>
  props.keywords.filter((keyword) => keyword.code.startsWith('item-section-')).map((keyword) => keyword.name),
);

function sectionOf(rule: Rule): string | null {
  for (const keywordId of rule.keywordIds ?? []) {
    const code = keywordCodeById.value.get(keywordId);
    if (code?.startsWith('item-section-')) return keywordNameById.value.get(keywordId) ?? null;
  }

  return null;
}

function categoryLabelOf(spec: ItemSpec | undefined): string {
  if (spec?.weapon) return ITEM_LABELS.subtype.weapon;
  if (spec?.armor) return ITEM_LABELS.subtype.armor;
  if (spec?.shield) return ITEM_LABELS.subtype.shield;

  return ITEM_LABELS.category[spec?.category ?? 'other'];
}

/** Тип предмета для быстрого фильтра «один из» (R: по спекам и признаку-зелью). */
function typeOf(rule: Rule, spec: ItemSpec | undefined): InventoryItemType {
  if (spec?.weapon) return 'weapon';
  if (spec?.shield) return 'shield';
  if (spec?.armor) return 'armor';
  const potion = (rule.keywordIds ?? []).some((keywordId) => keywordCodeById.value.get(keywordId) === 'potion');

  return potion ? 'potion' : 'other';
}

/** Признаки в теле панели: все keywords кроме раздела каталога (раздел — в шапке чипом). */
function featureKeywordsOf(rule: Rule): { id: number; name: string }[] {
  return (rule.keywordIds ?? [])
    .filter((keywordId) => !keywordCodeById.value.get(keywordId)?.startsWith('item-section-'))
    .map((keywordId) => ({ id: keywordId, name: keywordNameById.value.get(keywordId) ?? `#${keywordId}` }));
}

/** Предметы каталога закупки: тип item с ценой и без innate (R5). */
const catalog = computed<(InventoryCatalogItem & Record<string, unknown>)[]>(() =>
  props.rules
    .filter((rule) => rule.type === 'item')
    .map((rule) => {
      const spec = rule.spec as ItemSpec | undefined;

      return { rule, spec, cost: spec?.cost_gm ?? null, innate: spec?.innate ?? false };
    })
    .filter((entry): entry is { rule: Rule; spec: ItemSpec | undefined; cost: number; innate: boolean } => {
      return entry.cost !== null && !entry.innate;
    })
    .map(({ rule, spec, cost }) => ({
      ruleCode: rule.code,
      name: rule.name,
      description: rule.description,
      cost,
      section: sectionOf(rule),
      subtitle: categoryLabelOf(spec),
      type: typeOf(rule, spec),
      spec,
      featureKeywords: featureKeywordsOf(rule),
      keywordIds: rule.keywordIds ?? [],
    })),
);

function itemViewFromRule(rule: Rule, spec: ItemSpec | undefined, cost: number): InventoryCatalogItem {
  return {
    ruleCode: rule.code,
    name: rule.name,
    description: rule.description,
    cost,
    section: sectionOf(rule),
    subtitle: categoryLabelOf(spec),
    type: typeOf(rule, spec),
    spec,
    featureKeywords: featureKeywordsOf(rule),
    keywordIds: rule.keywordIds ?? [],
  };
}

function ruleOf(code: string | null): Rule | undefined {
  if (!code) return undefined;

  return props.rules.find((entry) => entry.code === code);
}

function itemViewFromOwned(owned: InventoryItem): InventoryCatalogItem | null {
  const rule = ruleOf(owned.ruleCode);
  if (rule) {
    const spec = rule.spec as ItemSpec | undefined;

    return itemViewFromRule(rule, spec, spec?.cost_gm ?? 0);
  }
  if (owned.ruleCode) {
    return {
      ruleCode: owned.ruleCode,
      name: owned.name ?? owned.ruleCode,
      description: owned.description ?? '',
      cost: 0,
      section: null,
      subtitle: ITEM_LABELS.category.other,
      type: 'other',
      spec: undefined,
      featureKeywords: [],
      keywordIds: [],
    };
  }

  return {
    ruleCode: '',
    name: owned.name ?? 'Предмет мастера',
    description: owned.description ?? '',
    cost: 0,
    section: null,
    subtitle: ITEM_LABELS.category.other,
    type: 'other',
    spec: undefined,
    featureKeywords: [],
    keywordIds: [],
  };
}

type SheetListItem = InventoryCatalogItem & { inventoryId: number; instanceEquipped: boolean };

const ownedList = computed<SheetListItem[]>(() => {
  const rows: SheetListItem[] = [];
  for (const owned of props.build.inventory) {
    const item = itemViewFromOwned(owned);
    if (!item) continue;
    rows.push({ ...item, inventoryId: owned.id, instanceEquipped: owned.equipped });
  }

  return rows;
});

/** Значения характеристик по коду — для оценки профилей оружия в строках. */
const characteristicValues = computed(() => {
  const map = new Map<string, DimensionalNumberValue>();
  for (const characteristic of props.model.characteristics) {
    map.set(characteristic.code, characteristic.value);
  }

  return map;
});

const catalogFilterFields = computed<FilterField[]>(() => [
  { key: 'name', label: 'Название', type: 'string' },
  {
    key: 'section',
    label: 'Раздел',
    type: 'select',
    options: SECTIONS.value.map((section) => ({ label: section, value: section })),
  },
]);

const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows({
  getItems: () => (props.variant === 'sheet' ? ownedList.value : catalog.value),
  fields: catalogFilterFields.value,
  searchFields: ['name'],
});

const categoryFilter = ref<InventoryItemType | 'all'>('all');
const acquiredOnly = ref(false);
const equippedOnly = ref(false);
/** Раскрытые панели предметов (переживают ремаунты строк виртуализации). */
const openSet = ref<Set<string>>(new Set());

function itemKeywordCodes(keywordIds: number[]): string[] {
  return keywordIds.map((id) => keywordCodeById.value.get(id)).filter((code): code is string => Boolean(code));
}

function effectMatchesType(label: string | null | undefined, type: InventoryItemType): boolean {
  if (!label) return true;
  const text = label.toLowerCase();
  if (text.includes('общее')) return true;
  if (type === 'armor') return text.includes('доспех');
  if (type === 'shield') return text.includes('щит');
  if (type === 'weapon') return text.includes('оружие');

  return true;
}

function modifiersOf(item: InventoryCatalogItem): InventoryModifierOption[] {
  const codes = itemKeywordCodes(item.keywordIds);

  return props.rules
    .filter((rule) => rule.type === 'item_modifier')
    .filter((rule) => {
      const spec = rule.spec as ItemModifierSpec | undefined;

      return itemModifierService.isApplicable(spec?.applies, codes);
    })
    .map((rule) => {
      const spec = rule.spec as ItemModifierSpec;
      const typeRule = props.rules.find(
        (entry) => entry.type === 'item_modifier_type' && entry.code === spec.type_code,
      );
      const effects = (spec.effects ?? [])
        .filter((effect) => effect.text.trim() && effectMatchesType(effect.label, item.type))
        .map((effect) => (effect.label ? `${effect.label}: ${effect.text}` : effect.text));

      return {
        ruleCode: rule.code,
        name: rule.name,
        category: typeRule?.name ?? '',
        priceLabel: itemModifierService.formatPriceLabel(spec.price, codes),
        effects,
      };
    });
}

function costOf(item: InventoryCatalogItem, modifierRuleCodes: readonly string[]): number {
  const codes = itemKeywordCodes(item.keywordIds);
  if (!item.spec) return item.cost;
  const modifiers = modifierRuleCodes
    .map((id) => props.rules.find((entry) => entry.code === id))
    .filter((entry): entry is Rule => entry !== undefined);

  return itemModifierService.applyStack(item.spec, modifiers, codes).cost;
}

function isInstanced(item: InventoryCatalogItem): boolean {
  return Boolean(item.spec?.weapon || item.spec?.armor || item.spec?.shield);
}

/** Базовая линия шага «Инвентарь» (R2): снапшот на входе в шаг. */
const baseline = computed(() => draftStore.draftOf(props.draftKey)?.inventoryBaseline ?? null);

const ownedByRuleId = computed(() => {
  const map = new Map<string, number>();
  for (const item of props.build.inventory) {
    if (item.ruleCode === null) continue;
    map.set(item.ruleCode, (map.get(item.ruleCode) ?? 0) + item.quantity);
  }

  return map;
});

const baselineByRuleId = computed(() => {
  const map = new Map<string, number>();
  for (const item of baseline.value?.inventory ?? []) {
    if (item.ruleCode === null) continue;
    map.set(item.ruleCode, (map.get(item.ruleCode) ?? 0) + item.quantity);
  }

  return map;
});

const equippedByRuleId = computed(() => {
  const map = new Set<string>();
  for (const item of props.build.inventory) {
    if (item.equipped && item.ruleCode !== null) map.add(item.ruleCode);
  }

  return map;
});

function passesFilters(item: InventoryCatalogItem, instanceEquipped?: boolean): boolean {
  if (categoryFilter.value !== 'all' && item.type !== categoryFilter.value) return false;
  if (props.variant !== 'sheet' && acquiredOnly.value && (ownedByRuleId.value.get(item.ruleCode) ?? 0) <= 0)
    return false;
  if (equippedOnly.value) {
    if (props.variant === 'sheet') return instanceEquipped === true;
    if (!equippedByRuleId.value.has(item.ruleCode)) return false;
  }

  return true;
}

type InventoryListRow = InventoryCatalogItem & {
  rowKind: 'shop' | 'owned';
  inventoryId: number | null;
};

const catalogRows = computed<InventoryListRow[]>(() => {
  if (props.variant === 'sheet') {
    const rows: InventoryListRow[] = [];
    for (const item of filteredRows.value as SheetListItem[]) {
      if (!passesFilters(item, item.instanceEquipped)) continue;
      rows.push({ ...item, rowKind: 'owned', inventoryId: item.inventoryId });
    }

    return rows.sort((a, b) => {
      const section = (a.section ?? '').localeCompare(b.section ?? '');
      if (section !== 0) return section;
      if (a.ruleCode !== b.ruleCode) return a.name.localeCompare(b.name);

      return (a.inventoryId ?? 0) - (b.inventoryId ?? 0);
    });
  }

  const rows: InventoryListRow[] = [];
  for (const item of filteredRows.value.filter((entry) => passesFilters(entry))) {
    if (!equippedOnly.value) {
      rows.push({ ...item, rowKind: 'shop', inventoryId: null });
    }
    if (!isInstanced(item)) continue;
    for (const owned of props.build.inventory.filter((entry) => entry.ruleCode === item.ruleCode)) {
      if (equippedOnly.value && !owned.equipped) continue;
      rows.push({ ...item, rowKind: 'owned', inventoryId: owned.id });
    }
  }

  return rows.sort((a, b) => {
    const section = (a.section ?? '').localeCompare(b.section ?? '');
    if (section !== 0) return section;
    if (a.ruleCode !== b.ruleCode) return a.name.localeCompare(b.name);
    if (a.rowKind !== b.rowKind) return a.rowKind === 'shop' ? -1 : 1;

    return (a.inventoryId ?? 0) - (b.inventoryId ?? 0);
  });
});

/** Сброс скролла каталога наверх при смене любого фильтра. */
const resetKey = computed(() =>
  JSON.stringify([appliedFilters.value, categoryFilter.value, acquiredOnly.value, equippedOnly.value]),
);

const catalogHeight = computed(() => props.listHeight);

const moneyExceeded = computed(() => props.model.budgets.money.exceeded);

const inventoryChanged = computed(() => {
  const base = baseline.value;
  if (!base) return false;
  if (base.money !== props.build.money) return true;
  if (base.inventory.length !== props.build.inventory.length) return true;

  return base.inventory.some(
    (left) =>
      !props.build.inventory.some(
        (right) =>
          right.id === left.id &&
          right.ruleCode === left.ruleCode &&
          right.quantity === left.quantity &&
          itemModifierService.identityKey(right.ruleCode ?? '', right.modifierRuleCodes) ===
            itemModifierService.identityKey(left.ruleCode ?? '', left.modifierRuleCodes),
      ),
  );
});

function setOpen(rowKey: string, open: boolean): void {
  if (open) openSet.value.add(rowKey);
  else openSet.value.delete(rowKey);
}

/**
 * Свежайший build из стора: patchBuild обновляет стор синхронно, а props.build — только после
 * (медленного) пересчёта модели редактора. Быстрые клики по инвентарю иначе работали бы со
 * устаревшим build и перезаписывали результат предыдущего клика.
 */
function currentBuild(): CharacterBuild {
  props.ensureDraft?.();

  return draftStore.draftOf(props.draftKey)?.build ?? props.build;
}

function mutateBuild(patch: Partial<CharacterBuild>): void {
  draftStore.patchBuild(props.draftKey, patch);
}

function buy(ruleCode: string): void {
  const next = characterBuildService.buyItem(currentBuild(), ruleCode, 1, props.rules, props.keywords);
  mutateBuild({ inventory: next.inventory, money: next.money });
}

function cancel(ruleCode: string, quantity: number): void {
  const next = characterBuildService.cancelItemPurchase(
    currentBuild(),
    baseline.value,
    ruleCode,
    quantity,
    props.rules,
    props.keywords,
  );
  mutateBuild({ inventory: next.inventory, money: next.money });
}

function cancelInstance(itemId: number): void {
  const next = characterBuildService.cancelItemInstance(
    currentBuild(),
    baseline.value,
    itemId,
    props.rules,
    props.keywords,
  );
  mutateBuild({ inventory: next.inventory, money: next.money });
}

function toggleEquipped(itemId: number): void {
  if (props.onToggleEquipped) {
    props.onToggleEquipped(itemId);

    return;
  }
  const next = characterBuildService.toggleItemEquipped(currentBuild(), itemId, props.rules);
  mutateBuild({ inventory: next.inventory });
}

function applyOwnedModifiers(itemId: number, modifierRuleCodes: string[]): void {
  const next = characterBuildService.applyItemModifiers(
    currentBuild(),
    itemId,
    modifierRuleCodes,
    props.rules,
    props.keywords,
  );
  mutateBuild({ inventory: next.inventory, money: next.money });
}

function itemKey(item: InventoryListRow): string {
  return item.rowKind === 'owned' ? `owned-${item.inventoryId}` : `shop-${item.ruleCode}`;
}

function ownedOf(row: InventoryListRow) {
  return props.build.inventory.find((entry) => entry.id === row.inventoryId);
}

/** Прокачка «Владения оружием» семьи предмета (зона ОР): уровень 0 — снять владение. */
function train(mastery: ItemMasteryView, level: number): void {
  const next = characterBuildService.setWeaponMastery(
    currentBuild(),
    mastery.masteryRuleCode,
    mastery.familyName,
    mastery.familyCode,
    level,
    props.rules,
  );
  mutateBuild({ abilities: next.abilities });
}

/** Покупка/снятие оружейного навыка из слайдера (зона ОР, как на вкладке «Развитие»). */
function setSkillLevel(ruleCode: string, level: number): void {
  const next = characterBuildService.setAbilityLevel(currentBuild(), ruleCode, level, props.rules, { zone: 'or' });
  mutateBuild({ abilities: next.abilities });
}

function resetInventory(): void {
  const next = characterBuildService.resetInventory(currentBuild(), baseline.value, props.rules);
  mutateBuild({ inventory: next.inventory, money: next.money, abilities: next.abilities });
}

function openWeaponSkills(familyCode: string, keywordCode: string | null): void {
  weaponSkillsFamilyCode.value = familyCode;
  weaponSkillsKeywordCode.value = keywordCode;
  showWeaponSkills.value = true;
}

const modifierPickerOpen = computed({
  get: () => modifierPickerItemId.value !== null,
  set: (value: boolean) => {
    if (!value) modifierPickerItemId.value = null;
  },
});

const modifierPickerCatalogItem = computed(() => {
  const itemId = modifierPickerItemId.value;
  if (itemId === null) return null;
  const owned = props.build.inventory.find((entry) => entry.id === itemId);
  if (!owned?.ruleCode) return null;

  return catalog.value.find((item) => item.ruleCode === owned.ruleCode) ?? null;
});

const modifierPickerModifiers = computed(() =>
  modifierPickerCatalogItem.value ? modifiersOf(modifierPickerCatalogItem.value) : [],
);

const modifierPickerSelected = computed(
  () => props.build.inventory.find((entry) => entry.id === modifierPickerItemId.value)?.modifierRuleCodes ?? [],
);

function openModifiers(itemId: number): void {
  modifierPickerItemId.value = itemId;
}

function applyModifierPicker(modifierRuleCodes: string[]): void {
  if (modifierPickerItemId.value === null) return;
  applyOwnedModifiers(modifierPickerItemId.value, modifierRuleCodes);
}

/** Очистка состояния слайдера при закрытии (пользователь кликнет на панель). */
watch(showWeaponSkills, (val) => {
  if (!val) {
    weaponSkillsFamilyCode.value = null;
    weaponSkillsKeywordCode.value = null;
  }
});
</script>

<template>
  <div>
    <v-alert
      v-if="variant === 'editor' && moneyExceeded"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-3"
      title="Превышен лимит денег"
    >
      Покупки сверх бюджета возможны в черновике, но персонаж не сохранится, пока лимит не будет восстановлен.
    </v-alert>

    <FilterBar
      :fields="catalogFilterFields"
      :model-value="appliedFilters"
      placeholder="Фильтр по предметам"
      :settings-key="variant === 'sheet' ? 'character-sheet-inventory' : 'character-editor-inventory'"
      class="mb-2"
      @update:model-value="onFilterChange"
    />

    <div class="d-flex align-center ga-2 mb-3 flex-wrap">
      <v-tabs v-model="categoryFilter" density="compact" class="category-tabs">
        <v-tab value="all">Все</v-tab>
        <v-tab value="weapon">Оружие</v-tab>
        <v-tab value="shield">Щит</v-tab>
        <v-tab value="armor">Доспех</v-tab>
        <v-tab value="potion">Зелье</v-tab>
      </v-tabs>
      <div class="flex-grow-1" />
      <v-chip
        v-if="variant === 'editor'"
        size="small"
        variant="tonal"
        :color="acquiredOnly ? 'primary' : undefined"
        @click="acquiredOnly = !acquiredOnly"
      >
        Приобретено
      </v-chip>
      <v-chip
        size="small"
        variant="tonal"
        :color="equippedOnly ? 'primary' : undefined"
        @click="equippedOnly = !equippedOnly"
      >
        Экипировано
      </v-chip>
      <v-btn
        v-if="variant === 'editor' && inventoryChanged"
        size="small"
        variant="tonal"
        color="warning"
        prepend-icon="mdi-restore"
        @click="resetInventory"
      >
        Сбросить
      </v-btn>
    </div>

    <VirtualList
      :items="catalogRows"
      :estimate-size="48"
      :get-item-key="itemKey"
      :reset-key="resetKey"
      :height="catalogHeight"
      :empty-text="variant === 'sheet' ? 'Инвентарь пуст' : 'Предметы не найдены.'"
    >
      <template #default="{ item }">
        <InventoryItemRow
          :item="item"
          :rules="rules"
          :characteristic-values="characteristicValues"
          :abilities="build.abilities"
          :owned-qty="item.rowKind === 'owned' ? 1 : (ownedByRuleId.get(item.ruleCode) ?? 0)"
          :baseline-qty="
            item.rowKind === 'owned'
              ? baseline?.inventory.some((entry) => entry.id === item.inventoryId)
                ? 1
                : 0
              : (baselineByRuleId.get(item.ruleCode) ?? 0)
          "
          :equipped="item.rowKind === 'owned' ? (ownedOf(item)?.equipped ?? false) : false"
          :open="openSet.has(itemKey(item))"
          :modifiers="item.rowKind === 'owned' ? modifiersOf(item) : []"
          :selected-modifier-rule-ids="ownedOf(item)?.modifierRuleCodes ?? []"
          :display-cost="costOf(item, ownedOf(item)?.modifierRuleCodes ?? [])"
          :keyword-codes="itemKeywordCodes(item.keywordIds)"
          :mode="item.rowKind"
          :show-purchase="variant === 'editor'"
          :allow-equip="variant === 'editor' || canEdit"
          :allow-ability-edit="variant === 'editor'"
          @update:open="(open) => setOpen(itemKey(item), open)"
          @buy="buy"
          @cancel="cancel"
          @cancel-instance="item.inventoryId !== null && cancelInstance(item.inventoryId)"
          @toggle-equipped="item.inventoryId !== null && toggleEquipped(item.inventoryId)"
          @train="train"
          @open-skills="openWeaponSkills"
          @open-modifiers="item.inventoryId !== null && openModifiers(item.inventoryId)"
        />
      </template>
    </VirtualList>

    <ItemModifierPickerDialog
      v-model="modifierPickerOpen"
      :modifiers="modifierPickerModifiers"
      :selected-rule-ids="modifierPickerSelected"
      :item-keyword-codes="modifierPickerCatalogItem ? itemKeywordCodes(modifierPickerCatalogItem.keywordIds) : []"
      :rules="rules"
      @apply="applyModifierPicker"
    />

    <WeaponSkillsSlider
      v-if="weaponSkillsFamilyCode"
      v-model:open="showWeaponSkills"
      :family-code="weaponSkillsFamilyCode"
      :keyword-code="weaponSkillsKeywordCode"
      :rules="rules"
      :keywords="keywords"
      :abilities="build.abilities"
      @set-level="setSkillLevel"
    />
  </div>
</template>

<style scoped>
.category-tabs {
  max-width: 100%;
  overflow-x: auto;
}

@media (max-width: 960px) {
  .category-tabs {
    width: 100%;
  }
}
</style>
