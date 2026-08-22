<script setup lang="ts">
import { ref, watch } from 'vue';
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock';
import DefenseSlotsEditor from '@/modules/Roleplay/Rule/Component/DefenseSlotsEditor.vue';
import ResistanceSlotsEditor from '@/modules/Roleplay/Rule/Component/ResistanceSlotsEditor.vue';
import CharacteristicLimitsEditor from '@/modules/Roleplay/Rule/Component/CharacteristicLimitsEditor.vue';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const props = defineProps<{
  armor: ArmorBlock;
  damageTypes: { code: string; name: string }[];
  sources: { code: string; name: string }[];
  characteristics: { code: string; name: string }[];
  dexterityCode: string;
}>();

const emit = defineEmits<{
  'update:armor': [value: ArmorBlock];
}>();

const inner = ref<ArmorBlock>(cloneData(props.armor));

watch(
  () => props.armor,
  (value) => {
    if (JSON.stringify(value) !== JSON.stringify(inner.value)) {
      inner.value = cloneData(value);
    }
  },
  { deep: true },
);

watch(
  inner,
  (value) => {
    emit('update:armor', cloneData(value));
  },
  { deep: true },
);
</script>

<template>
  <div>
    <DefenseSlotsEditor v-model="inner.defense_slots" :sources="sources" />
    <ResistanceSlotsEditor
      v-model="inner.resistance_slots"
      :damage-types="damageTypes"
      :sources="sources"
      class="mt-2"
    />
    <CharacteristicLimitsEditor
      v-model="inner.characteristic_limits"
      :characteristics="characteristics"
      :default-characteristic-code="dexterityCode"
    />
  </div>
</template>
