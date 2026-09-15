<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ethnicitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicitySpecService';
import { ETHNICITY_ROLE_ITEMS } from '@/modules/Roleplay/Rule/Constant/Ethnicity/ETHNICITY_ROLE_ITEMS';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed(() => ethnicitySpecService.resolve(props.rule.spec));

const roleTitle = computed(
  () => ETHNICITY_ROLE_ITEMS.find((item) => item.value === spec.value.role)?.title ?? spec.value.role,
);

const parentName = computed(() => {
  const code = spec.value.parent_code;
  if (!code) return null;

  return props.rules.find((entry) => entry.code === code)?.name ?? code;
});

const languageNames = computed(() =>
  spec.value.language_codes.map((code) => props.rules.find((entry) => entry.code === code)?.name ?? code),
);
</script>

<template>
  <v-card variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2">{{ roleTitle }}</div>
      <div v-if="parentName" class="text-body-2 text-medium-emphasis mt-1">Родитель: {{ parentName }}</div>
      <div v-if="languageNames.length" class="text-body-2 text-medium-emphasis mt-1">
        Языки: {{ languageNames.join(', ') }}
      </div>
    </v-card-text>
  </v-card>
</template>
