<script setup lang="ts">
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import ZoneCostTableEditor from '@/modules/Roleplay/Rule/Component/Editors/Zone/ZoneCostTableEditor.vue';
import { zoneCostSpecService } from '@/modules/Roleplay/Rule/Service/Instance/zoneCostSpecService';

const props = defineProps<{
  modelValue: Extract<AbilityCost, { kind: 'parameter_sum_tables' }>;
  parameters: AbilityParameter[];
  parameterItems: { title: string; value: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Extract<AbilityCost, { kind: 'parameter_sum_tables' }>];
}>();

function patch(partial: Partial<Extract<AbilityCost, { kind: 'parameter_sum_tables' }>>): void {
  emit('update:modelValue', { ...props.modelValue, ...partial });
}

function addAxis(): void {
  const code = zoneCostSpecService.unusedPurchaseCode(props.parameters, props.modelValue.tables);
  patch({ tables: zoneCostSpecService.addSumTable(props.modelValue.tables, code) });
}
</script>

<template>
  <div class="mt-1">
    <ClampedNumberField
      :model-value="modelValue.max_level"
      label="Потолок суммы (уровень)"
      :min="1"
      density="compact"
      hide-details
      class="mb-2"
      style="max-width: 220px"
      @update:model-value="patch({ max_level: $event ?? 1 })"
    />
    <div v-for="code in Object.keys(modelValue.tables)" :key="code" class="pa-2 mb-2 rounded bg-accent">
      <div class="d-flex ga-2 align-center mb-2">
        <v-select
          :model-value="code"
          :items="parameterItems"
          label="Параметр"
          density="compact"
          hide-details
          style="min-width: 180px"
          @update:model-value="
            patch({ tables: zoneCostSpecService.renameSumTable(modelValue.tables, code, String($event ?? code)) })
          "
        />
        <v-btn
          icon
          size="small"
          variant="text"
          @click="patch({ tables: zoneCostSpecService.removeSumTable(modelValue.tables, code) })"
        >
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </div>
      <ZoneCostTableEditor
        :model-value="modelValue.tables[code] ?? {}"
        @update:model-value="patch({ tables: zoneCostSpecService.replaceSumTable(modelValue.tables, code, $event) })"
      />
    </div>
    <v-btn
      size="small"
      variant="text"
      :disabled="!zoneCostSpecService.unusedPurchaseCode(parameters, modelValue.tables)"
      @click="addAxis"
    >
      <v-icon start>mdi-plus</v-icon>
      Таблица параметра
    </v-btn>
  </div>
</template>
