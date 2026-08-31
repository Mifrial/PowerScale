<script setup lang="ts">
import EditorAbilityRow from '@/modules/Roleplay/Character/Component/Editor/EditorAbilityRow.vue';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  ability: EditorAbility;
  /** Карта «код способности → её улучшения» (рекурсивно, включая вложенные). */
  childrenByCode: Map<string, EditorAbility[]>;
  keywords: Keyword[];
  /** Общий каталог правил (для имён ресурсов в шагах процесса). */
  rules?: Rule[];
  /** Раскрытые панели (управляемое состояние — переживает ремаунты виртуализации). */
  openSet: Set<string>;
}>();

const emit = defineEmits<{
  'update:open': [ruleCode: string, open: boolean];
  'set-level': [ruleCode: string, level: number];
  'set-parameter': [ruleCode: string, code: string, value: number | { base: number; size: number }];
  'add-instance': [ruleCode: string, domain: string, domainCode: string | null];
  'set-instance-level': [ruleCode: string, domain: string, level: number];
  'set-instance-domain': [ruleCode: string, oldDomain: string, newDomain: string, domainCode: string | null];
  'remove-instance': [ruleCode: string, domain: string];
  'set-ability-domain': [ruleCode: string, domain: string, domainCode: string | null];
}>();

function childrenOf(ability: EditorAbility): EditorAbility[] {
  return props.childrenByCode.get(ability.code) ?? [];
}
</script>

<template>
  <div class="ability-node">
    <EditorAbilityRow
      :ability="ability"
      :keywords="keywords"
      :rules="rules"
      zone-code="or"
      zone-label="ОР"
      :open="openSet.has(ability.ruleCode)"
      @update:open="emit('update:open', ability.ruleCode, $event)"
      @set-parameter="(ruleCode, code, value) => emit('set-parameter', ruleCode, code, value)"
      @set-level="(ruleCode, level) => emit('set-level', ruleCode, level)"
      @add-instance="(ruleCode, domain, code) => emit('add-instance', ruleCode, domain, code)"
      @set-instance-level="(ruleCode, domain, level) => emit('set-instance-level', ruleCode, domain, level)"
      @set-instance-domain="
        (ruleCode, oldDomain, newDomain, code) => emit('set-instance-domain', ruleCode, oldDomain, newDomain, code)
      "
      @remove-instance="(ruleCode, domain) => emit('remove-instance', ruleCode, domain)"
      @set-ability-domain="(ruleCode, domain, code) => emit('set-ability-domain', ruleCode, domain, code)"
    >
      <template v-if="childrenOf(ability).length" #nested>
        <div class="ability-node__nested">
          <DevelopmentAbilityNode
            v-for="child in childrenOf(ability)"
            :key="child.ruleCode"
            :ability="child"
            :children-by-code="childrenByCode"
            :keywords="keywords"
            :rules="rules"
            :open-set="openSet"
            @update:open="(ruleCode, open) => emit('update:open', ruleCode, open)"
            @set-parameter="(ruleCode, code, value) => emit('set-parameter', ruleCode, code, value)"
            @set-level="(ruleCode, level) => emit('set-level', ruleCode, level)"
            @add-instance="(ruleCode, domain, code) => emit('add-instance', ruleCode, domain, code)"
            @set-instance-level="(ruleCode, domain, level) => emit('set-instance-level', ruleCode, domain, level)"
            @set-instance-domain="
              (ruleCode, oldDomain, newDomain, code) =>
                emit('set-instance-domain', ruleCode, oldDomain, newDomain, code)
            "
            @remove-instance="(ruleCode, domain) => emit('remove-instance', ruleCode, domain)"
            @set-ability-domain="(ruleCode, domain, code) => emit('set-ability-domain', ruleCode, domain, code)"
          />
        </div>
      </template>
    </EditorAbilityRow>
  </div>
</template>

<style scoped>
/* Вложенные улучшения: отступ вглубь + разделитель слева, верхний бордер первого узла. */
.ability-node__nested {
  margin-top: 12px;
}

.ability-node__nested > .ability-node:first-child > .ability-row {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
