<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';

const props = defineProps<{
  rule: Rule;
  keywords: Keyword[];
  rules?: Rule[];
}>();

const spec = computed<ItemModifierSpec | null>(() =>
  props.rule.type === 'item_modifier' ? ((props.rule.spec as ItemModifierSpec | undefined) ?? null) : null,
);

const keywordNameByCode = computed(() => new Map(props.keywords.map((k) => [k.code, k.name])));

function names(codes: string[]): string {
  return codes.map((code) => keywordNameByCode.value.get(code) ?? code).join(', ');
}

function opsLabel(ops: ItemModifierOp[]): string {
  return ops.map((op) => ruleViewLabelService.modifierOp(op, props.rules ?? [], props.keywords)).join('; ');
}

const priceScaleLabel = computed(() => {
  const scale = spec.value?.price_scale;
  if (!scale) return null;
  const typeName = (props.rules ?? []).find((rule) => rule.code === scale.type_code)?.name ?? scale.type_code;
  const only = scale.increasing_only ? ', только при росте цены' : '';

  return `×${scale.factor} к цене «${typeName}»${only}`;
});

const appliesLines = computed<string[]>(() => {
  const applies = spec.value?.applies;
  if (!applies) return [];
  const lines: string[] = [];
  if (applies.keyword_all.length) lines.push(`Требует все признаки: ${names(applies.keyword_all)}`);
  if (applies.keyword_any.length) lines.push(`Требует один из признаков: ${names(applies.keyword_any)}`);
  if (applies.keyword_none.length) lines.push(`Без признаков: ${names(applies.keyword_none)}`);

  return lines;
});

const priceLabel = computed<string | null>(() => {
  const price = spec.value?.price;
  if (!price) return null;
  if (price.factor === null && price.add_gm === null && price.add_gm_per_100g === null && price.min_final_gm === null) {
    return null;
  }
  const parts: string[] = [];
  if (price.factor !== null) parts.push(`×${price.factor}`);
  if (price.add_gm !== null) parts.push(`${price.add_gm >= 0 ? '+' : ''}${price.add_gm} гм`);
  if (price.add_gm_per_100g !== null) parts.push(`+${price.add_gm_per_100g} гм / 100 г`);
  if (price.min_final_gm !== null) parts.push(`минимум ${price.min_final_gm} гм`);

  return parts.join(' · ');
});

const typeLabel = computed(() => {
  const code = spec.value?.type_code;
  if (!code) return null;
  const typeRule = (props.rules ?? []).find((rule) => rule.type === 'item_modifier_type' && rule.code === code);

  return typeRule?.name ?? code;
});

const hasEffects = computed(() => (spec.value?.effects ?? []).some((effect) => effect.text.trim().length > 0));
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-4">
    <v-card-title>Модификатор предмета</v-card-title>
    <v-card-text>
      <div v-if="typeLabel" class="text-body-2 mb-2"><strong>Тип:</strong> {{ typeLabel }}</div>

      <div v-if="priceLabel" class="text-body-2 mb-2"><strong>Влияние на цену:</strong> {{ priceLabel }}</div>
      <div v-if="priceScaleLabel" class="text-body-2 mb-2"><strong>Множитель цены:</strong> {{ priceScaleLabel }}</div>

      <div v-if="appliesLines.length" class="text-body-2 mb-2">
        <strong>Применимо:</strong>
        <div v-for="line in appliesLines" :key="line" class="mt-1">{{ line }}</div>
      </div>

      <template v-if="hasEffects">
        <div class="text-body-2 mb-1"><strong>Эффекты:</strong></div>
        <div v-for="(effect, index) in spec.effects.filter((e) => e.text.trim())" :key="index" class="text-body-2 mb-1">
          <template v-if="effect.label">
            <strong>{{ effect.label }}:</strong>
          </template>
          {{ effect.text }}
          <span v-if="effect.ops?.length" class="text-medium-emphasis"> ({{ opsLabel(effect.ops) }})</span>
        </div>
      </template>

      <div
        v-if="
          !spec.applies ||
          spec.applies.keyword_all.length + spec.applies.keyword_any.length + spec.applies.keyword_none.length === 0
        "
        class="text-body-2 text-medium-emphasis mt-2"
      >
        Применимо к любому предмету.
      </div>
    </v-card-text>
  </v-card>
</template>
