<script setup lang="ts">
import { computed, ref } from 'vue';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
import { RULE_LIST_FILTER_FIELDS } from '@/modules/Roleplay/RuleSpace/Constant/RULE_LIST_FILTER_FIELDS';
import { ruleContentStatusService } from '@/modules/Roleplay/Rule/init';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import VirtualList from '@/modules/Core/UI/Component/VirtualList.vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';
import DescriptionHtml from '@/modules/Core/UI/Component/DescriptionHtml.vue';

const props = withDefaults(
  defineProps<{
    rules: Rule[];
    spaceCode: string;
    ctx: string | undefined;
    isDraftContext: boolean;
    draftRuleCodes: Set<string>;
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

const activeTab = ref<string>('all');

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

const tabs = computed<RuleType[]>(() => Object.keys(RULE_TYPE_LABELS) as RuleType[]);

const filteredRules = computed(() => {
  const tab = activeTab.value;
  if (tab === 'all') {
    return filteredRows.value;
  }

  return filteredRows.value.filter((rule) => rule.type === tab);
});

/** Сброс скролла наверх при смене вкладки/поиска. */
const resetKey = computed(() => `${activeTab.value}|${JSON.stringify(appliedFilters.value)}`);

function statusFrameClass(rule: Rule): string {
  return ruleContentStatusService.frameModifier(rule.contentStatus);
}

function ruleLink(code: string): string {
  return `/space/${props.spaceCode}/${props.ctx ?? ''}/rules/${encodeURIComponent(code)}`;
}

function ruleKey(rule: Rule): string {
  return rule.code;
}
</script>

<template>
  <div>
    <v-tabs v-model="activeTab" class="mb-2" density="compact">
      <v-tab value="all">Все</v-tab>
      <v-tab v-for="tab in tabs" :key="tab" :value="tab">{{ RULE_TYPE_LABELS[tab] }}</v-tab>
    </v-tabs>

    <FilterBar
      :fields="RULE_LIST_FILTER_FIELDS"
      :model-value="appliedFilters"
      placeholder="Фильтр по правилам"
      settings-key="space-rules"
      class="mb-2"
      @update:model-value="onFilterChange"
    />

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
            <v-chip v-if="draftRuleCodes.has(item.code)" size="x-small" color="warning" variant="tonal" class="ml-2">
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
                v-if="isDraftContext && draftRuleCodes.has(item.code)"
                icon
                size="x-small"
                color="error"
                variant="text"
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
