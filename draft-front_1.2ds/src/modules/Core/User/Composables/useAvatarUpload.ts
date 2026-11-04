import { ref } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import { getFileApi, validateFileSelection } from '@/modules/Core/File/init';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { AVATAR_MAX_SIZE } from '@/modules/Core/User/Constant/Avatar/AVATAR_MAX_SIZE';
import { AVATAR_MIME } from '@/modules/Core/User/Constant/Avatar/AVATAR_MIME';

/** Клиентская сторона аватара: проверка файла, upload в File и `user.setAvatar`. */
export function useAvatarUpload() {
  const store = useUserStore();
  const uploading = ref(false);
  const error = ref<string | null>(null);

  async function upload(file: File): Promise<User | null> {
    const validationError = validateFileSelection(file, { maxSize: AVATAR_MAX_SIZE, accept: AVATAR_MIME });
    error.value = validationError;
    if (validationError) return null;

    uploading.value = true;
    try {
      const record = await getFileApi().upload(file);

      return await store.setAvatar(record.publicId);
    } catch (caught) {
      error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить аватар';

      return null;
    } finally {
      uploading.value = false;
    }
  }

  async function clear(): Promise<User | null> {
    uploading.value = true;
    error.value = null;
    try {
      return await store.setAvatar(null);
    } catch (caught) {
      error.value = caught instanceof Error ? caught.message : 'Не удалось снять аватар';

      return null;
    } finally {
      uploading.value = false;
    }
  }

  return { uploading, error, upload, clear };
}
