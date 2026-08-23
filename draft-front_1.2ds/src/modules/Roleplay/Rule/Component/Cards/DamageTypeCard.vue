<script setup lang="ts">
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { asDamageTypeSpec } from '@/modules/Roleplay/Rule/Utils/damageTypeSpec';
import { computed } from 'vue';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed(() => asDamageTypeSpec(props.rule));
const attached = computed(() => {
  const codes = spec.value?.attached_rule_codes ?? [];

  return codes
    .map((code) => props.rules.find((candidate) => candidate.code === code))
    .filter((rule): rule is Rule => rule !== undefined);
});
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-4">
    <v-card-text>
      <div class="text-body-2">
        Системное имя: <strong>{{ rule.code }}</strong>
      </div>
      <div v-if="spec.forms.genitive" class="text-body-2 mt-1">Родительный: {{ spec.forms.genitive }}</div>
      <div v-if="spec.forms.dative" class="text-body-2 mt-1">Дательный: {{ spec.forms.dative }}</div>
      <div v-if="spec.defense_ignored" class="text-body-2 mt-1">Защита не помогает</div>
      <div v-if="attached.length" class="text-body-2 mt-1">
        Механики:
        <strong>{{ attached.map((item) => item.name).join(', ') }}</strong>
      </div>
      <div v-else class="text-body-2 mt-1">Механики не подвешены</div>
    </v-card-text>
  </v-card>
</template>
