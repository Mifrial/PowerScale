<script setup lang="ts">
import type { CombatOverview } from '@/modules/Roleplay/Character/Dto/Overview/CombatOverview';
import CharacteristicTile from '@/modules/Roleplay/Character/Component/Detail/Characteristics/CharacteristicTile.vue';

defineProps<{
  combat: CombatOverview;
}>();
</script>

<template>
  <div>
    <div v-if="combat.melee || combat.ranged" class="text-subtitle-2 text-medium-emphasis">Мастерство боя</div>
    <template v-if="combat.melee">
      <div class="text-caption text-medium-emphasis mb-1">Ближний бой</div>
      <v-row dense>
        <v-col cols="6" sm="4" md="3">
          <CharacteristicTile :characteristic="combat.melee.stat" />
        </v-col>
        <v-col v-for="weapon in combat.melee.weapons" :key="weapon.ruleId" cols="6" sm="4" md="3">
          <CharacteristicTile :characteristic="weapon" />
        </v-col>
      </v-row>
    </template>
    <template v-if="combat.ranged">
      <div class="text-caption text-medium-emphasis mb-1 mt-3">Дальний бой</div>
      <v-row dense>
        <v-col cols="6" sm="4" md="3">
          <CharacteristicTile :characteristic="combat.ranged.stat" />
        </v-col>
        <v-col v-for="weapon in combat.ranged.weapons" :key="weapon.ruleId" cols="6" sm="4" md="3">
          <CharacteristicTile :characteristic="weapon" />
        </v-col>
      </v-row>
    </template>
  </div>
</template>
