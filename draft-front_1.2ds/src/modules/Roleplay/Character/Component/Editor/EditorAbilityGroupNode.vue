<script setup lang="ts">
import ExpandableItem from '@/modules/Core/UI/Component/ExpandableItem.vue';
import LightChip from '@/modules/Core/UI/Component/light/LightChip.vue';
import EditorAbilityRow from '@/modules/Roleplay/Character/Component/Editor/EditorAbilityRow.vue';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityGroup } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityGroup';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';

const props = defineProps<{
  group: EditorAbilityGroup;
  /** Участники после фильтров вкладки (для рендера); счётчики считаются по полному group.members. */
  members: EditorAbility[];
  keywords: Keyword[];
  /** Общий каталог правил (для имён ресурсов в шагах процесса). */
  rules?: Rule[];
  /** Способности, недоступные для взятия (группа исчерпала лимит выбора). */
  lockedRuleIds?: Set<string>;
  /** Зона цен способностей (os/ol/or); по умолчанию «Основа». */
  zoneCode?: string;
  /** Подпись зоны в ценах («ОС»/«ОЛ»/«ОР»). */
  zoneLabel?: string;
  /** Доплата механики «Общие черты» по ruleId способности (ОС). */
  surchargeByRuleId?: Map<string, number>;
  /** Раскрытые панели группы и её участников (переживают ремаунты виртуализации). */
  openSet: Set<string>;
}>();

const emit = defineEmits<{
  'update:open': [ruleId: string, open: boolean];
  'set-level': [ruleId: string, level: number];
  'set-parameter': [ruleId: string, code: string, value: number | { base: number; size: number }];
  'add-instance': [ruleId: string, domain: string, domainCode: string | null];
  'set-instance-level': [ruleId: string, domain: string, level: number];
  'set-instance-domain': [ruleId: string, oldDomain: string, newDomain: string, domainCode: string | null];
  'remove-instance': [ruleId: string, domain: string];
  'set-ability-domain': [ruleId: string, domain: string, domainCode: string | null];
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
    :model-value="openSet.has(group.ruleId)"
    class="ability-group"
    :class="{ 'chosen-ability': chosenIn(group) > 0 }"
    @update:model-value="(open) => emit('update:open', group.ruleId, open)"
  >
    <template #title>
      <div class="d-flex align-center ga-2 w-100 pr-2 group-title">
        <span class="font-weight-medium group-name">{{ group.name }}</span>
        <LightChip v-for="member in chosenMembers(group)" :key="member.ruleId" color="primary">
          {{ member.name }}
        </LightChip>
        <LightChip>выбрано {{ chosenIn(group) }} из {{ limitLabel(group) }}</LightChip>
        <LightChip v-if="chosenIn(group) > 0" color="primary" class="group-spent">
          {{ spentIn(group) }} {{ zoneLabelOf() }}
        </LightChip>
      </div>
    </template>

    <div class="ability-group__body">
      <p v-if="group.description" class="text-body-2 group-description">{{ group.description }}</p>
      <EditorAbilityRow
        v-for="member in members"
        :key="member.ruleId"
        :ability="member"
        :keywords="keywords"
        :rules="rules"
        :locked-rule-ids="lockedRuleIds"
        :zone-code="zoneCode"
        :zone-label="zoneLabel"
        :surcharge-amount="surchargeByRuleId?.get(member.ruleId)"
        :open="openSet.has(member.ruleId)"
        @update:open="emit('update:open', member.ruleId, $event)"
        @set-level="(ruleId, level) => emit('set-level', ruleId, level)"
        @set-parameter="(ruleId, code, value) => emit('set-parameter', ruleId, code, value)"
        @add-instance="(ruleId, domain, code) => emit('add-instance', ruleId, domain, code)"
        @set-instance-level="(ruleId, domain, level) => emit('set-instance-level', ruleId, domain, level)"
        @set-instance-domain="
          (ruleId, oldDomain, newDomain, code) => emit('set-instance-domain', ruleId, oldDomain, newDomain, code)
        "
        @remove-instance="(ruleId, domain) => emit('remove-instance', ruleId, domain)"
        @set-ability-domain="(ruleId, domain, code) => emit('set-ability-domain', ruleId, domain, code)"
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
