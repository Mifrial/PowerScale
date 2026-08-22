<script setup lang="ts">
import { ref } from 'vue';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

defineProps<{
  attack: AttackOverview;
}>();

const open = ref(false);
</script>

<template>
  <v-menu v-model="open" location="bottom">
    <template #activator="{ props: menuProps }">
      <v-sheet v-bind="menuProps" class="pa-2 rounded border cursor-pointer">
        <div class="d-flex align-center justify-space-between ga-2">
          <span class="text-body-2 font-weight-medium text-truncate">{{ attack.itemName }}</span>
          <span class="text-body-2 text-no-wrap">{{ attack.accuracyLabel }}</span>
        </div>
        <div class="d-flex align-center justify-space-between ga-2">
          <span class="text-body-2 text-medium-emphasis text-truncate">{{ attack.profileTypeLabel }}</span>
          <span class="text-body-2 text-no-wrap">{{ attack.damageLabel }}</span>
        </div>
        <div class="d-flex align-center justify-space-between ga-2">
          <span class="text-body-2 text-medium-emphasis text-truncate">Дистанция {{ attack.distanceLabel }}</span>
          <span class="text-body-2 text-no-wrap">{{ attack.penetrationLabel }}</span>
        </div>
      </v-sheet>
    </template>

    <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">
        <RuleLink :rule-id="attack.itemRuleId">{{ attack.itemName }}</RuleLink>
        — {{ attack.profileTypeLabel }}
      </v-card-title>
      <v-card-text class="pt-0">
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Точность</span>
          <span class="font-weight-medium">{{ attack.accuracyLabel }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Урон</span>
          <span class="font-weight-medium">{{ attack.damageLabel }}</span>
        </div>
        <div class="text-caption text-medium-emphasis">формула: {{ attack.damageFormula }}</div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Пробитие</span>
          <span class="font-weight-medium">{{ attack.penetrationLabel }}</span>
        </div>
        <div class="text-caption text-medium-emphasis">формула: {{ attack.penetrationFormula }}</div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Дистанция</span>
          <span class="font-weight-medium">{{ attack.distanceLabel }}</span>
        </div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
