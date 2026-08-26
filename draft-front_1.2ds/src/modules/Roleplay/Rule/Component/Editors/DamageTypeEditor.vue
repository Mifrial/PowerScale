<script setup lang="ts">
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { DamageTypeSpec } from '@/modules/Roleplay/Rule/Dto/Damage/DamageTypeSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
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
  rules: Rule[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: DamageTypeSpec];
}>();

const draft = ref<DamageTypeSpec>(cloneData(damageTypeSpecService.fromRuleSpec(props.spec, props.code)));

const specToEmit = computed<DamageTypeSpec>(() => cloneData(draft.value));
watch(specToEmit, (value) => emit('update:spec', value), { deep: true, immediate: true });

const attachableOptions = computed(() =>
  props.rules
    .filter((rule) => damageTypeSpecService.isDamageTypeAttachableRule(rule))
    .map((rule) => ({ title: rule.name, value: rule.code })),
);
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
      <div class="text-subtitle-2 mb-2">Склонения в тексте правил</div>
      <v-text-field
        v-model="draft.forms.genitive"
        label="Родительный (сколько урона)"
        hint="«5 рубящего урона», «3 огня»"
        persistent-hint
        :rules="[(v) => !!v?.trim() || 'Обязательное поле']"
        hide-details="auto"
        class="mb-3"
      />
      <v-text-field
        v-model="draft.forms.dative"
        label="Дательный (сопротивление чему)"
        hint="«сопротивления рубящему урону», «огню»"
        persistent-hint
        :rules="[(v) => !!v?.trim() || 'Обязательное поле']"
        hide-details="auto"
        class="mb-3"
      />
      <v-checkbox
        v-model="draft.defense_ignored"
        label="Защита не помогает"
        hint="Линии защиты не добавляются к сопротивлению этому типу урона."
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-model="draft.attached_rule_codes"
        :items="attachableOptions"
        label="Механики типа урона"
        hint="Карточки с механикой: хуки на увечье, применение урона, оплату РУ."
        persistent-hint
        multiple
        chips
        closable-chips
        hide-details="auto"
        class="mb-3"
      />
    </template>
  </RuleEditorBase>
</template>
