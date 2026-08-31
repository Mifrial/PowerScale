<script setup lang="ts">
import type {
  DefenseArmorOverview,
  DefenseLineOverview,
} from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';
import DefenseValue from '@/modules/Roleplay/Character/Component/Detail/Defense/DefenseValue.vue';

defineProps<{
  item: DefenseArmorOverview;
}>();

function lowerSource(source: string): string {
  return source.charAt(0).toLowerCase() + source.slice(1);
}

function lineKey(line: DefenseLineOverview, index: number): string {
  return `${line.kind}_${index}`;
}
</script>

<template>
  <v-sheet class="armor-tile pa-2 rounded border d-flex flex-column">
    <div class="d-flex align-center justify-space-between ga-2">
      <RuleLink :rule-code="item.itemRuleCode" class="text-body-2 font-weight-medium armor-tile__name">
        {{ item.itemName }}
      </RuleLink>
      <v-menu attach location="bottom end" :z-index="2200">
        <template #activator="{ props: menuProps }">
          <v-btn
            v-bind="menuProps"
            icon="mdi-information-outline"
            size="x-small"
            variant="text"
            class="armor-tile__info"
          />
        </template>
        <v-card class="rounded border" elevation="3" style="min-width: 280px; max-width: 380px">
          <v-card-text class="pt-3">
            <div class="text-caption text-medium-emphasis mb-2">Слои защиты доспеха:</div>
            <div v-for="(line, index) in item.lines" :key="lineKey(line, index)" class="text-body-2 armor-tile__line">
              <span class="font-weight-medium text-primary">+{{ line.valueLabel }}</span>
              <template v-if="line.kind === 'defense'">
                <span> защиты</span>
              </template>
              <template v-else>
                <span> сопротивления {{ line.damageTypeDative ?? line.damageTypeLabel }}</span>
              </template>
              <span v-if="line.sourceLabel"> от {{ lowerSource(line.sourceLabel) }}</span>
              <span class="text-medium-emphasis"> · надёжность {{ line.durability }}</span>
            </div>
          </v-card-text>
        </v-card>
      </v-menu>
    </div>
    <div class="armor-tile__value">
      <DefenseValue :tiers="item.tiers" />
    </div>
  </v-sheet>
</template>

<style scoped>
.armor-tile {
  height: 100%;
  justify-content: center;
}

.armor-tile__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.armor-tile__line {
  line-height: 1.4;
}

.armor-tile__line + .armor-tile__line {
  margin-top: 2px;
}
</style>
