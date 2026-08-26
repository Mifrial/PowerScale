<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemModifierTypeSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierTypeSpec';

const props = defineProps<{
  rule: Rule;
}>();

const spec = computed<ItemModifierTypeSpec | null>(() =>
  props.rule.type === 'item_modifier_type' ? ((props.rule.spec as ItemModifierTypeSpec | undefined) ?? null) : null,
);
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2">
        На предмете:
        <strong>{{ spec.exclusive ? 'не больше одного модификатора этого типа' : 'можно несколько' }}</strong>
      </div>
    </v-card-text>
  </v-card>
</template>
