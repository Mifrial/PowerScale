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
  'update:open': [ruleId: string, open: boolean];
  'set-level': [ruleId: string, level: number];
  'add-instance': [ruleId: string, domain: string, domainCode: string | null];
  'set-instance-level': [ruleId: string, domain: string, level: number];
  'set-instance-domain': [ruleId: string, oldDomain: string, newDomain: string, domainCode: string | null];
  'remove-instance': [ruleId: string, domain: string];
  'set-ability-domain': [ruleId: string, domain: string, domainCode: string | null];
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
      :open="openSet.has(ability.ruleId)"
      @update:open="emit('update:open', ability.ruleId, $event)"
      @set-level="(ruleId, level) => emit('set-level', ruleId, level)"
      @add-instance="(ruleId, domain, code) => emit('add-instance', ruleId, domain, code)"
      @set-instance-level="(ruleId, domain, level) => emit('set-instance-level', ruleId, domain, level)"
      @set-instance-domain="
        (ruleId, oldDomain, newDomain, code) => emit('set-instance-domain', ruleId, oldDomain, newDomain, code)
      "
      @remove-instance="(ruleId, domain) => emit('remove-instance', ruleId, domain)"
      @set-ability-domain="(ruleId, domain, code) => emit('set-ability-domain', ruleId, domain, code)"
    >
      <template v-if="childrenOf(ability).length" #nested>
        <div class="ability-node__nested">
          <DevelopmentAbilityNode
            v-for="child in childrenOf(ability)"
            :key="child.ruleId"
            :ability="child"
            :children-by-code="childrenByCode"
            :keywords="keywords"
            :rules="rules"
            :open-set="openSet"
            @update:open="(ruleId, open) => emit('update:open', ruleId, open)"
            @set-level="(ruleId, level) => emit('set-level', ruleId, level)"
            @add-instance="(ruleId, domain, code) => emit('add-instance', ruleId, domain, code)"
            @set-instance-level="(ruleId, domain, level) => emit('set-instance-level', ruleId, domain, level)"
            @set-instance-domain="
              (ruleId, oldDomain, newDomain, code) => emit('set-instance-domain', ruleId, oldDomain, newDomain, code)
            "
            @remove-instance="(ruleId, domain) => emit('remove-instance', ruleId, domain)"
            @set-ability-domain="(ruleId, domain, code) => emit('set-ability-domain', ruleId, domain, code)"
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
