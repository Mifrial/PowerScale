<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';

const router = useRouter();
const store = useSpaceStore();
const { signal } = useAbortable();

const name = ref('');
const description = ref('');
const inheritFrom = ref<number | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);

const spaceOptions = computed(() => store.spaces.filter((s) => s.active).map((s) => ({ title: s.name, value: s.id })));

const inheritedSpace = computed(() => store.spaces.find((s) => s.id === inheritFrom.value));

onMounted(() => {
  if (store.spaces.length === 0) {
    store.fetchSpaces(signal.value);
  }
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
        inheritFrom: inheritFrom.value,
      },
      signal.value,
    );
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
        />

        <v-card v-if="inheritFrom" variant="tonal" color="info" class="mt-4">
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
