<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { magicPathSpecService } from '@/modules/Roleplay/Rule/Service/Instance/magicPathSpecService';

const props = defineProps<{
  name: string;
  code: string;
  codeDisabled?: boolean;
  description: string;
  mechanicId: number | null;
  keywordIds: number[];
  spec: RuleSpec | null;
  mechanicOptions: { title: string; value: number }[];
  keywordOptions: { title: string; value: number }[];
  rules: Rule[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: MagicPathSpec];
}>();

const inner = computed(() => magicPathSpecService.resolve(props.spec));

const includePathOptions = computed(() =>
  props.rules
    .filter((rule) => rule.type === 'magic_path' && rule.code !== props.code)
    .map((rule) => ({ title: rule.name, value: rule.code })),
);

const checkOptions = computed(() =>
  props.rules.filter((rule) => rule.type === 'check').map((rule) => ({ title: rule.name, value: rule.code })),
);

function patch(partial: Partial<MagicPathSpec>): void {
  emit('update:spec', { ...inner.value, ...partial });
}

function patchStudyCost(partial: Partial<NonNullable<MagicPathSpec['study_cost']>> | null): void {
  if (partial === null) {
    patch({ study_cost: null });

    return;
  }
  const current = inner.value.study_cost ?? { discount_fraction: 0.5, pair_base_cost: null };
  patch({ study_cost: { ...current, ...partial } });
}
</script>

<template>
  <RuleEditorBase
    :name="name"
    @update:name="(value) => emit('update:name', value)"
    :code="code"
    @update:code="(value) => emit('update:code', value)"
    :code-disabled="codeDisabled"
    :description="description"
    @update:description="(value) => emit('update:description', value)"
    :mechanic-id="mechanicId"
    @update:mechanic-id="(value) => emit('update:mechanicId', value)"
    :keyword-ids="keywordIds"
    @update:keyword-ids="(value) => emit('update:keywordIds', value)"
    :mechanic-options="mechanicOptions"
    :keyword-options="keywordOptions"
  >
    <template #spec>
      <v-autocomplete
        :model-value="inner.check_code"
        :items="checkOptions"
        item-title="title"
        item-value="value"
        label="Характеристика сотворения"
        density="compact"
        hide-details
        clearable
        class="mb-3"
        @update:model-value="patch({ check_code: $event || null })"
      />
      <v-autocomplete
        :model-value="inner.includes_path_codes"
        :items="includePathOptions"
        item-title="title"
        item-value="value"
        label="Включает пути"
        density="compact"
        hide-details
        multiple
        chips
        closable-chips
        class="mb-3"
        @update:model-value="patch({ includes_path_codes: $event ?? [] })"
      />
      <v-checkbox
        :model-value="inner.study_cost != null"
        label="Скидка стоимости изучения заклинаний"
        density="compact"
        hide-details
        @update:model-value="(value) => patchStudyCost(value ? {} : null)"
      />
      <div v-if="inner.study_cost" class="d-flex ga-2 mt-2">
        <v-text-field
          :model-value="inner.study_cost.discount_fraction"
          type="number"
          min="0"
          max="1"
          step="0.1"
          label="Доля скидки"
          density="compact"
          hide-details
          @update:model-value="(value) => patchStudyCost({ discount_fraction: Number(value) || 0 })"
        />
        <ClampedNumberField
          :model-value="inner.study_cost.pair_base_cost ?? 0"
          :min="0"
          label="Парная базовая цена (0 — нет)"
          density="compact"
          hide-details
          @update:model-value="(value) => patchStudyCost({ pair_base_cost: value > 0 ? value : null })"
        />
      </div>
    </template>
  </RuleEditorBase>
</template>
