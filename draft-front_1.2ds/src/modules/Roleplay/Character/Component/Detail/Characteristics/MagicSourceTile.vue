<script setup lang="ts">
import { computed } from 'vue';
import type { ItemStorageValue } from '@/modules/Roleplay/Character/Dto/ItemStorageValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import CharacteristicTile from '@/modules/Roleplay/Character/Component/Detail/Characteristics/CharacteristicTile.vue';
import { magicSourcePowerService } from '@/modules/Roleplay/Character/Service/Instance/magicSourcePowerService';

const props = defineProps<{
  source: ItemStorageValue;
  rules: Rule[];
}>();

const characteristic = computed(() => magicSourcePowerService.characteristicOf(props.source, props.rules));
</script>

<template>
  <CharacteristicTile :characteristic="characteristic" :rules="rules" :storage="source" />
</template>
