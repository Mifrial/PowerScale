<script setup lang="ts">
import { ref, watch } from 'vue';
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import BlockProfileEditor from '@/modules/Roleplay/Rule/Component/BlockProfileEditor.vue';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const props = defineProps<{
  shield: ShieldBlock;
  damageTypes: { code: string; name: string }[];
  sources: { code: string; name: string }[];
}>();

const emit = defineEmits<{
  'update:shield': [value: ShieldBlock];
}>();

const inner = ref<ShieldBlock>(cloneData(props.shield));

watch(
  () => props.shield,
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
    emit('update:shield', cloneData(value));
  },
  { deep: true },
);
</script>

<template>
  <div>
    <DimensionalNumberInput v-model="inner.min_strength" label="Минимальная сила" :min="3" :max="5" />
    <BlockProfileEditor v-model="inner.block" :damage-types="damageTypes" :sources="sources" :show-toggle="false" />
  </div>
</template>
