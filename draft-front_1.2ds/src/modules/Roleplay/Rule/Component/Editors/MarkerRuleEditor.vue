<script setup lang="ts">
import { computed, watch } from 'vue';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { SenseSpec } from '@/modules/Roleplay/Rule/Dto/SenseSpec';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';

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
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: SenseSpec];
}>();

const specToEmit = computed<SenseSpec>(() => {
  if (props.spec && 'type' in props.spec && props.spec.type === 'sense') return props.spec;

  return { type: 'sense', status: 'precise', radius: { base: 30, size: 0 } };
});
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
        Чувство — метка для даров «модификатор чувства». Значение на персонаже складывается из даров.
      </div>
    </template>
  </RuleEditorBase>
</template>
