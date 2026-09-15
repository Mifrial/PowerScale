<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { scriptSpecService } from '@/modules/Roleplay/Rule/Service/Instance/scriptSpecService';
import { SCRIPT_KIND_ITEMS } from '@/modules/Roleplay/Rule/Constant/Script/SCRIPT_KIND_ITEMS';

const props = defineProps<{
  rule: Rule;
}>();

const kindTitle = computed(() => {
  const kind = scriptSpecService.resolve(props.rule.spec).kind;

  return SCRIPT_KIND_ITEMS.find((item) => item.value === kind)?.title ?? kind;
});
</script>

<template>
  <v-card variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2">{{ kindTitle }}</div>
    </v-card-text>
  </v-card>
</template>
