<script setup lang="ts">
import { computed } from 'vue';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef';
import type { ParameterValueKind } from '@/modules/Roleplay/Rule/Enum/Ability/ParameterValueKind';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { abilityParameterSpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilityParameterSpecService';

const props = defineProps<{
  modelValue: AbilityParameter;
  abilities: AbilityRef[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: AbilityParameter];
  remove: [];
}>();

const kindItems: { title: string; value: ParameterValueKind }[] = [
  { title: 'Скаляр', value: 'scalar' },
  { title: 'Размерное значение', value: 'dimensional' },
];

const resolutionItems = [
  { title: 'При покупке', value: 'purchase' },
  { title: 'При активации', value: 'activation' },
];

const mismatched = computed(() => abilityParameterSpecService.mismatchedBoundFields(props.modelValue));

function patch(next: AbilityParameter): void {
  emit('update:modelValue', next);
}

function setLinkedEnabled(enabled: boolean): void {
  patch(
    abilityParameterSpecService.withLinked(
      props.modelValue,
      enabled ? { ability_code: '', parameter_code: 'x', max_delta: 0 } : null,
    ),
  );
}
</script>

<template>
  <div class="mb-4">
    <div class="d-flex ga-2 align-center mb-2">
      <v-text-field
        :model-value="modelValue.code"
        label="Код"
        density="compact"
        hide-details
        @update:model-value="patch({ ...modelValue, code: String($event) })"
      />
      <v-text-field
        :model-value="modelValue.label"
        label="Подпись"
        density="compact"
        hide-details
        @update:model-value="patch({ ...modelValue, label: String($event) })"
      />
      <v-select
        :model-value="modelValue.kind"
        :items="kindItems"
        label="Тип значения"
        density="compact"
        hide-details
        @update:model-value="patch(abilityParameterSpecService.withKind(modelValue, $event as ParameterValueKind))"
      />
      <v-select
        :model-value="modelValue.resolution"
        :items="resolutionItems"
        label="Когда выбирается"
        density="compact"
        hide-details
        @update:model-value="patch({ ...modelValue, resolution: $event as AbilityParameter['resolution'] })"
      />
      <v-btn icon size="small" color="error" variant="text" @click="emit('remove')">
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>
    <v-textarea
      :model-value="modelValue.description ?? ''"
      label="Описание"
      density="compact"
      auto-grow
      rows="1"
      hide-details
      class="mb-2"
      @update:model-value="patch(abilityParameterSpecService.withDescription(modelValue, String($event ?? '')))"
    />
    <v-alert v-if="mismatched.length" type="warning" variant="tonal" density="compact" class="mb-2">
      Границы {{ mismatched.join(', ') }} не совпадают с kind «{{ modelValue.kind }}». Поля не подменяются сами.
      <v-btn
        class="ml-2"
        size="small"
        variant="tonal"
        @click="patch(abilityParameterSpecService.coerceBoundsToKind(modelValue))"
      >
        Привести к kind
      </v-btn>
    </v-alert>
    <div v-if="modelValue.kind === 'scalar' && mismatched.length === 0" class="d-flex ga-2 align-center">
      <ClampedNumberField
        :model-value="typeof modelValue.default === 'number' ? modelValue.default : null"
        label="По умолчанию"
        density="compact"
        hide-details
        @update:model-value="patch(abilityParameterSpecService.withScalarBound(modelValue, 'default', $event))"
      />
      <ClampedNumberField
        :model-value="typeof modelValue.min === 'number' ? modelValue.min : null"
        label="Min"
        nullable
        density="compact"
        hide-details
        @update:model-value="patch(abilityParameterSpecService.withScalarBound(modelValue, 'min', $event))"
      />
      <ClampedNumberField
        :model-value="typeof modelValue.max === 'number' ? modelValue.max : null"
        label="Max"
        nullable
        density="compact"
        hide-details
        @update:model-value="patch(abilityParameterSpecService.withScalarBound(modelValue, 'max', $event))"
      />
    </div>
    <div
      v-else-if="modelValue.kind === 'dimensional' && mismatched.length === 0"
      class="d-flex ga-2 align-center flex-wrap"
    >
      <DimensionalNumberInput
        :model-value="typeof modelValue.default === 'object' ? modelValue.default : { base: 0, size: 0 }"
        label="По умолчанию"
        :min="-20"
        @update:model-value="patch(abilityParameterSpecService.withDimensionalBound(modelValue, 'default', $event))"
      />
      <DimensionalNumberInput
        :model-value="typeof modelValue.min === 'object' ? modelValue.min : null"
        label="Min"
        :min="-20"
        @update:model-value="patch(abilityParameterSpecService.withDimensionalBound(modelValue, 'min', $event))"
      />
      <DimensionalNumberInput
        :model-value="typeof modelValue.max === 'object' ? modelValue.max : null"
        label="Max"
        :min="-20"
        @update:model-value="patch(abilityParameterSpecService.withDimensionalBound(modelValue, 'max', $event))"
      />
    </div>
    <v-checkbox
      :model-value="!!modelValue.linked"
      label="Связан с параметром другой способности"
      density="compact"
      hide-details
      class="mt-2"
      @update:model-value="setLinkedEnabled(!!$event)"
    />
    <div v-if="modelValue.linked" class="d-flex ga-2 mt-2">
      <v-autocomplete
        :model-value="modelValue.linked.ability_code"
        :items="abilities"
        item-title="name"
        item-value="code"
        label="Способность"
        density="compact"
        hide-details
        @update:model-value="
          patch(
            abilityParameterSpecService.withLinked(modelValue, {
              ...modelValue.linked,
              ability_code: String($event ?? ''),
            }),
          )
        "
      />
      <v-text-field
        :model-value="modelValue.linked.parameter_code"
        label="Код параметра"
        density="compact"
        hide-details
        @update:model-value="
          patch(
            abilityParameterSpecService.withLinked(modelValue, {
              ...modelValue.linked,
              parameter_code: String($event ?? ''),
            }),
          )
        "
      />
      <ClampedNumberField
        :model-value="modelValue.linked.max_delta"
        label="Макс. расхождение"
        density="compact"
        hide-details
        @update:model-value="
          patch(
            abilityParameterSpecService.withLinked(modelValue, {
              ...modelValue.linked,
              max_delta: $event ?? 0,
            }),
          )
        "
      />
    </div>
  </div>
</template>
