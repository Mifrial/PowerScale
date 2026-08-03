<script setup lang="ts">
import type { InheritedAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/InheritedAbilityRef';

const props = defineProps<{
  refs: InheritedAbilityRef[];
  abilityNameMap: Map<string, string>;
}>();

function abilityName(code: string): string {
  return props.abilityNameMap.get(code) ?? code;
}
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Способности, которые раса получит от цепочки предков-видов (ближний → дальний).
    </div>
    <div v-if="refs.length === 0" class="text-body-2 text-medium-emphasis">
      Родитель не выбран или у предков нет способностей.
    </div>
    <v-chip v-for="(item, index) in refs" :key="index" size="small" class="mr-2 mb-2">
      {{ abilityName(item.ability_code) }} · от «{{ item.fromName }}»
    </v-chip>
  </div>
</template>
