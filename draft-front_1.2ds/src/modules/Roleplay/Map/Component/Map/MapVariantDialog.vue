<script setup lang="ts">
import { computed, defineAsyncComponent, reactive, ref, watch } from 'vue';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapVariantEdit } from '@/modules/Roleplay/Map/Dto/MapVariantEdit';

const FileUploadField = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileUploadField.vue'));

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
  variant: MapVariant | null;
  canDelete: boolean;
  submitting: boolean;
  suggestedMillimetersPerPixel: number | null;
}>();

const emit = defineEmits<{
  submit: [edit: MapVariantEdit];
  remove: [variantId: number];
}>();

const form = reactive({
  label: '',
  fromMinutes: null as number | null,
  sortOrder: 0,
  millimetersPerPixel: null as number | null,
  isDefault: false,
  isBaseFileTouched: false,
});

const file = ref<FileRecord | null>(null);
const baseFilePublicId = ref<string | null>(null);
const baseFileSize = ref<{ width: number | null; height: number | null }>({ width: null, height: null });

const fromMinutesModel = computed({
  get: () => form.fromMinutes,
  set: (value: number | string | null) => {
    form.fromMinutes = value === null || value === '' ? null : Number(value);
  },
});

const scaleModel = computed({
  get: () => form.millimetersPerPixel,
  set: (value: number | string | null) => {
    form.millimetersPerPixel = value === null || value === '' ? null : Number(value);
  },
});

const imageLimits = { accept: ['image/png', 'image/jpeg', 'image/webp'] };

watch(open, (value) => {
  if (!value) return;
  form.label = props.variant?.label ?? 'Основная';
  form.fromMinutes = props.variant?.fromMinutes ?? null;
  form.sortOrder = props.variant?.sortOrder ?? 0;
  form.millimetersPerPixel = props.suggestedMillimetersPerPixel ?? props.variant?.millimetersPerPixel ?? null;
  form.isDefault = props.variant?.isDefault ?? false;
  form.isBaseFileTouched = false;
  file.value = null;
  baseFilePublicId.value = props.variant?.baseFile?.publicId ?? null;
  baseFileSize.value = {
    width: props.variant?.baseFile?.width ?? null,
    height: props.variant?.baseFile?.height ?? null,
  };
});

watch(file, (value) => {
  if (value) {
    form.isBaseFileTouched = true;
    baseFilePublicId.value = value.publicId;
    baseFileSize.value = { width: value.width, height: value.height };
  }
});

function clearBaseFile(): void {
  file.value = null;
  form.isBaseFileTouched = true;
  baseFilePublicId.value = null;
  baseFileSize.value = { width: null, height: null };
}

function submit(): void {
  const label = form.label.trim();
  if (label === '') return;
  emit('submit', {
    label,
    fromMinutes: form.fromMinutes,
    sortOrder: form.sortOrder,
    filePublicId: baseFilePublicId.value,
    fileWidth: baseFileSize.value.width,
    fileHeight: baseFileSize.value.height,
    isBaseFileTouched: form.isBaseFileTouched,
    millimetersPerPixel: form.millimetersPerPixel,
    isDefault: form.isDefault,
  });
}

function remove(): void {
  if (props.variant) emit('remove', props.variant.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="560" :persistent="props.submitting">
    <v-card>
      <v-card-title class="text-body-1 font-weight-medium">
        {{ props.variant ? 'Вариант карты' : 'Новый вариант' }}
      </v-card-title>
      <v-card-text>
        <v-text-field v-model="form.label" label="Подпись эпохи" variant="outlined" density="comfortable" />
        <v-row dense class="mt-3">
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="fromMinutesModel"
              type="number"
              label="Смещение от эпохи, мин"
              variant="outlined"
              density="comfortable"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.sortOrder"
              type="number"
              min="0"
              label="Порядок"
              variant="outlined"
              density="comfortable"
            />
          </v-col>
        </v-row>
        <v-text-field
          v-model="scaleModel"
          type="number"
          min="1"
          label="Миллиметров на пиксель"
          variant="outlined"
          density="comfortable"
          class="mt-3"
          hint="Пусто — масштаб не задан, расстояния придётся указывать у рёбер"
          persistent-hint
        />
        <FileUploadField v-model="file" label="Подложка (png, jpeg, webp)" :accept="imageLimits.accept" class="mt-3" />
        <div class="d-flex align-center ga-2 mt-2">
          <span class="text-caption text-medium-emphasis">
            {{ baseFilePublicId ? 'Подложка задана' : 'Подложки нет' }}
          </span>
          <v-btn v-if="baseFilePublicId" size="small" variant="text" color="error" @click="clearBaseFile">
            Убрать
          </v-btn>
        </div>
        <v-switch v-model="form.isDefault" label="Вариант по умолчанию" color="primary" density="compact" />
      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="props.canDelete"
          color="error"
          variant="text"
          :disabled="props.submitting"
          prepend-icon="mdi-delete-outline"
          @click="remove"
        >
          Удалить
        </v-btn>
        <v-spacer />
        <v-btn variant="text" :disabled="props.submitting" @click="open = false">Отмена</v-btn>
        <v-btn color="primary" variant="tonal" :loading="props.submitting" @click="submit">Применить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
