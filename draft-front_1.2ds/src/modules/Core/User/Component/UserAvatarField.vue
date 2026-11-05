<script setup lang="ts">
import type { User } from '@/modules/Core/User/Dto/User';
import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import { useAvatarEditor } from '@/modules/Core/User/Composables/useAvatarEditor';
import UserAvatar from '@/modules/Core/User/Component/UserAvatar.vue';
import UserAvatarDialog from '@/modules/Core/User/Component/UserAvatarDialog.vue';

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

const {
  open,
  step,
  stateLoading,
  source,
  initialCrop,
  sources,
  sourcesTotal,
  sourcesLoading,
  uploading,
  error,
  start,
  startUpload,
  pick,
  back,
  loadMore,
  apply,
  clearAvatar,
} = useAvatarEditor();

async function onApply(crop: AvatarCrop): Promise<void> {
  const updated = await apply(crop);
  if (updated) emit('updated', updated);
}

async function onClear(): Promise<void> {
  const updated = await clearAvatar();
  if (updated) emit('updated', updated);
}
</script>

<template>
  <div class="avatar-field text-center">
    <UserAvatar :user="props.user" :size="props.size" class="mb-2" />

    <div v-if="canEdit">
      <v-btn size="small" variant="tonal" :loading="uploading" @click="start">
        <v-icon start size="small">mdi-camera-outline</v-icon>Изменить
      </v-btn>
      <v-btn v-if="user.avatar" size="small" variant="text" :disabled="uploading" @click="onClear">Снять</v-btn>
    </div>

    <div v-if="error" class="text-caption text-error mt-1">{{ error }}</div>

    <UserAvatarDialog
      v-model="open"
      :step="step"
      :source="source"
      :initial-crop="initialCrop"
      :sources="sources"
      :sources-total="sourcesTotal"
      :state-loading="stateLoading"
      :sources-loading="sourcesLoading"
      :uploading="uploading"
      :error="error"
      @upload="startUpload"
      @pick="pick"
      @apply="onApply"
      @back="back"
      @load-more="loadMore"
    />
  </div>
</template>
