<script setup lang="ts">
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
import { checkLaunchService } from '@/modules/Roleplay/Rule/Service/Instance/checkLaunchService';
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
  'update:spec': [value: CheckSpec];
}>();

function emptySpec(): CheckSpec {
  return {
    type: 'check',
    parent_check_code: null,
    characteristic_code: null,
    allow_characteristic_override: false,
    default_efficiency: null,
    difficulty_input: { kind: 'ask' },
    allowed_modes: 'both',
    attached_rule_codes: null,
  };
}

function fromSpec(value: RuleSpec | null): CheckSpec {
  if (value && typeof value === 'object' && 'type' in value && value.type === 'check') {
    return cloneData(value);
  }

  return emptySpec();
}

const draft = ref<CheckSpec>(fromSpec(props.spec));

watch(
  () => props.spec,
  (value) => {
    draft.value = fromSpec(value);
  },
);

const specToEmit = computed<CheckSpec>(() => cloneData(draft.value));
watch(specToEmit, (value) => emit('update:spec', value), { deep: true, immediate: true });

const parentOptions = computed(() =>
  props.rules
    .filter((rule) => rule.type === 'check' && rule.code !== props.code)
    .map((rule) => ({ title: rule.name, value: rule.code })),
);

const characteristicOptions = computed(() =>
  props.rules.filter((rule) => rule.type === 'characteristic').map((rule) => ({ title: rule.name, value: rule.code })),
);

const effectiveCharacteristicCode = computed({
  get: () =>
    draft.value.characteristic_code ??
    checkResolutionService.resolveCheckCharacteristicCode(props.code, props.rules, null),
  set: (code: string | null) => {
    draft.value.characteristic_code = code;
  },
});

const stateOptions = computed(() =>
  props.rules.filter((rule) => rule.type === 'state').map((rule) => ({ title: rule.name, value: rule.code })),
);

const attachableRuleOptions = computed(() =>
  props.rules
    .filter((rule) => checkResolutionService.isCheckAttachableRule(rule))
    .map((rule) => ({ title: rule.name, value: rule.code })),
);

const overrideAttachedRules = computed({
  get: () => draft.value.attached_rule_codes !== null && draft.value.attached_rule_codes !== undefined,
  set: (value: boolean) => {
    draft.value.attached_rule_codes = value ? [] : null;
  },
});

const difficultyKind = computed({
  get: () => draft.value.difficulty_input.kind,
  set: (kind: CheckSpec['difficulty_input']['kind']) => {
    if (kind === 'from_state') {
      const current = draft.value.difficulty_input;
      draft.value.difficulty_input = {
        kind: 'from_state',
        state_code: current.kind === 'from_state' ? current.state_code : '',
      };
    } else {
      draft.value.difficulty_input = { kind };
    }
  },
});

const stateCode = computed({
  get: () => (draft.value.difficulty_input.kind === 'from_state' ? draft.value.difficulty_input.state_code : ''),
  set: (code: string) => {
    draft.value.difficulty_input = { kind: 'from_state', state_code: code };
  },
});

const versusPreview = computed(() =>
  checkLaunchService.checkVersusLabel(
    {
      id: null,
      code: props.code,
      type: 'check',
      name: props.name,
      description: props.description,
      spaceId: 0,
      spec: draft.value,
      createdAt: '',
    },
    props.rules,
  ),
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
    :rules="rules"
  >
    <template #spec>
      <v-select
        v-model="draft.parent_check_code"
        :items="parentOptions"
        label="Родительская проверка"
        hint="Правила броска и гранты наследуются по цепочке."
        persistent-hint
        clearable
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-model="effectiveCharacteristicCode"
        :items="characteristicOptions"
        label="Характеристика пула"
        hint="Кто бросает. Для истощения — Сила воли. Пусто — взять у предка."
        persistent-hint
        clearable
        hide-details="auto"
        class="mb-3"
      />
      <v-switch
        v-model="draft.allow_characteristic_override"
        label="Разрешить подмену характеристики при запуске"
        color="primary"
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-model="draft.allowed_modes"
        :items="[
          { title: 'Одиночная и совместная', value: 'both' },
          { title: 'Только одиночная', value: 'solo' },
          { title: 'Только совместная', value: 'joint' },
        ]"
        label="Режимы запуска"
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-model="difficultyKind"
        :items="[
          { title: 'Сложность задаёт мастер (быстро — против 0)', value: 'ask' },
          { title: 'Сложность из состояния (против значения на листе)', value: 'from_state' },
          { title: 'Без своей сложности (чужие итоги / порядок)', value: 'none' },
        ]"
        label="Откуда сложность"
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-if="difficultyKind === 'from_state'"
        v-model="stateCode"
        :items="stateOptions"
        label="Состояние (против чего проверка)"
        hint="Истощение: код состояния exhaustion. Сложность берётся с листа участника."
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <v-alert v-if="versusPreview" type="info" variant="tonal" density="compact" class="mb-3">
        На запуске: {{ versusPreview }}
      </v-alert>
      <v-switch
        v-model="overrideAttachedRules"
        label="Свой набор правил броска"
        hint="Выкл — взять у предка. Вкл с пустым списком — без правил на броске."
        persistent-hint
        color="primary"
        hide-details="auto"
        class="mb-3"
      />
      <v-select
        v-if="overrideAttachedRules"
        v-model="draft.attached_rule_codes"
        :items="attachableRuleOptions"
        label="Правила на броске"
        hint="Карточки вроде «Правило 6 и 1», не коды механик. Их хендлеры вешаются на события броска."
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
