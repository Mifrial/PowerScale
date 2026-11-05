<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import type { AvatarSource } from '@/modules/Core/User/Dto/AvatarSource';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { AvatarEditorStep } from '@/modules/Core/User/Composables/useAvatarEditor';
import { getFileApi } from '@/modules/Core/File/init';
import AvatarCropStage from '@/modules/Core/User/Component/AvatarCropStage.vue';

const open = defineModel<boolean>({ default: false });

const props = withDefaults(
  defineProps<{
    step: AvatarEditorStep;
    source?: AvatarSource | null;
    initialCrop?: AvatarCrop | null;
    sources?: FileRecord[];
    sourcesLoading?: boolean;
    uploading?: boolean;
    error?: string | null;
  }>(),
  { source: null, initialCrop: null, sources: () => [], sourcesLoading: false, uploading: false, error: null },
);

const emit = defineEmits<{
  upload: [file: File];
  pick: [record: FileRecord];
  apply: [crop: AvatarCrop];
  back: [];
}>();

const crop = ref<AvatarCrop | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const sourceUrl = computed(() => (props.source ? viewUrl(props.source.publicId) : ''));

watch(
  () => props.source?.publicId,
  () => {
    crop.value = null;
  },
);

function viewUrl(publicId: string): string {
  return getFileApi().getViewUrl(publicId);
}

function pickFile(): void {
  fileInput.value?.click();
}

function onFilePicked(event: Event): void {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (file) emit('upload', file);
}
</script>

<template>
  <v-dialog v-model="open" max-width="460" :persistent="uploading">
    <v-card>
      <v-card-title>{{ step === 'crop' ? 'Выберите кружок' : 'Аватар' }}</v-card-title>

      <v-card-text>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>

        <template v-if="step === 'crop' && source">
          <AvatarCropStage :src="sourceUrl" :initial-crop="initialCrop" @change="crop = $event" />
          <div class="text-caption text-medium-emphasis mt-1">Двигайте фото и меняйте масштаб</div>
        </template>

        <template v-else>
          <input
            ref="fileInput"
            type="file"
            class="d-none"
            accept="image/png,image/jpeg,image/webp"
            @change="onFilePicked"
          />
          <v-btn block variant="tonal" :loading="uploading" @click="pickFile">
            <v-icon start size="small">mdi-upload</v-icon>Загрузить фото
          </v-btn>

          <div class="text-caption text-medium-emphasis mt-4 mb-2">Или выберите из загруженных</div>
          <div v-if="sourcesLoading" class="d-flex justify-center pa-4">
            <v-progress-circular indeterminate width="2" size="24" color="primary" />
          </div>
          <div v-else-if="sources.length > 0" class="avatar-source-grid">
            <button
              v-for="record in sources"
              :key="record.publicId"
              type="button"
              class="avatar-source"
              :title="record.originalName"
              @click="emit('pick', record)"
            >
              <img :src="viewUrl(record.publicId)" :alt="record.originalName" />
            </button>
          </div>
          <div v-else class="text-caption text-medium-emphasis">У вас пока нет загруженных изображений</div>
        </template>
      </v-card-text>

      <v-card-actions>
        <template v-if="step === 'crop'">
          <v-btn variant="text" :disabled="uploading" @click="emit('back')">Другой файл</v-btn>
          <v-spacer />
          <v-btn color="primary" :loading="uploading" :disabled="!crop" @click="crop && emit('apply', crop)">
            Применить
          </v-btn>
        </template>
        <template v-else>
          <v-spacer />
          <v-btn variant="text" @click="open = false">Закрыть</v-btn>
        </template>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.avatar-source-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  max-height: 220px;
  overflow-y: auto;
}

.avatar-source {
  aspect-ratio: 1;
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  background: rgb(var(--v-theme-surface-variant));
  border: 2px solid transparent;
  border-radius: 8px;
}

.avatar-source:hover {
  border-color: rgb(var(--v-theme-primary));
}

.avatar-source img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
