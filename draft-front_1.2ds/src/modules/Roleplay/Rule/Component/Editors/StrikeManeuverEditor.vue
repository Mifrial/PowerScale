<script setup lang="ts">
import { computed } from 'vue';
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';
import type {
  StrikeManeuverEffect,
  StrikeManeuverEffectSelector,
} from '@/modules/Roleplay/Rule/Dto/Ability/StrikeManeuver';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';

const props = defineProps<{
  modelValue: AbilitySpecDraft;
  states: { code: string; name: string }[];
  maneuvers: { code: string; name: string; effects: StrikeManeuverEffect[] }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: AbilitySpecDraft];
}>();

const spec = computed(() => props.modelValue);

function commit(next: AbilitySpecDraft) {
  emit('update:modelValue', next);
}

function setHasStrikeManeuver(enabled: boolean) {
  commit(
    abilitySpecService.setStrikeManeuver(
      spec.value,
      enabled ? abilitySpecService.createEmptyStrikeManeuver() : null,
    ),
  );
}

function patchManeuver(patch: Parameters<typeof abilitySpecService.patchStrikeManeuver>[1]) {
  commit(abilitySpecService.patchStrikeManeuver(spec.value, patch));
}

function patchManeuverEffect(index: number, patch: Partial<StrikeManeuverEffect>) {
  commit(abilitySpecService.patchStrikeManeuverEffect(spec.value, index, patch));
}

function addManeuverEffect() {
  commit(abilitySpecService.addStrikeManeuverEffect(spec.value));
}

function removeManeuverEffect(index: number) {
  commit(abilitySpecService.removeStrikeManeuverEffect(spec.value, index));
}

function setHasManeuverBoost(enabled: boolean) {
  commit(
    abilitySpecService.setManeuverBoost(
      spec.value,
      enabled ? abilitySpecService.createEmptyManeuverBoost() : null,
    ),
  );
}

function patchBoost(patch: Parameters<typeof abilitySpecService.patchManeuverBoost>[1]) {
  commit(abilitySpecService.patchManeuverBoost(spec.value, patch));
}

function patchBoostManeuvers(codes: unknown) {
  const selected = Array.isArray(codes) ? codes.map(String) : [];
  const effects = props.maneuvers
    .filter((option) => selected.includes(option.code))
    .flatMap((option) => option.effects);
  commit(
    abilitySpecService.patchManeuverBoost(spec.value, {
      maneuvers: selected,
      replaces: selectorsOf(effects),
    }),
  );
}

function patchBoostEffect(index: number, patch: Partial<StrikeManeuverEffect>) {
  commit(abilitySpecService.patchManeuverBoostEffect(spec.value, index, patch));
}

function addBoostEffect() {
  commit(abilitySpecService.addManeuverBoostEffect(spec.value));
}

function removeBoostEffect(index: number) {
  commit(abilitySpecService.removeManeuverBoostEffect(spec.value, index));
}

function selectorsOf(effects: StrikeManeuverEffect[]): StrikeManeuverEffectSelector[] {
  const seen = new Set<string>();
  const selectors: StrikeManeuverEffectSelector[] = [];
  for (const effect of effects) {
    const key = `${effect.kind}:${effect.state}`;
    if (!effect.state || seen.has(key)) continue;
    seen.add(key);
    selectors.push({ kind: effect.kind, state: effect.state });
  }

  return selectors;
}

const selectedManeuvers = computed(() => {
  const codes = spec.value.maneuver_boost?.maneuvers ?? [];

  return props.maneuvers.filter((option) => codes.includes(option.code));
});

const replaceOptions = computed(() => {
  const seen = new Set<string>();
  const options: { key: string; title: string; kind: 'state'; state: string }[] = [];
  for (const maneuver of selectedManeuvers.value) {
    for (const effect of maneuver.effects) {
      const key = `${effect.kind}:${effect.state}`;
      if (!effect.state || seen.has(key)) continue;
      seen.add(key);
      options.push({ key, title: effectLabel(effect), kind: effect.kind, state: effect.state });
    }
  }

  return options;
});

const replaceKeys = computed(() => {
  const boost = spec.value.maneuver_boost;
  if (!boost) return [];
  const selectors = boost.replaces ?? [];

  return selectors.map((selector) => `${selector.kind}:${selector.state}`);
});

function patchReplaces(keys: unknown) {
  const selected = Array.isArray(keys) ? keys.map(String) : [];
  const selectors: StrikeManeuverEffectSelector[] = [];
  for (const option of replaceOptions.value) {
    if (selected.includes(option.key)) {
      selectors.push({ kind: option.kind, state: option.state });
    }
  }

  commit(abilitySpecService.patchManeuverBoostReplace(spec.value, selectors));
}

function effectLabel(effect: StrikeManeuverEffect): string {
  const state = props.states.find((entry) => entry.code === effect.state);

  return `${state?.name ?? effect.state ?? '—'} ${effect.amount}`;
}

function replaceHint(): string {
  return 'Отмеченные эффекты манёвра убираются, вместо них добавляются новые. Ничего не отмечено — новые эффекты добавляются к базовым.';
}
</script>

<template>
  <div>
    <v-checkbox
      :model-value="!!spec.strike_maneuver"
      label="Карточка — манёвр после удара"
      density="compact"
      hide-details
      @update:model-value="(v) => setHasStrikeManeuver(!!v)"
    />
    <div v-if="spec.strike_maneuver" class="d-flex flex-column ga-2 mt-2 ml-4">
      <div class="d-flex ga-2 flex-wrap align-center">
        <ClampedNumberField
          :model-value="spec.strike_maneuver.min_success"
          label="Минимальный РУ удара"
          density="compact"
          hide-details
          :min="1"
          style="max-width: 190px"
          @update:model-value="(v) => patchManeuver({ min_success: v ?? 1 })"
        />
        <ClampedNumberField
          :model-value="spec.strike_maneuver.min_weapon_mastery ?? 0"
          label="Мастерство оружия удара"
          density="compact"
          hide-details
          :min="0"
          style="max-width: 210px"
          @update:model-value="(v) => patchManeuver({ min_weapon_mastery: v || undefined })"
        />
      </div>
      <div class="text-caption text-medium-emphasis">
        Манёвр объявляется сразу после удара. Мастерство проверяется по оружию прошлого удара; 0 — без проверки.
      </div>
      <div class="text-subtitle-2 mt-1">Цель получает</div>
      <div
        v-for="(effect, index) in spec.strike_maneuver.effects"
        :key="index"
        class="d-flex ga-2 flex-wrap align-center"
      >
        <v-autocomplete
          :model-value="effect.state"
          :items="states"
          item-title="name"
          item-value="code"
          label="Состояние"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
          style="min-width: 220px"
          @update:model-value="(v) => patchManeuverEffect(index, { state: String(v ?? '') })"
        />
        <ClampedNumberField
          :model-value="effect.amount"
          label="Значение"
          density="compact"
          hide-details
          :min="1"
          style="max-width: 140px"
          @update:model-value="(v) => patchManeuverEffect(index, { amount: v ?? 1 })"
        />
        <v-btn
          icon="mdi-delete-outline"
          variant="text"
          size="small"
          :disabled="spec.strike_maneuver.effects.length < 2"
          @click="removeManeuverEffect(index)"
        />
      </div>
      <v-btn variant="text" size="small" class="align-self-start" @click="addManeuverEffect">
        Добавить эффект
      </v-btn>
    </div>

    <v-divider class="my-3" />

    <v-checkbox
      :model-value="!!spec.maneuver_boost"
      label="Усиление манёвра при высоком РУ"
      density="compact"
      hide-details
      @update:model-value="(v) => setHasManeuverBoost(!!v)"
    />
    <div v-if="spec.maneuver_boost" class="d-flex flex-column ga-2 mt-2 ml-4">
      <v-select
        :model-value="spec.maneuver_boost.maneuvers"
        :items="maneuvers"
        item-title="name"
        item-value="code"
        label="Усиливаемые манёвры"
        density="compact"
        hide-details
        multiple
        chips
        @update:model-value="patchBoostManeuvers"
      />
      <div class="d-flex ga-2 flex-wrap align-center">
        <ClampedNumberField
          :model-value="spec.maneuver_boost.min_success"
          label="Минимальный РУ удара"
          density="compact"
          hide-details
          :min="1"
          style="max-width: 190px"
          @update:model-value="(v) => patchBoost({ min_success: v ?? 1 })"
        />
        <ClampedNumberField
          :model-value="spec.maneuver_boost.min_weapon_mastery ?? 0"
          label="Мастерство оружия удара"
          density="compact"
          hide-details
          :min="0"
          style="max-width: 210px"
          @update:model-value="(v) => patchBoost({ min_weapon_mastery: v || undefined })"
        />
      </div>

      <div class="text-subtitle-2 mt-1">Заменяемые эффекты</div>
      <div class="text-caption text-medium-emphasis">{{ replaceHint() }}</div>
      <v-select
        :model-value="replaceKeys"
        :items="replaceOptions"
        item-title="title"
        item-value="key"
        label="Эффекты выбранного манёвра"
        density="compact"
        hide-details
        multiple
        chips
        :disabled="selectedManeuvers.length === 0"
        @update:model-value="patchReplaces"
      />

      <div class="text-subtitle-2 mt-1">Новые эффекты</div>
      <div
        v-for="(effect, index) in spec.maneuver_boost.effects"
        :key="index"
        class="d-flex ga-2 flex-wrap align-center"
      >
        <v-autocomplete
          :model-value="effect.state"
          :items="states"
          item-title="name"
          item-value="code"
          label="Состояние"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
          style="min-width: 220px"
          @update:model-value="(v) => patchBoostEffect(index, { state: String(v ?? '') })"
        />
        <ClampedNumberField
          :model-value="effect.amount"
          label="Значение"
          density="compact"
          hide-details
          :min="1"
          style="max-width: 140px"
          @update:model-value="(v) => patchBoostEffect(index, { amount: v ?? 1 })"
        />
        <v-btn
          icon="mdi-delete-outline"
          variant="text"
          size="small"
          :disabled="spec.maneuver_boost.effects.length < 2"
          @click="removeBoostEffect(index)"
        />
      </div>
      <v-btn variant="text" size="small" class="align-self-start" @click="addBoostEffect">
        Добавить эффект
      </v-btn>
    </div>
  </div>
</template>
