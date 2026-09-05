<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { RevisionFile } from '@/modules/Roleplay/Space/Dto/RevisionFile';
import { revisionFileService } from '@/modules/Roleplay/Space/Service/Instance/revisionFileService';

const props = defineProps<{
  modelValue: boolean;
  allowCurrent: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  confirm: [payload: { file: RevisionFile; intoCurrent: boolean; removeMissing: boolean }];
}>();

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
});

const parseError = ref<string | null>(null);
const parsed = ref<RevisionFile | null>(null);
const target = ref<'current' | 'new'>('current');
const removeMissing = ref(false);
const picking = ref(false);

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return;
    parseError.value = null;
    parsed.value = null;
    target.value = props.allowCurrent ? 'current' : 'new';
    removeMissing.value = false;
  },
);

async function onFile(files: File | File[] | null): Promise<void> {
  const file = Array.isArray(files) ? (files[0] ?? null) : files;
  parseError.value = null;
  parsed.value = null;
  if (!file) return;
  picking.value = true;
  try {
    parsed.value = revisionFileService.parse(await file.text());
  } catch (error) {
    parseError.value = error instanceof Error ? error.message : 'Не удалось прочитать файл';
  } finally {
    picking.value = false;
  }
}

function confirm(): void {
  if (!parsed.value) return;
  emit('confirm', {
    file: parsed.value,
    intoCurrent: props.allowCurrent && target.value === 'current',
    removeMissing: removeMissing.value,
  });
  open.value = false;
}

const sourceLabel = computed(() => {
  const revision = parsed.value?.revision;
  if (!revision) return '';

  return `${revision.spaceName} (${revision.spaceCode}) v${revision.revision}, правил: ${revision.rules.length}`;
});

const canConfirm = computed(() => parsed.value !== null && !parseError.value);
</script>

<template>
  <v-dialog v-model="open" max-width="560">
    <v-card>
      <v-card-title>Импорт ревизии</v-card-title>
      <v-card-text>
        <v-file-input
          accept="application/json,.json"
          label="Файл ревизии"
          prepend-icon="mdi-file-import"
          :loading="picking"
          @update:model-value="onFile"
        />
        <v-alert v-if="parseError" type="error" class="mt-2" density="compact">{{ parseError }}</v-alert>
        <div v-if="parsed" class="text-body-2 mt-2">{{ sourceLabel }}</div>

        <v-radio-group v-if="allowCurrent && parsed" v-model="target" class="mt-4" hide-details>
          <v-radio value="current" label="В это пространство (черновик)" />
          <v-radio value="new" label="Создать новое пространство" />
        </v-radio-group>

        <v-switch
          v-if="allowCurrent && target === 'current' && parsed"
          v-model="removeMissing"
          label="Убрать правила, которых нет в файле"
          color="primary"
          hide-details
          class="mt-2"
        />
        <div
          v-if="allowCurrent && target === 'current' && removeMissing"
          class="text-caption text-medium-emphasis mt-2"
        >
          В новой ревизии они получат маркер удаления. Уже опубликованные ревизии не изменятся.
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" variant="tonal" :disabled="!canConfirm" @click="confirm">Импортировать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
