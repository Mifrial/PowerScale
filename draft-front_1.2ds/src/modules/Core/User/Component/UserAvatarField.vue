<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import { useAvatarUpload } from '@/modules/Core/User/Composables/useAvatarUpload';
import { initials as userInitials } from '@/modules/Core/User/Utils/initials';

const FileImage = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileImage.vue'));

const props = withDefaults(
  defineProps<{
    user: User;
    canEdit?: boolean;
    size?: number;
  }>(),
  { canEdit: false, size: 120 },
);

const emit = defineEmits<{
  updated: [value: User];
}>();

const { uploading, error, upload, clear } = useAvatarUpload();
const fileInput = ref<HTMLInputElement | null>(null);

const initials = computed(() => userInitials(props.user.name, props.user.surname));

function pickFile(): void {
  fileInput.value?.click();
}

async function onFilePicked(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;

  const updated = await upload(file);
  if (updated) emit('updated', updated);
}

async function onClear(): Promise<void> {
  const updated = await clear();
  if (updated) emit('updated', updated);
}
</script>

<template>
  <div class="avatar-field text-center">
    <FileImage
      v-if="user.avatar"
      :public-id="user.avatar"
      alt="Аватар"
      :width="props.size"
      :height="props.size"
      cover
      class="avatar-field__image"
    />
    <v-avatar v-else color="primary" :size="props.size" class="mb-2">
      <span class="text-h3 font-weight-medium text-white">{{ initials }}</span>
    </v-avatar>

    <div v-if="canEdit" class="mt-2">
      <input
        ref="fileInput"
        type="file"
        class="d-none"
        accept="image/png,image/jpeg,image/webp"
        @change="onFilePicked"
      />
      <v-btn size="small" variant="tonal" :loading="uploading" @click="pickFile">
        <v-icon start size="small">mdi-upload</v-icon>Загрузить
      </v-btn>
      <v-btn v-if="user.avatar" size="small" variant="text" :disabled="uploading" @click="onClear"> Снять </v-btn>
    </div>

    <div v-if="error" class="text-caption text-error mt-1">{{ error }}</div>
    <div v-else-if="canEdit" class="text-caption text-medium-emphasis mt-1">
      PNG, JPEG или WebP до 2 МБ; сервер уменьшит до 512×512
    </div>
  </div>
</template>

<style scoped>
.avatar-field__image {
  border-radius: 50%;
  overflow: hidden;
  margin: 0 auto 8px;
}
</style>
