<script setup lang="ts">
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { ItemModifierTypeSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierTypeSpec';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import { computed, ref, watch } from 'vue';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

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
  'update:spec': [value: ItemModifierTypeSpec];
}>();

function emptySpec(): ItemModifierTypeSpec {
  return { exclusive: true };
}

const draft = ref<ItemModifierTypeSpec>(props.spec ? (cloneData(props.spec) as ItemModifierTypeSpec) : emptySpec());

watch(
  () => props.spec,
  (value) => {
    draft.value = value ? (cloneData(value) as ItemModifierTypeSpec) : emptySpec();
  },
);

const specToEmit = computed<ItemModifierTypeSpec>(() => cloneData(draft.value));

watch(specToEmit, (value) => emit('update:spec', value), { deep: true });
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
      <v-switch
        v-model="draft.exclusive"
        label="Только один модификатор этого типа на предмете"
        hint="Выкл. — на предмете можно сочетать несколько модификаторов типа."
        persistent-hint
        color="primary"
        hide-details="auto"
      />
    </template>
  </RuleEditorBase>
</template>
