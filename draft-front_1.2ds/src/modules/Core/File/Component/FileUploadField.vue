<script setup lang="ts">
import { computed, ref } from 'vue';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import { FileApiError } from '@/modules/Core/File/Dto/FileApiError';
import { getFileApi } from '@/modules/Core/File/init';
import { formatFileSize } from '@/modules/Core/File/Service/FormatFileSize';
import { validateFileSelection } from '@/modules/Core/File/Service/ValidateFileSelection';
import FileImage from '@/modules/Core/File/Component/FileImage.vue';

const props = withDefaults(
  defineProps<{
    modelValue: FileRecord | null;
    label?: string;
    maxSize?: number;
    accept?: string[];
    disabled?: boolean;
  }>(),
  { label: 'Файл', maxSize: undefined, accept: () => [], disabled: false },
);

const emit = defineEmits<{
  'update:modelValue': [value: FileRecord | null];
}>();

const uploading = ref(false);
const error = ref<string | null>(null);
const lastFile = ref<File | null>(null);
const acceptAttribute = computed(() => (props.accept.length > 0 ? props.accept.join(',') : undefined));

async function onFilePicked(value: File | File[] | null): Promise<void> {
  const file = Array.isArray(value) ? value[0] : value;
  if (!file) return;

  await upload(file);
}

async function upload(file: File): Promise<void> {
  lastFile.value = null;
  const validationError = validateFileSelection(file, {
    maxSize: props.maxSize,
    accept: props.accept,
  });
  error.value = validationError;
  if (validationError) return;

  lastFile.value = file;
  uploading.value = true;
  try {
    emit('update:modelValue', await getFileApi().upload(file));
  } catch (caught) {
    error.value = caught instanceof FileApiError ? caught.message : 'Не удалось загрузить файл';
  } finally {
    uploading.value = false;
  }
}

function retryUpload(): void {
  if (lastFile.value) {
    void upload(lastFile.value);

    return;
  }

  error.value = null;
}
</script>

<template>
  <div class="file-upload">
    <v-file-input
      :model-value="null"
      :label="props.label"
      :accept="acceptAttribute"
      :disabled="props.disabled || uploading"
      :loading="uploading"
      prepend-icon="mdi-paperclip"
      variant="outlined"
      density="comfortable"
      hide-details="auto"
      @update:model-value="onFilePicked"
    />
    <v-alert v-if="error" type="error" density="compact" class="mt-2">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" :disabled="uploading" @click="retryUpload">Повторить</v-btn>
      </template>
    </v-alert>
    <div v-if="props.modelValue" class="file-upload__current mt-2">
      <FileImage
        :public-id="props.modelValue.publicId"
        :archived="props.modelValue.isArchived"
        width="96"
        height="64"
      />
      <div class="text-body-2">{{ props.modelValue.originalName }} · {{ formatFileSize(props.modelValue.size) }}</div>
      <v-btn
        size="small"
        variant="text"
        icon="mdi-close"
        aria-label="Убрать файл"
        :disabled="uploading"
        @click="emit('update:modelValue', null)"
      />
    </div>
  </div>
</template>

<style scoped>
.file-upload__current {
  display: flex;
  align-items: center;
  gap: 12px;
}
</style>
