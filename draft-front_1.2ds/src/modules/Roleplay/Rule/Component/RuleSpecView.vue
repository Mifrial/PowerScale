<script setup lang="ts">
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import AbilityCard from '@/modules/Roleplay/Rule/Component/Cards/AbilityCard.vue';
import AgeCard from '@/modules/Roleplay/Rule/Component/Cards/AgeCard.vue';
import CharacteristicCard from '@/modules/Roleplay/Rule/Component/Cards/CharacteristicCard.vue';
import CheckCard from '@/modules/Roleplay/Rule/Component/Cards/CheckCard.vue';
import DamageTypeCard from '@/modules/Roleplay/Rule/Component/Cards/DamageTypeCard.vue';
import ItemCard from '@/modules/Roleplay/Rule/Component/Cards/ItemCard.vue';
import ItemModifierTypeCard from '@/modules/Roleplay/Rule/Component/Cards/ItemModifierTypeCard.vue';
import MarkerCard from '@/modules/Roleplay/Rule/Component/Cards/MarkerCard.vue';
import ModifierCard from '@/modules/Roleplay/Rule/Component/Cards/ModifierCard.vue';
import PointsCard from '@/modules/Roleplay/Rule/Component/Cards/PointsCard.vue';
import PoisonCard from '@/modules/Roleplay/Rule/Component/Cards/PoisonCard.vue';
import RaceCard from '@/modules/Roleplay/Rule/Component/Cards/RaceCard.vue';
import ResourceCard from '@/modules/Roleplay/Rule/Component/Cards/ResourceCard.vue';
import SpeciesCard from '@/modules/Roleplay/Rule/Component/Cards/SpeciesCard.vue';
import StateCard from '@/modules/Roleplay/Rule/Component/Cards/StateCard.vue';
import WeaponFamilyCard from '@/modules/Roleplay/Rule/Component/Cards/WeaponFamilyCard.vue';

defineProps<{
  rule: Rule;
  rules: Rule[];
  keywords: Keyword[];
}>();
</script>

<template>
  <AbilityCard v-if="rule.type === 'ability'" :rule="rule" :rules="rules" :keywords="keywords" />
  <RaceCard v-else-if="rule.type === 'race'" :rule="rule" :rules="rules" />
  <SpeciesCard v-else-if="rule.type === 'species'" :rule="rule" :rules="rules" />
  <PointsCard v-else-if="rule.type === 'points'" :rule="rule" />
  <StateCard v-else-if="rule.type === 'state'" :rule="rule" :rules="rules" />
  <PoisonCard v-else-if="rule.type === 'poison'" :rule="rule" :rules="rules" />
  <ModifierCard v-else-if="rule.type === 'item_modifier'" :rule="rule" :keywords="keywords" :rules="rules" />
  <CheckCard v-else-if="rule.type === 'check'" :rule="rule" :rules="rules" />
  <DamageTypeCard v-else-if="rule.type === 'damage_type'" :rule="rule" :rules="rules" />
  <ItemCard v-else-if="rule.type === 'item'" :rule="rule" :rules="rules" />
  <CharacteristicCard v-else-if="rule.type === 'characteristic'" :rule="rule" :rules="rules" />
  <ResourceCard v-else-if="rule.type === 'resource'" :rule="rule" :rules="rules" />
  <AgeCard v-else-if="rule.type === 'age'" :rule="rule" :rules="rules" />
  <WeaponFamilyCard v-else-if="rule.type === 'weapon_family'" :rule="rule" />
  <ItemModifierTypeCard v-else-if="rule.type === 'item_modifier_type'" :rule="rule" />
  <MarkerCard v-else-if="rule.type === 'sense' || rule.type === 'language' || rule.type === 'source'" :rule="rule" />
</template>
