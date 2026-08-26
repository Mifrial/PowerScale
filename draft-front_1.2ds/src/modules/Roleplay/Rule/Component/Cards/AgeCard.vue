<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AgeSpec } from '@/modules/Roleplay/Rule/Dto/Age/AgeSpec';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed<AgeSpec | null>(() =>
  props.rule.type === 'age' ? ((props.rule.spec as AgeSpec | undefined) ?? null) : null,
);
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-subtitle-2 mb-2">Ступени возраста</div>
      <div v-for="(age, index) in spec.ages" :key="index" class="text-body-2 mb-2">
        <strong>{{ age.name }}</strong
        >: {{ age.ol }} ОЛ, лимит особенностей {{ age.featureLimit }}
        <div v-for="(effect, effectIndex) in age.effects" :key="effectIndex" class="text-medium-emphasis">
          {{ ruleViewLabelService.ruleName(rules, effect.characteristic_code) }}
          {{ effect.delta >= 0 ? '+' : '' }}{{ effect.delta
          }}<template v-if="effect.scope"> ({{ effect.scope }})</template>
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>
