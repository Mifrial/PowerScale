<script setup lang="ts">
import { computed, ref } from 'vue';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
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
const searchQuery = ref('');

const tabs = computed<RuleType[]>(() => Object.keys(RULE_TYPE_LABELS) as RuleType[]);

const filteredRules = computed(() => {
  let result = props.rules;
  const tab = activeTab.value;
  if (tab !== 'all') {
    result = result.filter((r) => r.type === tab);
  }
  const q = searchQuery.value?.toLowerCase();
  if (q) {
    result = result.filter((r) => r.name.toLowerCase().includes(q) || r.description.toLowerCase().includes(q));
  }

  return result;
});

/** Сброс скролла наверх при смене вкладки/поиска. */
const resetKey = computed(() => `${activeTab.value}|${searchQuery.value}`);

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

    <v-text-field
      v-model="searchQuery"
      label="Поиск"
      prepend-inner-icon="mdi-magnify"
      clearable
      density="compact"
      hide-details
      class="mb-2"
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
        <v-list-item :to="ruleLink(item.code)">
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
</style>
