<script setup lang="ts">
import { computed, ref } from 'vue';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/init';
import VirtualList from '@/modules/Core/UI/Component/VirtualList.vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';

const props = withDefaults(
  defineProps<{
    rules: Rule[];
    spaceCode: string;
    ctx: string | undefined;
    isDraftContext: boolean;
    draftRuleIds: Set<string>;
    /** Высота скролл-области списка правил. */
    height?: string | number;
  }>(),
  {
    height: 'calc(100vh - 300px)',
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

function ruleLink(ruleId: string): string {
  return `/space/${props.spaceCode}/${props.ctx ?? ''}/rules/${ruleId}`;
}

function ruleKey(rule: Rule): string {
  return rule.id;
}
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <div class="text-h6">Правила ({{ filteredRules.length }})</div>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" :to="`/space/${spaceCode}/draft/rules/new`">
        Создать правило
      </v-btn>
    </div>

    <v-tabs v-model="activeTab" class="mb-4">
      <v-tab value="all">Все</v-tab>
      <v-tab v-for="tab in tabs" :key="tab" :value="tab">{{ RULE_TYPE_LABELS[tab] }}</v-tab>
    </v-tabs>

    <v-text-field v-model="searchQuery" label="Поиск" prepend-inner-icon="mdi-magnify" clearable class="mb-4" />

    <VirtualList
      :items="filteredRules"
      :estimate-size="72"
      :get-item-key="ruleKey"
      :reset-key="resetKey"
      :height="height"
      empty-text="Правила не найдены"
    >
      <template #default="{ item }">
        <v-list-item :to="ruleLink(item.id)">
          <v-list-item-title>
            {{ item.name }}
            <v-chip v-if="draftRuleIds.has(item.id)" size="x-small" color="warning" variant="tonal" class="ml-2">
              Изменено
            </v-chip>
          </v-list-item-title>
          <v-list-item-subtitle>{{ item.description }}</v-list-item-subtitle>
          <template #append>
            <div class="d-flex align-center ga-2">
              <v-chip size="x-small" variant="tonal">
                {{ RULE_TYPE_LABELS[item.type] }}
              </v-chip>
              <v-btn
                v-if="isDraftContext && draftRuleIds.has(item.id)"
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
