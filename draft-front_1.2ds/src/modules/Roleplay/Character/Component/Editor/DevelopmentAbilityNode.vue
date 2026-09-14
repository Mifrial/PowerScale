<script setup lang="ts">
import EditorAbilityRow from '@/modules/Roleplay/Character/Component/Editor/EditorAbilityRow.vue';
import type { AbilityInstanceAddPayload } from '@/modules/Roleplay/Character/Dto/Editor/AbilityInstanceAddPayload';
import type { DevelopmentAbilityRow } from '@/modules/Roleplay/Character/Dto/Editor/DevelopmentAbilityRow';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  row: DevelopmentAbilityRow;
  childrenByParentKey: Map<string, DevelopmentAbilityRow[]>;
  keywords: Keyword[];
  rules?: Rule[];
  openSet: Set<string>;
}>();

const emit = defineEmits<{
  'update:open': [rowKey: string, open: boolean];
  'set-level': [ruleCode: string, level: number];
  'set-parameter': [ruleCode: string, code: string, value: number | { base: number; size: number }];
  'add-instance': [ruleCode: string, domain: string, domainCode: string | null, extras?: AbilityInstanceAddPayload];
  'set-instance-level': [ruleCode: string, domain: string, level: number];
  'set-instance-domain': [ruleCode: string, oldDomain: string, newDomain: string, domainCode: string | null];
  'remove-instance': [ruleCode: string, domain: string, domainCode?: string | null];
  'set-ability-domain': [ruleCode: string, domain: string, domainCode: string | null];
}>();

function childrenOf(row: DevelopmentAbilityRow): DevelopmentAbilityRow[] {
  return props.childrenByParentKey.get(row.key) ?? [];
}
</script>

<template>
  <div class="ability-node">
    <EditorAbilityRow
      :ability="row.ability"
      :keywords="keywords"
      :rules="rules"
      zone-code="or"
      zone-label="ОР"
      :spell-row-kind="row.spellRowKind"
      :open="openSet.has(row.key)"
      @update:open="emit('update:open', row.key, $event)"
      @set-parameter="(ruleCode, code, value) => emit('set-parameter', ruleCode, code, value)"
      @set-level="(ruleCode, level) => emit('set-level', ruleCode, level)"
      @add-instance="(ruleCode, domain, code, extras) => emit('add-instance', ruleCode, domain, code, extras)"
      @set-instance-level="(ruleCode, domain, level) => emit('set-instance-level', ruleCode, domain, level)"
      @set-instance-domain="
        (ruleCode, oldDomain, newDomain, code) => emit('set-instance-domain', ruleCode, oldDomain, newDomain, code)
      "
      @remove-instance="(ruleCode, domain, code) => emit('remove-instance', ruleCode, domain, code)"
      @set-ability-domain="(ruleCode, domain, code) => emit('set-ability-domain', ruleCode, domain, code)"
    >
      <template v-if="childrenOf(row).length" #nested>
        <div class="ability-node__nested">
          <DevelopmentAbilityNode
            v-for="child in childrenOf(row)"
            :key="child.key"
            :row="child"
            :children-by-parent-key="childrenByParentKey"
            :keywords="keywords"
            :rules="rules"
            :open-set="openSet"
            @update:open="(rowKey, open) => emit('update:open', rowKey, open)"
            @set-parameter="(ruleCode, code, value) => emit('set-parameter', ruleCode, code, value)"
            @set-level="(ruleCode, level) => emit('set-level', ruleCode, level)"
            @add-instance="(ruleCode, domain, code, extras) => emit('add-instance', ruleCode, domain, code, extras)"
            @set-instance-level="(ruleCode, domain, level) => emit('set-instance-level', ruleCode, domain, level)"
            @set-instance-domain="
              (ruleCode, oldDomain, newDomain, code) =>
                emit('set-instance-domain', ruleCode, oldDomain, newDomain, code)
            "
            @remove-instance="(ruleCode, domain, code) => emit('remove-instance', ruleCode, domain, code)"
            @set-ability-domain="(ruleCode, domain, code) => emit('set-ability-domain', ruleCode, domain, code)"
          />
        </div>
      </template>
    </EditorAbilityRow>
  </div>
</template>

<style scoped>
.ability-node__nested {
  margin-top: 12px;
}

.ability-node__nested > .ability-node:first-child > .ability-row {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
