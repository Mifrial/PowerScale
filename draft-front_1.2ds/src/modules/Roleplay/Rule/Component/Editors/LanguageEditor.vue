<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { LanguageSpec } from '@/modules/Roleplay/Rule/Dto/LanguageSpec';
import type { LanguageRole } from '@/modules/Roleplay/Rule/Enum/LanguageRole';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import { languageSpecService } from '@/modules/Roleplay/Rule/Service/Instance/languageSpecService';
import { languageRelatednessService } from '@/modules/Roleplay/Rule/Service/Instance/languageRelatednessService';
import { LANGUAGE_ROLE_ITEMS } from '@/modules/Roleplay/Rule/Constant/Language/LANGUAGE_ROLE_ITEMS';

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
  'update:spec': [value: LanguageSpec];
}>();

const inner = computed(() => languageSpecService.resolve(props.spec));

const parentItems = computed(() =>
  languageRelatednessService.parentSelectItems(props.rules, props.code, inner.value.role),
);

const writingSystemItems = computed(() =>
  props.rules.filter((rule) => rule.type === 'script').map((rule) => ({ title: rule.name, value: rule.code })),
);

function patch(partial: Partial<LanguageSpec>): void {
  emit('update:spec', { ...inner.value, ...partial });
}

function patchRole(role: LanguageRole): void {
  const next: LanguageSpec = { ...inner.value, role };
  if (role === 'stock' && next.parent_code) {
    const parent = props.rules.find((rule) => rule.code === next.parent_code);
    if (parent && languageSpecService.resolve(parent.spec).role !== 'stock') {
      next.parent_code = null;
    }
  }
  emit('update:spec', next);
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
      <v-select
        :model-value="inner.role"
        :items="LANGUAGE_ROLE_ITEMS"
        item-title="title"
        item-value="value"
        label="Роль"
        density="compact"
        hide-details
        class="mb-3"
        @update:model-value="patchRole($event)"
      />
      <v-autocomplete
        :model-value="inner.parent_code"
        :items="parentItems"
        item-title="title"
        item-value="value"
        label="Родитель"
        density="compact"
        hide-details
        clearable
        @update:model-value="patch({ parent_code: $event || null })"
      />
      <v-autocomplete
        v-if="inner.role === 'language'"
        :model-value="inner.script_codes"
        :items="writingSystemItems"
        item-title="title"
        item-value="value"
        label="Письменности"
        hint="Карты письменности, которыми этот язык пишут. Не перечень букв."
        persistent-hint
        density="compact"
        multiple
        chips
        closable-chips
        class="mt-3"
        @update:model-value="patch({ script_codes: $event ?? [] })"
      />
    </template>
  </RuleEditorBase>
</template>
