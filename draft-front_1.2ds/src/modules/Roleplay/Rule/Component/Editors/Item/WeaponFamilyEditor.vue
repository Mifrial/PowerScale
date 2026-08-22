<script setup lang="ts">
import { ref } from 'vue';
import type { WeaponFamilySpec } from '@/modules/Roleplay/Rule/Dto/Item/WeaponFamilySpec';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';

const props = defineProps<{
  name: string;
  code: string;
  codeDisabled?: boolean;
  description: string;
  mechanicId: number | null;
  keywordIds: number[];
  spec: WeaponFamilySpec;
  mechanicOptions: { title: string; value: number }[];
  keywordOptions: { title: string; value: number }[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: WeaponFamilySpec];
}>();

/** Начальная лестница стоимостей при создании новой семьи (3 уровня). */
const DEFAULT_COSTS = [2, 4, 6];

const levels = ref<number[]>(props.spec.costs.length > 0 ? props.spec.costs.slice() : [...DEFAULT_COSTS]);

/** Добавить уровень в лестницу стоимостей. */
function addLevel() {
  levels.value.push(0);
}

function removeLevel(index: number) {
  if (levels.value.length <= 1) return;
  levels.value = levels.value.filter((_, i) => i !== index);
}

function patchCost(index: number, value: number) {
  levels.value[index] = Math.max(0, value);
}
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
      <div class="text-body-2 text-medium-emphasis mb-2">
        Лестница стоимостей уровней владения семьёй оружия (основы, понимание, специалист…). Количество уровней зависит
        от редакции правил.
      </div>

      <div v-for="(cost, index) in levels" :key="index" class="d-flex ga-2 align-center mb-1">
        <span class="text-body-2 text-high-emphasis flex-grow-0">Уровень {{ index + 1 }}</span>
        <ClampedNumberField
          :model-value="cost"
          @update:model-value="(v: number) => patchCost(index, v)"
          label="Стоимость"
          :min="0"
          density="compact"
          hide-details
          style="flex: 1 1 auto"
        />
        <v-btn
          icon
          size="small"
          color="error"
          variant="text"
          :disabled="levels.length <= 1"
          @click="removeLevel(index)"
        >
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </div>

      <v-btn variant="text" color="primary" size="small" @click="addLevel">
        <v-icon start>mdi-plus</v-icon>
        Добавить уровень
      </v-btn>
    </template>
  </RuleEditorBase>
</template>
