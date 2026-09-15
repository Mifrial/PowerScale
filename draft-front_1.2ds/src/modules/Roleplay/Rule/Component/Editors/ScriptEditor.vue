<script setup lang="ts">
import { computed } from 'vue';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { ScriptSpec } from '@/modules/Roleplay/Rule/Dto/ScriptSpec';
import type { ScriptKind } from '@/modules/Roleplay/Rule/Enum/ScriptKind';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import { scriptSpecService } from '@/modules/Roleplay/Rule/Service/Instance/scriptSpecService';
import { SCRIPT_KIND_ITEMS } from '@/modules/Roleplay/Rule/Constant/Script/SCRIPT_KIND_ITEMS';

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
  'update:spec': [value: ScriptSpec];
}>();

const inner = computed(() => scriptSpecService.resolve(props.spec));

function patchKind(kind: ScriptKind): void {
  emit('update:spec', { ...inner.value, kind });
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
        :model-value="inner.kind"
        :items="SCRIPT_KIND_ITEMS"
        item-title="title"
        item-value="value"
        label="Вид (алфавит или иероглифы)"
        density="compact"
        hide-details
        @update:model-value="patchKind($event)"
      />
    </template>
  </RuleEditorBase>
</template>
