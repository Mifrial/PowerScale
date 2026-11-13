<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { AssetScope } from '@/modules/Library/Asset/Enum/AssetScope';
import { useAssetLibrary } from '@/modules/Library/Asset/Composables/useAssetLibrary';
import AssetLibraryCard from '@/modules/Library/Asset/Component/AssetLibraryCard.vue';
import { useCurrentUser } from '@/modules/Core/User/init';

const props = defineProps<{
  modelValue: boolean;
  collectionModule: string;
  collectionCode: string;
  collectionTitle: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  select: [item: AssetItem];
}>();

const { currentUser } = useCurrentUser();
const {
  scope,
  items,
  total,
  search,
  loading,
  loadingMore,
  uploading,
  mutating,
  error,
  reload,
  loadMore,
  setScope,
  upload,
  rename,
  archive,
} = useAssetLibrary({
  getCollectionModule: () => props.collectionModule,
  getCollectionCode: () => props.collectionCode,
});

const selectedId = ref<number | null>(null);
const selectedItem = computed(() => items.value.find((item) => item.id === selectedId.value) ?? null);
const canUpload = computed(() =>
  scope.value === 'shared' ? currentUser.value?.bypass === true : currentUser.value !== null,
);
const emptyMessage = computed(() =>
  scope.value === 'shared' ? 'В общей библиотеке пока пусто' : 'Загрузите первый личный ассет',
);

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return;
    selectedId.value = null;
    void reload();
  },
  { immediate: true },
);

function close(): void {
  emit('update:modelValue', false);
}

function confirm(): void {
  if (selectedItem.value) {
    emit('select', selectedItem.value);
  }
  close();
}

function canEdit(item: AssetItem): boolean {
  if (item.isShared) return currentUser.value?.bypass === true;

  return item.ownerId !== null && item.ownerId === currentUser.value?.id;
}

async function onScopeChange(value: unknown): Promise<void> {
  const nextScope: AssetScope = value === 'personal' ? 'personal' : 'shared';
  if (nextScope === scope.value) return;
  selectedId.value = null;
  await setScope(nextScope);
}

async function onUploadPicked(value: File | File[] | null): Promise<void> {
  const file = Array.isArray(value) ? value[0] : value;
  if (!file) return;
  const item = await upload(file);
  if (item) selectedId.value = item.id;
}

async function onArchive(item: AssetItem): Promise<void> {
  await archive(item);
  if (selectedId.value === item.id) selectedId.value = null;
}

function onRename(item: AssetItem, name: string): void {
  void rename(item, name);
}
</script>

<template>
  <v-dialog
    :model-value="props.modelValue"
    max-width="1100"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card>
      <v-card-title>Библиотека ассетов · {{ props.collectionTitle }}</v-card-title>
      <v-tabs :model-value="scope" density="compact" @update:model-value="onScopeChange">
        <v-tab value="shared">Общие</v-tab>
        <v-tab value="personal">Мои</v-tab>
      </v-tabs>
      <v-divider />
      <v-card-text>
        <div class="asset-dialog__toolbar">
          <v-text-field
            v-model="search"
            label="Поиск по имени"
            prepend-inner-icon="mdi-magnify"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
          <v-file-input
            v-if="canUpload"
            :model-value="null"
            label="Загрузить ассет"
            accept="image/png,image/jpeg,image/webp"
            prepend-icon="mdi-upload"
            density="compact"
            variant="outlined"
            hide-details
            :loading="uploading"
            :disabled="uploading"
            @update:model-value="onUploadPicked"
          />
        </div>

        <v-progress-linear v-if="loading" indeterminate class="mt-2" />
        <v-alert v-if="error" type="error" density="compact" class="mt-2">
          {{ error }}
          <template #append>
            <v-btn size="small" variant="tonal" :disabled="loading" @click="reload">Повторить</v-btn>
          </template>
        </v-alert>

        <v-row v-if="items.length > 0" dense class="mt-1">
          <v-col v-for="assetItem in items" :key="assetItem.id" cols="12" sm="6" md="4" lg="3">
            <AssetLibraryCard
              :item="assetItem"
              :selected="assetItem.id === selectedId"
              :can-edit="canEdit(assetItem)"
              :busy="mutating"
              @select="selectedId = $event.id"
              @rename="onRename"
              @archive="onArchive"
            />
          </v-col>
        </v-row>
        <div v-else-if="!loading" class="asset-dialog__empty text-medium-emphasis">{{ emptyMessage }}</div>

        <div v-if="items.length < total" class="asset-dialog__more">
          <v-btn variant="tonal" size="small" :loading="loadingMore" @click="loadMore">Показать ещё</v-btn>
        </div>
      </v-card-text>
      <v-divider />
      <v-card-actions>
        <span class="text-caption text-medium-emphasis">{{ items.length }} из {{ total }}</span>
        <v-spacer />
        <v-btn variant="text" @click="close">Отмена</v-btn>
        <v-btn color="primary" :disabled="selectedItem === null" @click="confirm">Выбрать</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.asset-dialog__toolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 12px;
}

.asset-dialog__empty {
  padding: 48px 0;
  text-align: center;
}

.asset-dialog__more {
  display: flex;
  justify-content: center;
  margin-top: 16px;
}
</style>
