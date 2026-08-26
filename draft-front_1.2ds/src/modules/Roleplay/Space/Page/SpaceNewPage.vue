<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { revisionFileService } from '@/modules/Roleplay/Space/Service/Instance/revisionFileService';

const router = useRouter();
const store = useSpaceStore();
const draftStore = useDraftRuleStore();
const { signal } = useAbortable();

const name = ref('');
const description = ref('');
const inheritFrom = ref<number | null>(null);
const importedRules = ref<Rule[] | null>(null);
const importLabel = ref('');
const importError = ref<string | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);

const spaceOptions = computed(() => store.spaces.filter((s) => s.active).map((s) => ({ title: s.name, value: s.id })));

const inheritedSpace = computed(() => store.spaces.find((s) => s.id === inheritFrom.value));

onMounted(() => {
  if (store.spaces.length === 0) {
    store.fetchSpaces(signal.value);
  }
  if (store.pendingImportedRules?.length) {
    importedRules.value = store.pendingImportedRules;
    importLabel.value = `Файл: ${store.pendingImportedRules.length} правил`;
    store.pendingImportedRules = null;
    inheritFrom.value = null;
  }
});

async function onImportFile(files: File | File[] | null): Promise<void> {
  const file = Array.isArray(files) ? (files[0] ?? null) : files;
  importError.value = null;
  if (!file) return;
  try {
    const parsed = revisionFileService.parse(await file.text());
    importedRules.value = parsed.revision.rules;
    importLabel.value = `${parsed.revision.spaceName} v${parsed.revision.revision}, правил: ${parsed.revision.rules.length}`;
    inheritFrom.value = null;
  } catch (error) {
    importedRules.value = null;
    importLabel.value = '';
    importError.value = error instanceof Error ? error.message : 'Не удалось прочитать файл';
  }
}

function clearImport(): void {
  importedRules.value = null;
  importLabel.value = '';
  importError.value = null;
}

watch(inheritFrom, (value) => {
  if (value !== null) clearImport();
});

async function save() {
  if (!name.value.trim()) return;
  saving.value = true;
  saveError.value = null;
  try {
    const space = await store.createSpace(
      {
        name: name.value,
        description: description.value,
        inheritFrom: importedRules.value ? null : inheritFrom.value,
      },
      signal.value,
    );
    if (importedRules.value) {
      const diff = revisionFileService.diffAgainstPublished(importedRules.value, [], space.id, {
        removeMissing: false,
        existingRemovedCodes: [],
      });
      for (const rule of diff.added) {
        draftStore.saveRule(space.id, rule);
      }
      router.push(`/space/${space.code}/draft`);

      return;
    }
    router.push(`/space/${space.code}`);
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    saveError.value = 'Не удалось создать пространство';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">Создание пространства</h1>

    <v-alert v-if="saveError" type="error" class="mb-4" closable @click:close="saveError = null">
      {{ saveError }}
    </v-alert>

    <v-card>
      <v-card-text>
        <v-text-field v-model="name" label="Название" :rules="[(v) => !!v || 'Обязательное поле']" />

        <v-textarea v-model="description" label="Описание" rows="3" class="mt-4" />

        <v-divider class="my-6" />

        <v-select
          v-model="inheritFrom"
          :items="spaceOptions"
          label="Наследовать от (опционально)"
          hint="Все правила будут скопированы из выбранного пространства"
          persistent-hint
          clearable
          :disabled="!!importedRules"
        />

        <v-file-input
          class="mt-4"
          accept="application/json,.json"
          label="Или импорт ревизии из файла"
          prepend-icon="mdi-file-import"
          :disabled="inheritFrom !== null"
          @update:model-value="onImportFile"
        />
        <v-alert v-if="importError" type="error" class="mt-2" density="compact">{{ importError }}</v-alert>
        <v-chip v-if="importedRules" class="mt-2" closable @click:close="clearImport">{{ importLabel }}</v-chip>
        <div v-if="importedRules" class="text-caption text-medium-emphasis mt-2">
          Правила попадут в черновик. Опубликовать можно после проверки валидатором — первая ревизия будет v1.
        </div>

        <v-card v-if="inheritFrom && !importedRules" variant="tonal" color="info" class="mt-4">
          <v-card-text>
            <div class="d-flex align-center mb-2">
              <v-icon class="mr-2">mdi-information</v-icon>
              <strong>Будет скопировано</strong>
            </div>
            <div class="text-body-2">
              Правил: <strong>{{ inheritedSpace?.rulesCount ?? 0 }}</strong>
            </div>
            <div class="text-caption text-medium-emphasis mt-2">После создания пространство станет независимым.</div>
          </v-card-text>
        </v-card>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="router.back()">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">Создать</v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>
