<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { EthnicitySpec } from '@/modules/Roleplay/Rule/Dto/EthnicitySpec';
import type { EthnicityRole } from '@/modules/Roleplay/Rule/Enum/EthnicityRole';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import { ethnicitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicitySpecService';
import { ethnicityTreeService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicityTreeService';
import { languageRelatednessService } from '@/modules/Roleplay/Rule/Service/Instance/languageRelatednessService';
import { languageSpecService } from '@/modules/Roleplay/Rule/Service/Instance/languageSpecService';
import { ETHNICITY_ROLE_ITEMS } from '@/modules/Roleplay/Rule/Constant/Ethnicity/ETHNICITY_ROLE_ITEMS';

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
  'update:spec': [value: EthnicitySpec];
}>();

const inner = computed(() => ethnicitySpecService.resolve(props.spec));

const parentItems = computed(() => ethnicityTreeService.parentSelectItems(props.rules, props.code, inner.value.role));

const languageItems = computed(() => languageRelatednessService.speakableOptions(props.rules));

const languageNameByCode = computed(() => new Map(languageItems.value.map((item) => [item.code, item.name])));

function scriptItemsOf(languageCode: string): { title: string; value: string }[] {
  const language = props.rules.find((rule) => rule.type === 'language' && rule.code === languageCode);
  const codes = language ? languageSpecService.resolve(language.spec).script_codes : [];

  return codes.map((code) => {
    const script = props.rules.find((rule) => rule.type === 'script' && rule.code === code);

    return { title: script?.name ?? code, value: code };
  });
}

const scriptsByLanguage = computed(() => {
  const map = new Map<string, { title: string; value: string }[]>();
  for (const usage of inner.value.usages) {
    map.set(usage.language_code, scriptItemsOf(usage.language_code));
  }

  return map;
});

function patch(partial: Partial<EthnicitySpec>): void {
  emit('update:spec', ethnicitySpecService.resolve({ ...inner.value, ...partial }));
}

function patchRole(role: EthnicityRole): void {
  const next: EthnicitySpec = { ...inner.value, role };
  if (role === 'stock') {
    next.language_codes = [];
    next.usages = [];
    next.race_codes = [];
    if (next.parent_code) {
      const parent = props.rules.find((rule) => rule.code === next.parent_code);
      if (parent && ethnicitySpecService.resolve(parent.spec).role !== 'stock') {
        next.parent_code = null;
      }
    }
  }
  emit('update:spec', ethnicitySpecService.resolve(next));
}

function patchLanguages(codes: string[]): void {
  const previous = new Map(inner.value.usages.map((usage) => [usage.language_code, usage.script_codes]));
  const usages = codes.map((language_code) => ({
    language_code,
    script_codes: previous.get(language_code) ?? scriptItemsOf(language_code).map((item) => item.value),
  }));
  patch({ language_codes: codes, usages });
}

function patchUsageScripts(languageCode: string, scriptCodes: string[]): void {
  const usages = inner.value.usages.map((usage) =>
    usage.language_code === languageCode ? { ...usage, script_codes: scriptCodes } : usage,
  );
  patch({ usages });
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
        :items="ETHNICITY_ROLE_ITEMS"
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
      <template v-if="inner.role === 'people'">
        <v-autocomplete
          :model-value="inner.language_codes"
          :items="languageItems"
          item-title="name"
          item-value="code"
          label="Языки"
          hint="Родной и прочие живые. Не стоки дерева языков."
          persistent-hint
          density="compact"
          multiple
          chips
          closable-chips
          class="mt-3"
          @update:model-value="patchLanguages($event ?? [])"
        />
        <div v-for="usage in inner.usages" :key="usage.language_code" class="mt-3">
          <v-autocomplete
            :model-value="usage.script_codes"
            :items="scriptsByLanguage.get(usage.language_code) ?? []"
            item-title="title"
            item-value="value"
            :label="'Письмо: ' + (languageNameByCode.get(usage.language_code) ?? usage.language_code)"
            density="compact"
            multiple
            chips
            closable-chips
            hide-details
            @update:model-value="patchUsageScripts(usage.language_code, $event ?? [])"
          />
        </div>
      </template>
    </template>
  </RuleEditorBase>
</template>
