<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ItemStorageValue } from '@/modules/Roleplay/Character/Dto/ItemStorageValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { magicSourcePowerService } from '@/modules/Roleplay/Character/Service/Instance/magicSourcePowerService';

const props = withDefaults(
  defineProps<{
    storage: ItemStorageValue;
    rules: Rule[];
    /** Ручная правка маны: инструмент ведущего, как правка ресурса. */
    editable?: boolean;
  }>(),
  { editable: false },
);

const emit = defineEmits<{
  apply: [storage: ItemStorageValue, value: number];
}>();

const name = computed(() => magicSourcePowerService.sourceName(props.storage, props.rules));
const volume = computed(() => `${props.storage.occupied} / ${props.storage.volume}`);
const mana = computed(() => `${props.storage.mana} / ${props.storage.manaMax}`);
const draft = ref(props.storage.mana);

watch(
  () => props.storage.mana,
  (value) => {
    draft.value = value;
  },
);

function submit(): void {
  emit('apply', props.storage, draft.value);
}
</script>

<template>
  <v-divider class="my-2" />
  <div class="d-flex align-center justify-space-between ga-3 py-1 text-body-2">
    <span class="text-medium-emphasis">Источник</span>
    <RuleLink :rule-code="storage.ruleCode" class="font-weight-medium">{{ name }}</RuleLink>
  </div>
  <div class="d-flex align-center justify-space-between ga-3 py-1 text-body-2">
    <span class="text-medium-emphasis">Мана</span>
    <span class="font-weight-medium">{{ mana }}</span>
  </div>
  <div class="d-flex align-center justify-space-between ga-3 py-1 text-body-2">
    <span class="text-medium-emphasis">Объём</span>
    <span class="font-weight-medium">{{ volume }}</span>
  </div>

  <template v-if="editable">
    <ClampedNumberField
      v-model="draft"
      class="mt-3"
      label="Задать ману"
      :min="0"
      :max="storage.manaMax"
      density="compact"
      hide-details
    />
    <v-btn class="mt-3" color="primary" variant="tonal" size="small" block type="button" @click.stop="submit">
      Применить
    </v-btn>
  </template>
</template>
