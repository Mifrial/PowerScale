<script setup lang="ts">
import { computed, watch } from 'vue';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { SenseSpec } from '@/modules/Roleplay/Rule/Dto/SenseSpec';
import type { LanguageSpec } from '@/modules/Roleplay/Rule/Dto/LanguageSpec';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';

const props = defineProps<{
  name: string;
  code: string;
  codeDisabled?: boolean;
  description: string;
  mechanicId: number | null;
  keywordIds: number[];
  spec: RuleSpec | null;
  specType: 'sense' | 'language';
  mechanicOptions: { title: string; value: number }[];
  keywordOptions: { title: string; value: number }[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: SenseSpec | LanguageSpec];
}>();

const specToEmit = computed<SenseSpec | LanguageSpec>(() => ({ type: props.specType }));
watch(specToEmit, (value) => emit('update:spec', value), { immediate: true });
</script>

<template>
  <RuleEditorBase
    :name="name"
    @update:name="(v) => emit('update:name', v)"
    :code="code"
    @update:code="(v) => emit('update:code', v)"
    :code-disabled="codeDisabled"
    :description="description"
    @update:description="(v) => emit('update:description', v)"
    :mechanic-id="mechanicId"
    @update:mechanic-id="(v) => emit('update:mechanicId', v)"
    :keyword-ids="keywordIds"
    @update:keyword-ids="(v) => emit('update:keywordIds', v)"
    :mechanic-options="mechanicOptions"
    :keyword-options="keywordOptions"
  >
    <template #spec>
      <div class="text-body-2 text-medium-emphasis mt-2">
        {{
          specType === 'sense'
            ? 'Чувство — метка для даров «модификатор чувства». Значение на персонаже складывается из даров.'
            : 'Язык — словарная статья домена навыка «Владение языком». Спеки нет.'
        }}
      </div>
    </template>
  </RuleEditorBase>
</template>
