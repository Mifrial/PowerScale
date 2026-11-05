import { ref } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import type { AvatarSource } from '@/modules/Core/User/Dto/AvatarSource';
import { getFileApi, validateFileSelection } from '@/modules/Core/File/init';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { avatarErrorService } from '@/modules/Core/User/Service/Instance/avatarErrorService';
import { AVATAR_MAX_SIZE } from '@/modules/Core/User/Constant/Avatar/AVATAR_MAX_SIZE';
import { AVATAR_MIME } from '@/modules/Core/User/Constant/Avatar/AVATAR_MIME';

/** Клиентская сторона аватара: проверка файла, upload в File и `user.setAvatar`. */
export function useAvatarUpload() {
  const store = useUserStore();
  const uploading = ref(false);
  const error = ref<string | null>(null);

  async function uploadFile(file: File): Promise<AvatarSource | null> {
    const validationError = validateFileSelection(file, { maxSize: AVATAR_MAX_SIZE, accept: AVATAR_MIME });
    error.value = validationError;
    if (validationError) return null;

    uploading.value = true;
    try {
      const record = await getFileApi().upload(file);

      return {
        publicId: record.publicId,
        originalName: record.originalName,
        width: record.width,
        height: record.height,
      };
    } catch (caught) {
      error.value = avatarErrorService.describe(caught, 'Не удалось загрузить файл');

      return null;
    } finally {
      uploading.value = false;
    }
  }

  async function applyCrop(filePublicId: string, crop: AvatarCrop): Promise<User | null> {
    uploading.value = true;
    error.value = null;
    try {
      return await store.setAvatar(filePublicId, crop);
    } catch (caught) {
      error.value = avatarErrorService.describe(caught, 'Не удалось сохранить аватар');

      return null;
    } finally {
      uploading.value = false;
    }
  }

  async function clear(): Promise<User | null> {
    uploading.value = true;
    error.value = null;
    try {
      return await store.setAvatar(null, null);
    } catch (caught) {
      error.value = avatarErrorService.describe(caught, 'Не удалось снять аватар');

      return null;
    } finally {
      uploading.value = false;
    }
  }

  return { uploading, error, uploadFile, applyCrop, clear };
}
