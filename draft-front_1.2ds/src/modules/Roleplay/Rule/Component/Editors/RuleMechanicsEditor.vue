<script setup lang="ts">
import { ref } from 'vue';
import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';
import type { RuleMechanicRef } from '@/modules/Roleplay/Rule/Dto/RuleMechanicRef';
import MechanicPayloadInspector from '@/modules/Roleplay/Rule/Component/MechanicPayloadInspector.vue';
import { mechanicPayloadInspectorService } from '@/modules/Roleplay/Rule/Service/Instance/mechanicPayloadInspectorService';

const props = defineProps<{
  mechanics: RuleMechanicRef[];
  loadedMechanics: RuleMechanicRef[];
  mechanicOptions: { title: string; value: number }[];
  isNew: boolean;
  error: string | null;
}>();

const emit = defineEmits<{
  'update:mechanics': [value: RuleMechanicRef[]];
}>();

const inspectorRefs = ref<{ readText: () => string; isEditing: () => boolean }[]>([]);

function replace(next: RuleMechanicRef[]): void {
  emit('update:mechanics', next);
}

function setMechanicId(index: number, mechanicId: number | null): void {
  if (mechanicId == null) {
    replace(props.mechanics.filter((_, rowIndex) => rowIndex !== index));

    return;
  }

  replace(
    props.mechanics.map((row, rowIndex) =>
      rowIndex === index ? { mechanicId, mechanicPayload: row.mechanicId === mechanicId ? row.mechanicPayload : null } : row,
    ),
  );
}

function addRow(): void {
  const mechanicId = props.mechanicOptions[0]?.value;
  if (mechanicId == null) return;
  replace([...props.mechanics, { mechanicId, mechanicPayload: null }]);
}

function removeRow(index: number): void {
  replace(props.mechanics.filter((_, rowIndex) => rowIndex !== index));
}

function rowChanged(index: number): boolean {
  const loaded = props.loadedMechanics[index];

  return !!loaded && loaded.mechanicId !== props.mechanics[index]?.mechanicId;
}

function commitPayloads(): boolean {
  const next = props.mechanics.map((row) => ({ ...row }));
  for (let index = 0; index < inspectorRefs.value.length; index += 1) {
    const inspector = inspectorRefs.value[index];
    if (!inspector?.isEditing()) continue;
    try {
      const parsed = mechanicPayloadInspectorService.parse(inspector.readText());
      next[index] = { ...next[index], mechanicPayload: structuredClone(parsed) as MechanicPayload };
    } catch (error) {
      throw error instanceof Error ? error : new Error('Некорректный JSON payload механики');
    }
  }
  replace(next);

  return true;
}

function setInspector(index: number, element: unknown): void {
  inspectorRefs.value[index] = element as { readText: () => string; isEditing: () => boolean };
}

function bindInspector(index: number, element: unknown): void {
  inspectorRefs.value[index] = element as { readText: () => string; isEditing: () => boolean };
}

defineExpose({ commitPayloads });
</script>

<template>
  <div class="mt-4">
    <div class="text-subtitle-2 mb-2">Механики</div>
    <div v-for="(row, index) in mechanics" :key="index" class="mb-4">
      <v-select
        :model-value="row.mechanicId"
        :items="mechanicOptions"
        item-title="title"
        item-value="value"
        label="Механика"
        clearable
        @update:model-value="(value) => setMechanicId(index, value)"
      />
      <MechanicPayloadInspector
        :ref="(element) => bindInspector(index, element)"
        :payload="row.mechanicPayload"
        :is-new="isNew && !loadedMechanics[index]"
        :mechanic-changed="rowChanged(index)"
        :error="error"
        @commit="commitPayloads"
      />
      <v-btn class="mt-2" variant="text" @click="removeRow(index)">Убрать</v-btn>
    </div>
    <v-btn variant="tonal" :disabled="mechanicOptions.length === 0" @click="addRow">Добавить механику</v-btn>
  </div>
</template>
