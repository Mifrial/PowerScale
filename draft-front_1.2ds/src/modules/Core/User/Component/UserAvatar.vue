<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import { initials as userInitials } from '@/modules/Core/User/Utils/initials';

const FileImage = defineAsyncComponent(() => import('@/modules/Core/File/Component/FileImage.vue'));

const props = withDefaults(
  defineProps<{
    user?: User | null;
    name?: string;
    surname?: string;
    avatar?: string | null;
    size?: number;
    color?: string;
  }>(),
  { user: null, name: '', surname: '', avatar: null, size: 36, color: 'primary' },
);

const imageFailed = ref(false);
const avatarId = computed(() => props.user?.avatar ?? props.avatar ?? null);
const letters = computed(() => userInitials(props.user?.name ?? props.name, props.user?.surname ?? props.surname));
const fontSize = computed(() => Math.max(10, Math.round(props.size * 0.38)));

watch(avatarId, () => {
  imageFailed.value = false;
});
</script>

<template>
  <div v-if="avatarId && !imageFailed" class="user-avatar" :style="{ width: `${size}px`, height: `${size}px` }">
    <FileImage
      :public-id="avatarId"
      alt="Аватар"
      :width="size"
      :height="size"
      cover
      class="user-avatar__image"
      @error="imageFailed = true"
    />
  </div>
  <v-avatar v-else :color="color" :size="size" class="user-avatar">
    <span class="font-weight-medium text-white" :style="{ fontSize: `${fontSize}px` }">{{ letters }}</span>
  </v-avatar>
</template>

<style scoped>
.user-avatar {
  display: inline-flex;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 50%;
}

.user-avatar__image {
  display: block;
  border-radius: 50%;
}
</style>
