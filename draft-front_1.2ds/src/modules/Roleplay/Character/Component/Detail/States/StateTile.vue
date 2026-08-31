<script setup lang="ts">
import { ref } from 'vue';
import type { StateEntryOverview } from '@/modules/Roleplay/Character/Dto/Overview/StateOverview';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = defineProps<{
  state: StateEntryOverview;
}>();

const open = ref(false);

function label(): string {
  return props.state.name;
}
</script>

<template>
  <v-menu v-model="open" attach location="bottom" :z-index="2200">
    <template #activator="{ props: menuProps }">
      <v-sheet v-bind="menuProps" class="pa-2 rounded border cursor-pointer" style="min-height: 32px">
        <div class="d-flex align-center justify-space-between ga-2">
          <span class="d-flex align-center text-body-2 text-medium-emphasis text-truncate">
            <v-icon v-if="state.iconCode" :icon="state.iconCode" size="small" class="mr-1" />
            {{ label() }}
          </span>
          <span class="d-flex align-center ga-1">
            <v-chip v-if="state.count > 1" size="x-small" variant="tonal">×{{ state.count }}</v-chip>
            <span v-if="state.valueLabel" class="text-body-2 font-weight-medium">{{ state.valueLabel }}</span>
          </span>
        </div>
      </v-sheet>
    </template>

    <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">{{ label() }}</v-card-title>
      <v-card-text class="pt-0">
        <div v-if="state.valueLabel" class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Значение</span>
          <span class="font-weight-medium">{{ state.valueLabel }}</span>
        </div>
        <div v-if="state.count > 1" class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Записей</span>
          <span class="font-weight-medium">{{ state.count }}</span>
        </div>
        <div
          v-if="state.aggregation !== 'independent'"
          class="d-flex align-center justify-space-between py-1 text-body-2"
        >
          <span class="text-medium-emphasis">Объединение</span>
          <span class="font-weight-medium">{{ state.aggregation }}</span>
        </div>
        <div v-if="state.dotLabel" class="py-1">
          <div class="text-medium-emphasis text-body-2">Профиль урона</div>
          <div class="text-body-2">{{ state.dotLabel }}</div>
        </div>
        <div class="pt-2">
          <RuleLink v-if="state.ruleCode" :rule-code="state.ruleCode" class="text-body-2"> Открыть правило → </RuleLink>
        </div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
