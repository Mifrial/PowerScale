<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed(() =>
  props.rule.type === 'magic_path' ? ((props.rule.spec as MagicPathSpec | undefined) ?? null) : null,
);

function includeNames(codes: string[]): string {
  return codes.map((code) => props.rules.find((rule) => rule.code === code)?.name ?? code).join(', ');
}

function checkName(code: string | null): string {
  if (!code) return '—';

  return props.rules.find((rule) => rule.code === code)?.name ?? code;
}
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-subtitle-2 mb-1">Путь волшебства</div>
      <div class="text-body-2">Проверка: {{ checkName(spec.check_code) }}</div>
      <div v-if="spec.includes_path_codes?.length" class="text-body-2">
        Включает: {{ includeNames(spec.includes_path_codes) }}
      </div>
      <div v-if="spec.study_cost" class="text-body-2">
        Скидка изучения: {{ spec.study_cost.discount_fraction }} · парная цена
        {{ spec.study_cost.pair_base_cost ?? 'нет' }}
      </div>
    </v-card-text>
  </v-card>
</template>
