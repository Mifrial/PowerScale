<script setup lang="ts">
import { watch } from 'vue';
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';
import type { NamedOption } from '@/modules/Roleplay/Rule/Dto/NamedOption';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';

const props = defineProps<{
  modelValue: AbilitySpecDraft;
  currentType: string | null;
  items: NamedOption[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: AbilitySpecDraft];
}>();

const combatActions = [
  { title: 'Уклонение', value: 'dodge' },
  { title: 'Блок', value: 'block' },
  { title: 'Поворот', value: 'turn' },
  { title: 'Ожидание', value: 'wait' },
  { title: 'Восстановление устойчивости', value: 'recover-stability' },
  { title: 'Простое касание', value: 'simple-touch' },
];

const attackModes = [
  { title: 'Одна цель', value: 'single' },
  { title: 'Широкая', value: 'wide' },
];

function patch(partial: Partial<AbilitySpecDraft>): void {
  emit('update:modelValue', { ...props.modelValue, ...partial });
}

function clearKey(key: keyof AbilitySpecDraft): void {
  const next = { ...props.modelValue };
  delete next[key];
  emit('update:modelValue', next);
}

watch(
  () => props.modelValue.combat_action === 'dodge' && props.modelValue.dodge_efficiency == null,
  (missing) => {
    if (missing) patch({ dodge_efficiency: { base: 4, size: -1 } });
  },
  { immediate: true },
);

function setCombatAction(value: AbilitySpecDraft['combat_action'] | null): void {
  const next = { ...props.modelValue };
  if (!value) {
    delete next.combat_action;
    delete next.dodge_efficiency;
    emit('update:modelValue', next);

    return;
  }
  next.combat_action = value;
  if (value === 'dodge') {
    next.dodge_efficiency = next.dodge_efficiency ?? { base: 4, size: -1 };
  } else {
    delete next.dodge_efficiency;
  }
  emit('update:modelValue', next);
}
</script>

<template>
  <div class="d-flex flex-column ga-2">
    <v-select
      :model-value="modelValue.combat_action ?? null"
      :items="combatActions"
      label="Боевая роль"
      density="compact"
      hide-details
      clearable
      @update:model-value="setCombatAction($event)"
    />
    <DimensionalNumberInput
      v-if="modelValue.combat_action === 'dodge'"
      :model-value="modelValue.dodge_efficiency ?? null"
      label="Эффективность уклонения"
      @update:model-value="patch({ dodge_efficiency: $event })"
    />
    <v-autocomplete
      :model-value="modelValue.weapon_item_code ?? null"
      :items="items"
      item-title="name"
      item-value="code"
      label="Предмет владения оружием"
      density="compact"
      hide-details
      clearable
      @update:model-value="patch({ weapon_item_code: $event ?? null })"
    />
    <div class="d-flex ga-2 flex-wrap">
      <v-checkbox
        :model-value="modelValue.peak_concentration === true"
        label="Пиковая концентрация"
        density="compact"
        hide-details
        @update:model-value="patch({ peak_concentration: $event ? true : undefined })"
      />
      <v-checkbox
        :model-value="modelValue.will_focus === true"
        label="Фокус воли"
        density="compact"
        hide-details
        @update:model-value="patch({ will_focus: $event ? true : undefined })"
      />
      <v-checkbox
        :model-value="modelValue.long_tension === true"
        label="Долгое напряжение"
        density="compact"
        hide-details
        @update:model-value="patch({ long_tension: $event ? true : undefined })"
      />
      <v-checkbox
        :model-value="modelValue.multiple === true"
        label="Множественный навык"
        density="compact"
        hide-details
        @update:model-value="patch({ multiple: $event ? true : undefined })"
      />
    </div>
    <v-text-field
      v-if="modelValue.multiple"
      :model-value="modelValue.domain_ref ?? ''"
      label="Справочник домена"
      density="compact"
      hide-details
      @update:model-value="patch({ domain_ref: String($event || '') || null })"
    />
    <v-text-field
      :model-value="modelValue.knowledge_template_field ?? ''"
      label="Шаблон знания"
      density="compact"
      hide-details
      @update:model-value="patch({ knowledge_template_field: String($event || '') || null })"
    />
    <v-text-field
      :model-value="modelValue.parent_knowledge_field ?? ''"
      label="Фильтр знания родителя"
      density="compact"
      hide-details
      @update:model-value="patch({ parent_knowledge_field: String($event || '') || null })"
    />
    <ClampedNumberField
      :model-value="modelValue.movement_step_size_delta ?? null"
      label="Дельта размера шага"
      nullable
      density="compact"
      hide-details
      @update:model-value="
        $event === null ? clearKey('movement_step_size_delta') : patch({ movement_step_size_delta: $event })
      "
    />
    <template v-if="currentType === 'action' || currentType === 'spell' || currentType === 'skill'">
      <v-select
        v-if="currentType !== 'skill'"
        :model-value="modelValue.attack_mode ?? null"
        :items="attackModes"
        label="Режим атаки"
        density="compact"
        hide-details
        clearable
        @update:model-value="$event ? patch({ attack_mode: $event }) : clearKey('attack_mode')"
      />
      <ClampedNumberField
        v-if="currentType !== 'skill'"
        :model-value="modelValue.max_targets ?? null"
        label="Максимум целей"
        :min="1"
        nullable
        density="compact"
        hide-details
        @update:model-value="$event === null ? clearKey('max_targets') : patch({ max_targets: $event })"
      />
      <ClampedNumberField
        :model-value="modelValue.min_total_action_cost ?? null"
        label="Мин. итоговая стоимость ОД"
        :min="0"
        nullable
        density="compact"
        hide-details
        @update:model-value="
          $event === null ? clearKey('min_total_action_cost') : patch({ min_total_action_cost: $event })
        "
      />
      <ClampedNumberField
        :model-value="modelValue.strike_count ?? null"
        label="Число ударов"
        :min="1"
        nullable
        density="compact"
        hide-details
        @update:model-value="$event === null ? clearKey('strike_count') : patch({ strike_count: $event })"
      />
      <div class="d-flex ga-2 flex-wrap">
        <v-checkbox
          :model-value="modelValue.distinct_weapons === true"
          label="Разные экземпляры оружия"
          density="compact"
          hide-details
          @update:model-value="patch({ distinct_weapons: $event ? true : undefined })"
        />
        <v-checkbox
          :model-value="modelValue.same_weapon === true"
          label="Одно оружие, несколько попаданий"
          density="compact"
          hide-details
          @update:model-value="patch({ same_weapon: $event ? true : undefined })"
        />
        <v-checkbox
          :model-value="modelValue.lift_parent_max_weapons === true"
          label="Снять max_weapons родителя"
          density="compact"
          hide-details
          @update:model-value="patch({ lift_parent_max_weapons: $event ? true : undefined })"
        />
      </div>
      <div class="d-flex ga-2">
        <ClampedNumberField
          :model-value="modelValue.min_weapons ?? null"
          label="Min оружия"
          :min="1"
          nullable
          density="compact"
          hide-details
          @update:model-value="$event === null ? clearKey('min_weapons') : patch({ min_weapons: $event })"
        />
        <ClampedNumberField
          :model-value="modelValue.max_weapons ?? null"
          label="Max оружия"
          :min="1"
          nullable
          density="compact"
          hide-details
          @update:model-value="$event === null ? clearKey('max_weapons') : patch({ max_weapons: $event })"
        />
      </div>
    </template>
  </div>
</template>
