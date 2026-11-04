<script setup lang="ts">
import { computed } from 'vue';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import { getFileApi } from '@/modules/Core/File/init';
import { formatFileSize } from '@/modules/Core/File/Service/FormatFileSize';
import FileImage from '@/modules/Core/File/Component/FileImage.vue';

const props = defineProps<{
  modelValue: boolean;
  file: FileRecord | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
}>();

const viewUrl = computed(() =>
  props.file && !props.file.isArchived ? getFileApi().getViewUrl(props.file.publicId) : '',
);

const createdAt = computed(() => (props.file ? new Date(props.file.createdAt * 1000).toLocaleString('ru-RU') : '—'));

const archivedAt = computed(() =>
  props.file?.archivedAt ? new Date(props.file.archivedAt * 1000).toLocaleString('ru-RU') : '—',
);

function close(): void {
  emit('update:modelValue', false);
}
</script>

<template>
  <v-dialog :model-value="props.modelValue" max-width="900" @update:model-value="emit('update:modelValue', $event)">
    <v-card v-if="props.file">
      <v-card-title>Файл {{ props.file.id }}</v-card-title>
      <v-card-text>
        <FileImage
          :public-id="props.file.publicId"
          :archived="props.file.isArchived"
          :alt="props.file.originalName"
          :height="320"
        />
        <v-table density="compact" class="mt-4">
          <tbody>
            <tr>
              <th class="text-left">Имя</th>
              <td>{{ props.file.originalName }}</td>
            </tr>
            <tr>
              <th class="text-left">Public id</th>
              <td class="text-mono">{{ props.file.publicId }}</td>
            </tr>
            <tr>
              <th class="text-left">Владелец</th>
              <td>{{ props.file.ownerId }}</td>
            </tr>
            <tr>
              <th class="text-left">Тип</th>
              <td>{{ props.file.mime }}</td>
            </tr>
            <tr>
              <th class="text-left">Размер</th>
              <td>{{ formatFileSize(props.file.size) }}</td>
            </tr>
            <tr>
              <th class="text-left">Размеры</th>
              <td>{{ props.file.width ?? '—' }} × {{ props.file.height ?? '—' }}</td>
            </tr>
            <tr>
              <th class="text-left">SHA-256</th>
              <td class="text-mono">{{ props.file.sha256 }}</td>
            </tr>
            <tr>
              <th class="text-left">Загружен</th>
              <td>{{ createdAt }}</td>
            </tr>
            <tr>
              <th class="text-left">Архив</th>
              <td>{{ props.file.isArchived ? `Да, ${archivedAt}` : 'Нет' }}</td>
            </tr>
          </tbody>
        </v-table>
        <v-alert v-if="props.file.isArchived" type="info" density="compact" class="mt-4">
          Файл в архиве: метаданные доступны, байты не отдаются.
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-btn v-if="viewUrl" :href="viewUrl" target="_blank" variant="text">Открыть</v-btn>
        <v-spacer />
        <v-btn variant="tonal" @click="close">Закрыть</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.text-mono {
  font-family: monospace;
  word-break: break-all;
}
</style>
