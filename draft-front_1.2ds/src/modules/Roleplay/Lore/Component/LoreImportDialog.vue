<script setup lang="ts">
import { computed, ref } from 'vue';
import type { LoreFile } from '@/modules/Roleplay/Lore/Dto/File/LoreFile';
import type { LoreImportConflictPolicy } from '@/modules/Roleplay/Lore/Enum/LoreImportConflictPolicy';
import type { LoreImportMode } from '@/modules/Roleplay/Lore/Enum/LoreImportMode';
import type { LoreImportReport } from '@/modules/Roleplay/Lore/Dto/LoreImportReport';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';

const props = defineProps<{
  modelValue: boolean;
  worlds: { code: string; name: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  imported: [worldCode: string];
}>();

const file = ref<LoreFile | null>(null);
const fileName = ref('');
const mode = ref<LoreImportMode>('new');
const targetWorldCode = ref<string | null>(null);
const conflictPolicy = ref<LoreImportConflictPolicy>('skip');
const report = ref<LoreImportReport | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
});

const targetOptions = computed(() => props.worlds.map((world) => ({ value: world.code, title: world.name })));

function isLoreFile(value: unknown): value is LoreFile {
  if (typeof value !== 'object' || value === null) return false;
  const candidate = value as { format?: unknown; formatVersion?: unknown; articles?: unknown };

  return candidate.format === 'powerscale.lore' && candidate.formatVersion === 1 && Array.isArray(candidate.articles);
}

async function onFileChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const selected = input.files?.[0];
  report.value = null;
  error.value = null;
  file.value = null;
  fileName.value = '';
  if (!selected) return;

  try {
    const parsed: unknown = JSON.parse(await selected.text());
    if (!isLoreFile(parsed)) {
      error.value = 'Это не файл кодекса powerscale.lore v1.';

      return;
    }
    file.value = parsed;
    fileName.value = selected.name;
  } catch {
    error.value = 'Файл не является корректным JSON.';
  }
}

async function runImport(dryRun: boolean): Promise<void> {
  if (!file.value) return;
  if (mode.value === 'merge' && !targetWorldCode.value) {
    error.value = 'Выберите мир для слияния.';

    return;
  }

  loading.value = true;
  error.value = null;
  try {
    report.value = await getLoreApi().importWorld(file.value, {
      mode: mode.value,
      targetWorldCode: mode.value === 'merge' ? (targetWorldCode.value ?? undefined) : undefined,
      conflictPolicy: conflictPolicy.value,
      dryRun,
    });
    if (!dryRun) emit('imported', report.value.worldCode);
  } catch (exception) {
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось импортировать мир';
  } finally {
    loading.value = false;
  }
}

function close(): void {
  isOpen.value = false;
  file.value = null;
  report.value = null;
  error.value = null;
}
</script>

<template>
  <v-dialog v-model="isOpen" max-width="720">
    <v-card>
      <v-card-title>Импорт мира</v-card-title>
      <v-card-text>
        <v-file-input
          label="Файл powerscale.lore"
          accept="application/json,.json"
          density="compact"
          prepend-icon=""
          prepend-inner-icon="mdi-file-import"
          @change="onFileChange"
        />
        <div v-if="fileName" class="text-caption text-medium-emphasis mb-2">Выбран файл: {{ fileName }}</div>

        <v-row dense>
          <v-col cols="12" md="4">
            <v-select
              v-model="mode"
              :items="[
                { value: 'new', title: 'Новый мир' },
                { value: 'merge', title: 'Слияние' },
              ]"
              item-title="title"
              item-value="value"
              label="Режим"
              density="compact"
            />
          </v-col>
          <v-col v-if="mode === 'merge'" cols="12" md="5">
            <v-select
              v-model="targetWorldCode"
              :items="targetOptions"
              item-title="title"
              item-value="value"
              label="Целевой мир"
              density="compact"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="conflictPolicy"
              :items="[
                { value: 'skip', title: 'Пропускать' },
                { value: 'overwrite', title: 'Перезаписывать' },
              ]"
              item-title="title"
              item-value="value"
              label="Конфликты"
              density="compact"
            />
          </v-col>
        </v-row>

        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate class="my-3" />

        <v-card v-if="report" class="mt-3">
          <v-card-text>
            <div class="text-subtitle-2 mb-2">
              {{ report.dryRun ? 'Проверка без записи' : 'Импорт выполнен' }}: мир {{ report.worldCode }}
            </div>
            <v-table density="compact">
              <thead>
                <tr>
                  <th />
                  <th>Разделы</th>
                  <th>Статьи</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Создано</td>
                  <td>{{ report.created.sections }}</td>
                  <td>{{ report.created.articles }}</td>
                </tr>
                <tr>
                  <td>Обновлено</td>
                  <td>{{ report.updated.sections }}</td>
                  <td>{{ report.updated.articles }}</td>
                </tr>
                <tr>
                  <td>Пропущено</td>
                  <td>{{ report.skipped.sections }}</td>
                  <td>{{ report.skipped.articles }}</td>
                </tr>
              </tbody>
            </v-table>
            <v-alert v-if="report.warnings.length > 0" type="warning" variant="tonal" density="compact" class="mt-3">
              <div v-for="warning in report.warnings" :key="`${warning.code}:${warning.path}`" class="text-caption">
                {{ warning.path }} — {{ warning.message }}
              </div>
            </v-alert>
          </v-card-text>
        </v-card>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="close">Закрыть</v-btn>
        <v-btn variant="tonal" :disabled="!file || loading" @click="runImport(true)">Проверить</v-btn>
        <v-btn color="primary" :disabled="!file || loading" @click="runImport(false)">Импортировать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
