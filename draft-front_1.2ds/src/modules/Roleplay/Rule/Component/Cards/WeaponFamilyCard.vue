<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { WeaponFamilySpec } from '@/modules/Roleplay/Rule/Dto/Item/WeaponFamilySpec';

const props = defineProps<{
  rule: Rule;
}>();

const spec = computed<WeaponFamilySpec | null>(() =>
  props.rule.type === 'weapon_family' ? ((props.rule.spec as WeaponFamilySpec | undefined) ?? null) : null,
);
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2">
        Стоимость уровней владения: <strong>{{ spec.costs.join(', ') }}</strong>
      </div>
    </v-card-text>
  </v-card>
</template>
