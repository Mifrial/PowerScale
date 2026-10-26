<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
import { RULE_LIST_FILTER_FIELDS } from '@/modules/Roleplay/RuleSpace/Constant/RULE_LIST_FILTER_FIELDS';
import { ruleContentStatusService } from '@/modules/Roleplay/Rule/init';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import TreeSelectFilter from '@/modules/Core/UI/Component/FilterBar/handlers/TreeSelectFilter.vue';
import VirtualList from '@/modules/Core/UI/Component/VirtualList.vue';
import DescriptionHtml from '@/modules/Core/UI/Component/DescriptionHtml.vue';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterOptionValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';

const props = withDefaults(
  defineProps<{
    rules: Rule[];
    spaceCode: string;
    ctx: string | undefined;
    isDraftContext: boolean;
    sections: AbilitySection[];
    addedRuleCodes: Set<string>;
    changedRuleCodes: Set<string>;
    /** Высота скролл-области списка правил. */
    height?: string | number;
  }>(),
  {
    height: 'calc(100vh - 220px)',
  },
);

const emit = defineEmits<{
  discard: [rule: Rule];
}>();

const showOnlyChanged = ref(false);
const sectionCode = ref<FilterOptionValue | null>(null);

const filterRows = computed(() =>
  props.rules.map((rule) => ({
    ...rule,
    contentStatus: ruleContentStatusService.normalize(rule.contentStatus),
  })),
);

const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows({
  getItems: () => filterRows.value,
  fields: RULE_LIST_FILTER_FIELDS,
  searchFields: ['name', 'description'],
});

const sectionTreeOptions = computed(() => abilitySectionTreeService.flatten(props.sections));

const sectionFilterField = computed<FilterField>(() => ({
  key: 'catalogSection',
  label: 'Раздел',
  type: 'tree-select',
  treeOptions: sectionTreeOptions.value.map((section) => ({
    label: section.name,
    value: section.code,
    path: section.path,
    depth: section.depth,
    parentValue: section.parentCode,
  })),
}));

const sectionCodes = computed<Set<string> | null>(() => {
  const selected = sectionCode.value;
  if (selected === null || selected === undefined || selected === '') return null;

  return new Set(abilitySectionTreeService.descendantCodes(String(selected), props.sections));
});

const affectedRuleCodes = computed(() => new Set([...props.addedRuleCodes, ...props.changedRuleCodes]));

const filteredRules = computed(() => {
  let rows = filteredRows.value;
  const codes = sectionCodes.value;
  if (codes) {
    rows = rows.filter((rule) => rule.catalogSection != null && codes.has(rule.catalogSection));
  }
  if (showOnlyChanged.value) {
    rows = rows.filter((rule) => affectedRuleCodes.value.has(rule.code));
  }

  return rows;
});

/** Сброс скролла наверх при смене фильтров. */
const resetKey = computed(
  () => `${JSON.stringify(appliedFilters.value)}|${String(sectionCode.value ?? '')}|${showOnlyChanged.value}`,
);

watch(
  () => props.isDraftContext,
  (isDraft) => {
    if (!isDraft) showOnlyChanged.value = false;
  },
);

watch(affectedRuleCodes, (codes) => {
  if (codes.size === 0) showOnlyChanged.value = false;
});

watch(
  () => props.spaceCode,
  () => {
    showOnlyChanged.value = false;
    sectionCode.value = null;
  },
);

function onSectionFilterChange(value: FilterOptionValue | null | undefined): void {
  sectionCode.value = value ?? null;
}

function statusFrameClass(rule: Rule): string {
  return ruleContentStatusService.frameModifier(rule.contentStatus);
}

function ruleLink(code: string): string {
  return `/space/${props.spaceCode}/${props.ctx ?? ''}/rules/${encodeURIComponent(code)}`;
}

function ruleKey(rule: Rule): string {
  return rule.code;
}

function discardTitle(rule: Rule): string {
  return props.addedRuleCodes.has(rule.code) ? 'Убрать из черновика' : 'Откатить изменения';
}
</script>

<template>
  <div>
    <FilterBar
      :fields="RULE_LIST_FILTER_FIELDS"
      :model-value="appliedFilters"
      placeholder="Фильтр по правилам"
      settings-key="space-rules"
      class="mb-2"
      @update:model-value="onFilterChange"
    />

    <div class="rule-list-panel__quick-filter d-flex align-center mb-3">
      <TreeSelectFilter
        :field="sectionFilterField"
        :model-value="sectionCode"
        eager
        class="rule-list-panel__section-filter"
        @update:model-value="onSectionFilterChange"
      />
      <v-chip
        v-if="isDraftContext"
        size="small"
        variant="tonal"
        class="rule-list-panel__changed-chip"
        :color="showOnlyChanged ? 'primary' : undefined"
        :disabled="affectedRuleCodes.size === 0"
        @click="showOnlyChanged = !showOnlyChanged"
      >
        Изменённое
      </v-chip>
    </div>

    <VirtualList
      :items="filteredRules"
      :estimate-size="72"
      :get-item-key="ruleKey"
      :reset-key="resetKey"
      :height="height"
      empty-text="Правила не найдены"
    >
      <template #default="{ item }">
        <v-list-item :to="ruleLink(item.code)" class="rule-list-panel__item" :class="statusFrameClass(item)">
          <v-list-item-title>
            {{ item.name }}
            <v-chip v-if="addedRuleCodes.has(item.code)" size="x-small" color="success" variant="tonal" class="ml-2">
              Новое
            </v-chip>
            <v-chip
              v-else-if="changedRuleCodes.has(item.code)"
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-2"
            >
              Изменено
            </v-chip>
          </v-list-item-title>
          <v-list-item-subtitle>
            <DescriptionHtml :html="item.description" class="rule-list-panel__description" />
          </v-list-item-subtitle>
          <template #append>
            <div class="d-flex align-center ga-2">
              <v-chip size="x-small" variant="tonal">
                {{ RULE_TYPE_LABELS[item.type] }}
              </v-chip>
              <v-btn
                v-if="isDraftContext && affectedRuleCodes.has(item.code)"
                icon
                size="x-small"
                color="error"
                variant="text"
                :title="discardTitle(item)"
                @click.prevent="emit('discard', item)"
              >
                <v-icon size="small">mdi-undo</v-icon>
              </v-btn>
            </div>
          </template>
        </v-list-item>
      </template>
    </VirtualList>
  </div>
</template>

<style scoped>
.rule-list-panel__quick-filter {
  gap: 10px;
}

.rule-list-panel__section-filter {
  flex: 1 1 auto;
  min-width: 0;
}

.rule-list-panel__changed-chip {
  margin-left: auto;
}

.rule-list-panel__description {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rule-list-panel__item {
  margin-bottom: 6px;
  overflow: hidden;
  border-radius: 12px !important;
  background: rgb(var(--v-theme-surface));
}

.rule-content-status--ready {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.rule-content-status--needs_work {
  border: 1px solid rgb(var(--v-theme-warning));
}

.rule-content-status--broken {
  border: 1px solid rgb(var(--v-theme-error));
}
</style>
