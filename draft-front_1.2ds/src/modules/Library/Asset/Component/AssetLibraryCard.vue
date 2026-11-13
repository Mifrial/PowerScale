<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';

const FileImage = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileImage.vue'));

const props = defineProps<{
  item: AssetItem;
  selected: boolean;
  canEdit: boolean;
  busy: boolean;
}>();

const emit = defineEmits<{
  select: [item: AssetItem];
  rename: [item: AssetItem, name: string];
  archive: [item: AssetItem];
}>();

const isEditing = ref(false);
const draftName = ref('');

const dimensions = computed(() =>
  props.item.width !== null && props.item.height !== null
    ? `${props.item.width} × ${props.item.height}`
    : 'размер неизвестен',
);

function onCardClick(): void {
  if (isEditing.value) return;

  emit('select', props.item);
}

function startRename(): void {
  draftName.value = props.item.name;
  isEditing.value = true;
}

function cancelRename(): void {
  isEditing.value = false;
}

function commitRename(): void {
  if (isEditing.value) {
    emit('rename', props.item, draftName.value);
  }
  isEditing.value = false;
}
</script>

<template>
  <v-card
    class="asset-card"
    :class="{ 'asset-card--selected': props.selected }"
    variant="outlined"
    @click="onCardClick"
  >
    <div class="asset-card__thumb">
      <FileImage
        v-if="props.item.filePublicId"
        :public-id="props.item.filePublicId"
        :alt="props.item.name"
        :archived="props.item.isArchived"
        width="100%"
        height="120"
        cover
      />
      <v-icon v-else icon="mdi-image-off-outline" size="48" />
    </div>
    <v-card-text class="pa-2">
      <div v-if="isEditing" class="d-flex align-center ga-1">
        <v-text-field
          v-model="draftName"
          density="compact"
          variant="outlined"
          hide-details
          autofocus
          @click.stop
          @keyup.enter="commitRename"
          @keyup.esc="cancelRename"
        />
        <v-btn icon="mdi-check" size="x-small" variant="text" :disabled="props.busy" @click.stop="commitRename" />
        <v-btn icon="mdi-close" size="x-small" variant="text" @click.stop="cancelRename" />
      </div>
      <div v-else class="asset-card__name text-body-2">{{ props.item.name }}</div>
      <div class="asset-card__meta text-caption">
        {{ dimensions }}<template v-if="props.item.isShared"> · общий</template>
      </div>
    </v-card-text>
    <v-menu v-if="props.canEdit" location="bottom end">
      <template #activator="{ props: menuProps }">
        <v-btn
          class="asset-card__menu"
          icon="mdi-dots-vertical"
          size="x-small"
          variant="text"
          v-bind="menuProps"
          @click.stop
        />
      </template>
      <v-list density="compact">
        <v-list-item prepend-icon="mdi-pencil" title="Переименовать" @click="startRename" />
        <v-list-item prepend-icon="mdi-archive" title="В архив" @click="emit('archive', props.item)" />
      </v-list>
    </v-menu>
  </v-card>
</template>

<style scoped>
.asset-card {
  position: relative;
  cursor: pointer;
  height: 100%;
}

.asset-card--selected {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 2px rgb(var(--v-theme-primary));
}

.asset-card__thumb {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 120px;
  overflow: hidden;
  background: rgba(var(--v-theme-surface-variant), 0.35);
}

.asset-card__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.asset-card__meta {
  opacity: 0.7;
}

.asset-card__menu {
  position: absolute;
  top: 4px;
  right: 4px;
  background: rgba(var(--v-theme-surface), 0.8);
}
</style>
