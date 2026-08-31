<script setup lang="ts">
import ExpandableItem from '@/modules/Core/UI/Component/ExpandableItem.vue';
import LightChip from '@/modules/Core/UI/Component/light/LightChip.vue';
import EditorAbilityRow from '@/modules/Roleplay/Character/Component/Editor/EditorAbilityRow.vue';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityGroup } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityGroup';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import DescriptionHtml from '@/modules/Core/UI/Component/DescriptionHtml.vue';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';

const props = defineProps<{
  group: EditorAbilityGroup;
  /** Участники после фильтров вкладки (для рендера); счётчики считаются по полному group.members. */
  members: EditorAbility[];
  keywords: Keyword[];
  /** Общий каталог правил (для имён ресурсов в шагах процесса). */
  rules?: Rule[];
  /** Способности, недоступные для взятия (группа исчерпала лимит выбора). */
  lockedRuleCodes?: Set<string>;
  /** Зона цен способностей (os/ol/or); по умолчанию «Основа». */
  zoneCode?: string;
  /** Подпись зоны в ценах («ОС»/«ОЛ»/«ОР»). */
  zoneLabel?: string;
  /** Доплата механики «Общие черты» по ruleCode способности (ОС). */
  surchargeByRuleCode?: Map<string, number>;
  /** Раскрытые панели группы и её участников (переживают ремаунты виртуализации). */
  openSet: Set<string>;
}>();

const { openRule } = useRuleDetailSlider();

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

function limitLabel(group: EditorAbilityGroup): string {
  return group.selectLimit === -1 || group.selectLimit === 0 ? '∞' : String(group.selectLimit);
}

function chosenIn(group: EditorAbilityGroup): number {
  return group.members.filter((member) => member.level > 0 || member.automatic).length;
}

function chosenMembers(group: EditorAbilityGroup): EditorAbility[] {
  return group.members.filter((member) => member.level > 0 || member.automatic);
}

/** Сколько зоны суммарно потрачено на группу. */
function spentIn(group: EditorAbilityGroup): number {
  return characterEditorService.spentInGroup(group, props.zoneCode ?? 'os');
}

function zoneLabelOf(): string {
  return props.zoneLabel ?? 'ОС';
}
</script>

<template>
  <ExpandableItem
    :model-value="openSet.has(group.ruleCode)"
    class="ability-group"
    :class="{ 'chosen-ability': chosenIn(group) > 0 }"
    @update:model-value="(open) => emit('update:open', group.ruleCode, open)"
  >
    <template #title>
      <div class="d-flex align-center ga-2 w-100 pr-2 group-title">
        <span class="font-weight-medium group-name">{{ group.name }}</span>
        <LightChip v-for="member in chosenMembers(group)" :key="member.ruleCode" color="primary">
          {{ member.name }}
        </LightChip>
        <LightChip>выбрано {{ chosenIn(group) }} из {{ limitLabel(group) }}</LightChip>
        <LightChip v-if="chosenIn(group) > 0" color="primary" class="group-spent">
          {{ spentIn(group) }} {{ zoneLabelOf() }}
        </LightChip>
      </div>
    </template>

    <div class="ability-group__body">
      <DescriptionHtml
        v-if="group.description"
        :html="group.description"
        class="text-body-2 group-description"
        @open-rule="openRule"
      />
      <EditorAbilityRow
        v-for="member in members"
        :key="member.ruleCode"
        :ability="member"
        :keywords="keywords"
        :rules="rules"
        :locked-rule-codes="lockedRuleCodes"
        :zone-code="zoneCode"
        :zone-label="zoneLabel"
        :surcharge-amount="surchargeByRuleCode?.get(member.ruleCode)"
        :open="openSet.has(member.ruleCode)"
        @update:open="emit('update:open', member.ruleCode, $event)"
        @set-level="(ruleCode, level) => emit('set-level', ruleCode, level)"
        @set-parameter="(ruleCode, code, value) => emit('set-parameter', ruleCode, code, value)"
        @add-instance="(ruleCode, domain, code) => emit('add-instance', ruleCode, domain, code)"
        @set-instance-level="(ruleCode, domain, level) => emit('set-instance-level', ruleCode, domain, level)"
        @set-instance-domain="
          (ruleCode, oldDomain, newDomain, code) => emit('set-instance-domain', ruleCode, oldDomain, newDomain, code)
        "
        @remove-instance="(ruleCode, domain) => emit('remove-instance', ruleCode, domain)"
        @set-ability-domain="(ruleCode, domain, code) => emit('set-ability-domain', ruleCode, domain, code)"
      />
    </div>
  </ExpandableItem>
</template>

<style scoped>
/* Лёгкий бордер по всем сторонам секции-группы. */
.ability-group {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Шапка группы — компактная, как у строк способностей. */
:deep(.expandable-item__trigger) {
  min-height: 48px;
  padding: 0 16px;
}

/* Группа с выбранным участником — еле заметная бледно-голубая заливка из темы. */
.chosen-ability {
  background-color: rgb(var(--v-theme-primaryLight));
}

/* Шапка группы: при нескольких выбранных участниках чипы переносятся на новую строку. */
.group-title {
  flex-wrap: wrap;
}

.group-name {
  min-width: 0;
}

/* Чип суммы потраченной зоны прижат вправо. */
.group-spent {
  margin-left: auto;
}

/* Тело группы: без отступа сверху, равные отступы по бокам и снизу. */
.ability-group__body {
  padding: 0 8px 8px;
}

/* Описание группы: отделяется от вложенных участников нижним бордером. */
.group-description {
  padding: 10px 16px 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  margin-bottom: 8px;
}
</style>
