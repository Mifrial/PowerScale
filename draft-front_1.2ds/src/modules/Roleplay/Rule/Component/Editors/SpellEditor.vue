<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { HitResolution } from '@/modules/Roleplay/Rule/Dto/Ability/HitResolution';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { HIT_RESOLUTION_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/HIT_RESOLUTION_OPTIONS';
import { SPELL_DURATION_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/SPELL_DURATION_OPTIONS';
import { SPELL_DURATION_LIMIT_UNIT_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/SPELL_DURATION_LIMIT_UNIT_OPTIONS';
import { SPELL_VALUE_MODE_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/SPELL_VALUE_MODE_OPTIONS';
import type { SpellDamage } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDamage';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const props = defineProps<{
  modelValue: SpellSpec | null;
  hitResolution?: HitResolution;
  parameters: AbilityParameter[];
  rules: Rule[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: SpellSpec];
  'update:hitResolution': [value: HitResolution];
}>();

const inner = ref<SpellSpec>(abilitySpecService.createEmptySpellSpec());

const parameterItems = computed(() =>
  props.parameters.map((parameter) => ({ title: parameter.label || parameter.code, value: parameter.code })),
);

const damageTypeItems = computed(() =>
  props.rules.filter((rule) => rule.type === 'damage_type').map((rule) => ({ title: rule.name, value: rule.code })),
);

const powerMode = computed(() =>
  abilitySpecService.isSpellValueParameter(inner.value.power) ? 'parameter' : 'dimensional',
);
const controlMode = computed(() =>
  abilitySpecService.isSpellValueParameter(inner.value.control) ? 'parameter' : 'dimensional',
);

const powerDimensional = computed<DimensionalNumberValue | null>(() =>
  abilitySpecService.isSpellValueParameter(inner.value.power) ? null : inner.value.power,
);
const controlDimensional = computed<DimensionalNumberValue | null>(() =>
  abilitySpecService.isSpellValueParameter(inner.value.control) ? null : inner.value.control,
);

const powerParameterCode = computed(() =>
  abilitySpecService.isSpellValueParameter(inner.value.power) ? inner.value.power.parameter_code : null,
);
const controlParameterCode = computed(() =>
  abilitySpecService.isSpellValueParameter(inner.value.control) ? inner.value.control.parameter_code : null,
);

const hitResolutionType = computed(() => props.hitResolution?.type ?? 'none');
const autoRating = computed(() => (props.hitResolution?.type === 'auto' ? props.hitResolution.rating : 1));

const durationPowerMode = computed(() => {
  const duration = inner.value.duration;
  if (duration.type !== 'sustained') return 'parameter';

  return abilitySpecService.isSpellValueParameter(duration.power) ? 'parameter' : 'dimensional';
});
const durationPowerDimensional = computed<DimensionalNumberValue | null>(() => {
  const duration = inner.value.duration;
  if (duration.type !== 'sustained' || abilitySpecService.isSpellValueParameter(duration.power)) return null;

  return duration.power;
});
const durationPowerParameterCode = computed(() => {
  const duration = inner.value.duration;
  if (duration.type !== 'sustained' || !abilitySpecService.isSpellValueParameter(duration.power)) return null;

  return duration.power.parameter_code;
});

const durationLimitValue = computed(() => {
  const duration = inner.value.duration;
  if (duration.type === 'instant' || !duration.limit) return 1;

  return typeof duration.limit.value === 'number' ? duration.limit.value : duration.limit.value.base;
});

function emitSpell(spell: SpellSpec) {
  inner.value = spell;
}

function setValueMode(field: 'power' | 'control', mode: string | null) {
  const empty = abilitySpecService.createEmptySpellSpec();
  if (mode === 'parameter') {
    const code = props.parameters[0]?.code ?? 'x';
    emitSpell(abilitySpecService.withSpellField(inner.value, field, abilitySpecService.setSpellValueParameter(code)));

    return;
  }
  emitSpell(abilitySpecService.withSpellField(inner.value, field, empty[field]));
}

function setDimensional(field: 'power' | 'control', value: DimensionalNumberValue | null) {
  const empty = abilitySpecService.createEmptySpellSpec();
  const fallback = empty[field] as DimensionalNumberValue;
  emitSpell(
    abilitySpecService.withSpellField(inner.value, field, abilitySpecService.setSpellValueDimensional(fallback, value)),
  );
}

function setParameterCode(field: 'power' | 'control', code: string | null) {
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      field,
      abilitySpecService.setSpellValueParameter(code ?? props.parameters[0]?.code ?? 'x'),
    ),
  );
}

function setDurationPowerMode(mode: string | null) {
  const duration = inner.value.duration;
  if (duration.type !== 'sustained') return;
  if (mode === 'parameter') {
    emitSpell(
      abilitySpecService.withSpellField(
        inner.value,
        'duration',
        abilitySpecService.setSpellDurationPower(
          duration,
          abilitySpecService.setSpellValueParameter(props.parameters[0]?.code ?? 'x'),
        ),
      ),
    );

    return;
  }
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      'duration',
      abilitySpecService.setSpellDurationPower(duration, { base: 3, size: 0 }),
    ),
  );
}

function setDurationPowerDimensional(value: DimensionalNumberValue | null) {
  const duration = inner.value.duration;
  if (duration.type !== 'sustained') return;
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      'duration',
      abilitySpecService.setSpellDurationPower(
        duration,
        abilitySpecService.setSpellValueDimensional({ base: 3, size: 0 }, value),
      ),
    ),
  );
}

function setDurationPowerParameter(code: string | null) {
  const duration = inner.value.duration;
  if (duration.type !== 'sustained') return;
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      'duration',
      abilitySpecService.setSpellDurationPower(
        duration,
        abilitySpecService.setSpellValueParameter(code ?? props.parameters[0]?.code ?? 'x'),
      ),
    ),
  );
}

function updateDurationType(type: string | null) {
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      'duration',
      abilitySpecService.createEmptySpellDuration((type as SpellDuration['type']) ?? 'instant'),
    ),
  );
}

function toggleDurationLimit(checked: boolean) {
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      'duration',
      abilitySpecService.toggleSpellDurationLimit(inner.value.duration, checked),
    ),
  );
}

function patchDurationLimit(key: 'value' | 'unit', value: unknown) {
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      'duration',
      abilitySpecService.patchSpellDurationLimit(inner.value.duration, key, value),
    ),
  );
}

function updateHitResolutionType(type: string | null) {
  emit(
    'update:hitResolution',
    abilitySpecService.createHitResolution((type as HitResolution['type']) ?? 'none', autoRating.value),
  );
}

function updateAutoRating(rating: number) {
  emit('update:hitResolution', abilitySpecService.createHitResolution('auto', rating));
}

function setRefreshableCost(value: number) {
  emitSpell(
    abilitySpecService.withSpellField(
      inner.value,
      'duration',
      abilitySpecService.setRefreshableActionCost(inner.value.duration, value),
    ),
  );
}

function setHasDamage(enabled: boolean) {
  emitSpell(
    abilitySpecService.setSpellDamage(inner.value, enabled ? abilitySpecService.createEmptySpellDamage() : null),
  );
}

function patchDamage(patch: Partial<SpellDamage>) {
  const current = inner.value.damage ?? abilitySpecService.createEmptySpellDamage();
  emitSpell(abilitySpecService.setSpellDamage(inner.value, { ...current, ...patch }));
}

function patchDamageStep(index: number, patch: Partial<SpellDamage['power_modify_steps'][number]>) {
  const current = inner.value.damage ?? abilitySpecService.createEmptySpellDamage();
  const steps = current.power_modify_steps.map((step, stepIndex) =>
    stepIndex === index ? { ...step, ...patch } : step,
  );
  patchDamage({ power_modify_steps: steps });
}

function addDamageStep() {
  const current = inner.value.damage ?? abilitySpecService.createEmptySpellDamage();
  patchDamage({
    power_modify_steps: [...current.power_modify_steps, { min_experience: 0, modify: 3 }],
  });
}

function removeDamageStep(index: number) {
  const current = inner.value.damage;
  if (!current || current.power_modify_steps.length <= 1) return;
  patchDamage({ power_modify_steps: current.power_modify_steps.filter((_, stepIndex) => stepIndex !== index) });
}

function setHasFalloff(enabled: boolean) {
  emitSpell(abilitySpecService.setSpellDamageFalloff(inner.value, enabled));
}

function patchFalloff(patch: Partial<NonNullable<SpellDamage['falloff']>>) {
  emitSpell(abilitySpecService.patchSpellDamageFalloff(inner.value, patch));
}

watch(
  inner,
  (value) => {
    emit('update:modelValue', cloneData(value));
  },
  { deep: true },
);

onMounted(() => {
  inner.value = props.modelValue ? cloneData(props.modelValue) : abilitySpecService.createEmptySpellSpec();
});
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Заклинание — волшебное действие. Мощь, контроль и длительность эффекта. Сложность сотворения считается при
      запуске, не хранится в карточке.
    </div>

    <div class="text-subtitle-2 mb-1">Мощь</div>
    <v-radio-group
      :model-value="powerMode"
      @update:model-value="(v) => setValueMode('power', v)"
      density="compact"
      hide-details
      inline
    >
      <v-radio
        v-for="option in SPELL_VALUE_MODE_OPTIONS"
        :key="option.value"
        :label="option.title"
        :value="option.value"
      />
    </v-radio-group>
    <DimensionalNumberInput
      v-if="powerMode === 'dimensional'"
      :model-value="powerDimensional"
      @update:model-value="setDimensional('power', $event)"
      label="Мощь"
      :min="3"
      :max="5"
      class="mt-2"
    />
    <v-select
      v-else
      :model-value="powerParameterCode"
      @update:model-value="setParameterCode('power', $event)"
      :items="parameterItems"
      item-title="title"
      item-value="value"
      label="Параметр мощи"
      density="compact"
      hide-details
      class="mt-2"
    />

    <div class="text-subtitle-2 mt-4 mb-1">Контроль</div>
    <v-radio-group
      :model-value="controlMode"
      @update:model-value="(v) => setValueMode('control', v)"
      density="compact"
      hide-details
      inline
    >
      <v-radio
        v-for="option in SPELL_VALUE_MODE_OPTIONS"
        :key="option.value"
        :label="option.title"
        :value="option.value"
      />
    </v-radio-group>
    <DimensionalNumberInput
      v-if="controlMode === 'dimensional'"
      :model-value="controlDimensional"
      @update:model-value="setDimensional('control', $event)"
      label="Контроль"
      :min="3"
      :max="5"
      class="mt-2"
    />
    <v-select
      v-else
      :model-value="controlParameterCode"
      @update:model-value="setParameterCode('control', $event)"
      :items="parameterItems"
      item-title="title"
      item-value="value"
      label="Параметр контроля"
      density="compact"
      hide-details
      class="mt-2"
    />

    <div class="text-subtitle-2 mt-4 mb-1">Попадание</div>
    <v-select
      :model-value="hitResolutionType"
      @update:model-value="updateHitResolutionType"
      :items="HIT_RESOLUTION_OPTIONS"
      item-title="title"
      item-value="value"
      label="Доставка попадания"
      density="compact"
      hide-details
    />
    <ClampedNumberField
      v-if="hitResolutionType === 'auto'"
      :model-value="autoRating"
      @update:model-value="updateAutoRating"
      label="РУ атаки"
      :min="1"
      density="compact"
      hide-details
      class="mt-2"
    />

    <div class="mt-4">
      <div class="text-subtitle-2 mb-1">Продолжительность эффекта</div>
      <v-radio-group
        :model-value="inner.duration?.type ?? 'instant'"
        @update:model-value="updateDurationType"
        density="compact"
        hide-details
      >
        <v-radio
          v-for="option in SPELL_DURATION_OPTIONS"
          :key="option.value"
          :label="option.title"
          :value="option.value"
        />
      </v-radio-group>

      <template v-if="inner.duration && inner.duration.type !== 'instant'">
        <ClampedNumberField
          v-if="inner.duration.type === 'refreshable'"
          :model-value="
            typeof inner.duration.action_cost === 'number'
              ? inner.duration.action_cost
              : (inner.duration.action_cost?.base ?? 0)
          "
          @update:model-value="setRefreshableCost"
          label="ОД на обновление"
          :min="0"
          density="compact"
          hide-details
          class="mt-2"
        />
        <template v-if="inner.duration.type === 'sustained'">
          <div class="text-body-2 text-medium-emphasis mt-2">
            Мощь поддержания. В первый ход равна мощи сотворения; дальше её можно менять в начале хода.
          </div>
          <v-radio-group
            :model-value="durationPowerMode"
            @update:model-value="setDurationPowerMode"
            density="compact"
            hide-details
            inline
            class="mt-1"
          >
            <v-radio
              v-for="option in SPELL_VALUE_MODE_OPTIONS"
              :key="option.value"
              :label="option.title"
              :value="option.value"
            />
          </v-radio-group>
          <DimensionalNumberInput
            v-if="durationPowerMode === 'dimensional'"
            :model-value="durationPowerDimensional"
            @update:model-value="setDurationPowerDimensional"
            label="Мощь поддержания"
            :min="3"
            :max="5"
            class="mt-2"
          />
          <v-select
            v-else
            :model-value="durationPowerParameterCode"
            @update:model-value="setDurationPowerParameter"
            :items="parameterItems"
            item-title="title"
            item-value="value"
            label="Параметр мощи поддержания"
            density="compact"
            hide-details
            class="mt-2"
          />
        </template>
        <div class="mt-2">
          <v-checkbox
            :model-value="!!inner.duration.limit"
            @update:model-value="(v) => toggleDurationLimit(!!v)"
            label="Предел длительности"
            density="compact"
            hide-details
          />
          <div v-if="inner.duration.limit" class="d-flex gap-2 flex-wrap mt-1">
            <ClampedNumberField
              :model-value="durationLimitValue"
              @update:model-value="(v) => patchDurationLimit('value', v)"
              label="Значение"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 120px"
            />
            <v-select
              :model-value="inner.duration.limit.unit"
              @update:model-value="(v) => patchDurationLimit('unit', v)"
              :items="SPELL_DURATION_LIMIT_UNIT_OPTIONS"
              item-title="title"
              item-value="value"
              label="Единица"
              density="compact"
              hide-details
              style="min-width: 160px"
            />
          </div>
        </div>
      </template>
    </div>

    <div class="mt-4">
      <div class="text-subtitle-2 mb-1">Урон</div>
      <v-checkbox
        :model-value="!!inner.damage"
        @update:model-value="(v) => setHasDamage(!!v)"
        label="Наносит типированный урон"
        density="compact"
        hide-details
      />
      <template v-if="inner.damage">
        <v-autocomplete
          :model-value="inner.damage.damage_type_code"
          @update:model-value="(v) => patchDamage({ damage_type_code: v ?? '' })"
          :items="damageTypeItems"
          item-title="title"
          item-value="value"
          label="Тип урона"
          density="compact"
          hide-details
          class="mt-2"
        />
        <v-text-field
          :model-value="inner.damage.experience_keyword_code"
          @update:model-value="(v) => patchDamage({ experience_keyword_code: v ?? '' })"
          label="Keyword опыта"
          density="compact"
          hide-details
          class="mt-2"
        />
        <div v-for="(step, index) in inner.damage.power_modify_steps" :key="index" class="d-flex gap-2 flex-wrap mt-2">
          <ClampedNumberField
            :model-value="step.min_experience"
            @update:model-value="(v) => patchDamageStep(index, { min_experience: v })"
            label="Опыт от"
            :min="0"
            density="compact"
            hide-details
            style="min-width: 120px"
          />
          <ClampedNumberField
            :model-value="step.modify"
            @update:model-value="(v) => patchDamageStep(index, { modify: v })"
            label="Сдвиг мощи"
            density="compact"
            hide-details
            style="min-width: 120px"
          />
          <v-btn
            v-if="inner.damage.power_modify_steps.length > 1"
            icon="mdi-close"
            variant="text"
            size="small"
            @click="removeDamageStep(index)"
          />
        </div>
        <v-btn class="mt-2" size="small" variant="text" @click="addDamageStep">Добавить ступень</v-btn>
        <v-checkbox
          :model-value="!!inner.damage.falloff"
          @update:model-value="(v) => setHasFalloff(!!v)"
          label="Снижение урона с дистанцией"
          density="compact"
          hide-details
          class="mt-2"
        />
        <div v-if="inner.damage.falloff" class="d-flex gap-2 flex-wrap mt-2">
          <ClampedNumberField
            :model-value="inner.damage.falloff.free_ipari"
            @update:model-value="(v) => patchFalloff({ free_ipari: v })"
            label="Бесплатных ипари"
            :min="0"
            density="compact"
            hide-details
            style="min-width: 140px"
          />
          <ClampedNumberField
            :model-value="inner.damage.falloff.size_per_extra_ipari"
            @update:model-value="(v) => patchFalloff({ size_per_extra_ipari: v })"
            label="Размеров за ипари"
            :min="1"
            density="compact"
            hide-details
            style="min-width: 140px"
          />
          <DimensionalNumberInput
            :model-value="inner.damage.falloff.min"
            @update:model-value="(v) => patchFalloff({ min: v ?? { base: 3, size: -1 } })"
            label="Ниже порога урона нет"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
