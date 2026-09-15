<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { languageSpecService } from '@/modules/Roleplay/Rule/Service/Instance/languageSpecService';
import { LANGUAGE_ROLE_ITEMS } from '@/modules/Roleplay/Rule/Constant/Language/LANGUAGE_ROLE_ITEMS';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed(() => languageSpecService.resolve(props.rule.spec));

const roleTitle = computed(
  () => LANGUAGE_ROLE_ITEMS.find((item) => item.value === spec.value.role)?.title ?? spec.value.role,
);

const parentName = computed(() => {
  const code = spec.value.parent_code;
  if (!code) return null;

  return props.rules.find((entry) => entry.code === code)?.name ?? code;
});

const writingSystemNames = computed(() =>
  spec.value.script_codes.map((code) => props.rules.find((entry) => entry.code === code)?.name ?? code),
);
</script>

<template>
  <v-card variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2">{{ roleTitle }}</div>
      <div v-if="parentName" class="text-body-2 text-medium-emphasis mt-1">Родитель: {{ parentName }}</div>
      <div v-if="writingSystemNames.length" class="text-body-2 text-medium-emphasis mt-1">
        Письменности: {{ writingSystemNames.join(', ') }}
      </div>
    </v-card-text>
  </v-card>
</template>
