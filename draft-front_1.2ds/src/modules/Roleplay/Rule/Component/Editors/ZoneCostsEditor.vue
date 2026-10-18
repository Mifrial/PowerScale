<script setup lang="ts">
import { computed } from 'vue';
import type { ZoneId } from '@/modules/Roleplay/Rule/Dto/Ability/ZoneId';
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { AbilityCostKind } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityCostKind';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import { ABILITY_COST_KIND_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_COST_KIND_OPTIONS';
import { zoneCostSpecService } from '@/modules/Roleplay/Rule/Service/Instance/zoneCostSpecService';
import ZoneParameterCostFields from '@/modules/Roleplay/Rule/Component/Editors/Zone/ZoneParameterCostFields.vue';
import ZoneParameterTableCostFields from '@/modules/Roleplay/Rule/Component/Editors/Zone/ZoneParameterTableCostFields.vue';
import ZoneParameterSumTablesCostFields from '@/modules/Roleplay/Rule/Component/Editors/Zone/ZoneParameterSumTablesCostFields.vue';

const props = defineProps<{
  modelValue: Partial<Record<ZoneId, AbilityCost>>;
  zoneOptions: { label: string; value: string }[];
  parameters?: AbilityParameter[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Partial<Record<ZoneId, AbilityCost>>];
}>();

const { inner } = useVModelSync<Partial<Record<ZoneId, AbilityCost>>>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: false,
});

const parameterItems = computed(() => zoneCostSpecService.parameterItems(props.parameters ?? []));
const kindOptions = ABILITY_COST_KIND_OPTIONS;

function hasZone(zone: ZoneId): boolean {
  return !!inner.value[zone];
}

function zoneCost(zone: ZoneId): AbilityCost {
  return inner.value[zone] ?? zoneCostSpecService.createEmpty('array');
}

function progressionZone(zone: ZoneId) {
  const cost = inner.value[zone];

  return cost && cost.kind === 'progression' ? cost : null;
}

function parameterZone(zone: ZoneId) {
  const cost = inner.value[zone];

  return cost && cost.kind === 'parameter' ? cost : null;
}

function parameterTableZone(zone: ZoneId) {
  const cost = inner.value[zone];

  return cost && cost.kind === 'parameter_table' ? cost : null;
}

function parameterSumZone(zone: ZoneId) {
  const cost = inner.value[zone];

  return cost && cost.kind === 'parameter_sum_tables' ? cost : null;
}

function toggleZone(zone: ZoneId, checked: boolean) {
  const zones = { ...inner.value };
  if (checked) {
    zones[zone] = zoneCostSpecService.createEmpty('array');
  } else {
    delete zones[zone];
  }
  inner.value = zones;
}

function setKind(zone: ZoneId, kind: AbilityCostKind) {
  inner.value = {
    ...inner.value,
    [zone]: zoneCostSpecService.replaceKind(inner.value[zone], kind),
  };
}

function replaceCost(zone: ZoneId, cost: AbilityCost) {
  inner.value = { ...inner.value, [zone]: zoneCostSpecService.coerceCost(cost) };
}

function patchProgression(zone: ZoneId, key: 'max_level' | 'base_cost' | 'step', value: unknown) {
  const current = progressionZone(zone);
  if (!current) return;
  replaceCost(zone, { ...current, [key]: Number(value) || 0 });
}

function arrayCosts(zone: ZoneId): number[] {
  const cost = inner.value[zone];

  return cost && cost.kind === 'array' ? cost.levels_cost : [];
}

function updateArrayCost(zone: ZoneId, index: number, value: number) {
  const cost = inner.value[zone];
  if (!cost || cost.kind !== 'array') return;
  const levels_cost = [...cost.levels_cost];
  levels_cost[index] = Number(value) || 0;
  replaceCost(zone, { kind: 'array', levels_cost });
}

function addArrayCost(zone: ZoneId) {
  const cost = inner.value[zone];
  if (!cost || cost.kind !== 'array') return;
  replaceCost(zone, { kind: 'array', levels_cost: [...cost.levels_cost, 0] });
}

function removeArrayCost(zone: ZoneId) {
  const cost = inner.value[zone];
  if (!cost || cost.kind !== 'array' || cost.levels_cost.length <= 1) return;
  replaceCost(zone, { kind: 'array', levels_cost: cost.levels_cost.slice(0, -1) });
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap ga-4">
      <v-checkbox
        v-for="zone in zoneOptions"
        :key="zone.value"
        :model-value="hasZone(zone.value)"
        @update:model-value="(v: boolean | null) => toggleZone(zone.value, !!v)"
        :label="zone.label"
        density="compact"
        hide-details
      />
    </div>

    <div v-for="zone in zoneOptions" :key="`zone-editor-${zone.value}`" class="mt-3">
      <template v-if="hasZone(zone.value)">
        <div class="d-flex ga-2 align-center mb-2 flex-wrap">
          <div class="text-subtitle-2">{{ zone.label }}</div>
          <v-select
            :model-value="zoneCost(zone.value).kind"
            :items="kindOptions"
            item-title="title"
            item-value="value"
            label="Вид стоимости"
            density="compact"
            hide-details
            style="max-width: 280px"
            @update:model-value="(v) => setKind(zone.value, v as AbilityCostKind)"
          />
        </div>

        <template v-if="zoneCost(zone.value).kind === 'array'">
          <div class="text-body-2 text-medium-emphasis mb-1">
            Стоимости по уровням (отрицательная = даёт очки). Длина = макс. уровень.
          </div>
          <div class="d-flex ga-2 align-center flex-wrap">
            <ClampedNumberField
              v-for="(cost, levelIndex) in arrayCosts(zone.value)"
              :key="levelIndex"
              :model-value="cost"
              @update:model-value="updateArrayCost(zone.value, levelIndex, $event ?? cost)"
              :label="`Ур. ${levelIndex + 1}`"
              density="compact"
              hide-details
              style="min-width: 90px"
            />
            <v-btn icon size="small" variant="text" @click="addArrayCost(zone.value)">
              <v-icon>mdi-plus</v-icon>
            </v-btn>
            <v-btn icon size="small" variant="text" @click="removeArrayCost(zone.value)">
              <v-icon>mdi-minus</v-icon>
            </v-btn>
          </div>
        </template>

        <template v-else-if="zoneCost(zone.value).kind === 'progression'">
          <div class="d-flex gap-2 mt-1">
            <ClampedNumberField
              :model-value="progressionZone(zone.value)?.max_level ?? 1"
              @update:model-value="patchProgression(zone.value, 'max_level', $event)"
              label="Макс. уровень"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 140px"
            />
            <ClampedNumberField
              :model-value="progressionZone(zone.value)?.base_cost ?? 0"
              @update:model-value="patchProgression(zone.value, 'base_cost', $event)"
              label="Базовая стоимость"
              density="compact"
              hide-details
              style="min-width: 140px"
            />
            <ClampedNumberField
              :model-value="progressionZone(zone.value)?.step ?? 0"
              @update:model-value="patchProgression(zone.value, 'step', $event)"
              label="Шаг за уровень"
              density="compact"
              hide-details
              style="min-width: 140px"
            />
          </div>
        </template>

        <template v-else-if="zoneCost(zone.value).kind === 'automatic'">
          <div class="text-body-2 text-medium-emphasis">
            Способность получается автоматически при выполнении требований и не покупается.
          </div>
        </template>

        <ZoneParameterCostFields
          v-else-if="parameterZone(zone.value)"
          :model-value="parameterZone(zone.value)!"
          :parameter-items="parameterItems"
          @update:model-value="replaceCost(zone.value, $event)"
        />

        <ZoneParameterTableCostFields
          v-else-if="parameterTableZone(zone.value)"
          :model-value="parameterTableZone(zone.value)!"
          :parameter-items="parameterItems"
          @update:model-value="replaceCost(zone.value, $event)"
        />

        <ZoneParameterSumTablesCostFields
          v-else-if="parameterSumZone(zone.value)"
          :model-value="parameterSumZone(zone.value)!"
          :parameters="parameters ?? []"
          :parameter-items="parameterItems"
          @update:model-value="replaceCost(zone.value, $event)"
        />
      </template>
    </div>
  </div>
</template>
