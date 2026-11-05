import { FileApiError } from '@/modules/Core/File/Dto/FileApiError';
import { UserApiError } from '@/modules/Core/User/Dto/UserApiError';
import { AVATAR_ERROR_MESSAGES } from '@/modules/Core/User/Constant/Avatar/AVATAR_ERROR_MESSAGES';

/** Переводит отказ API в понятную пользователю подсказку. */
export class AvatarErrorService {
  describe(error: unknown, fallback: string): string {
    if (error instanceof UserApiError || error instanceof FileApiError) {
      return AVATAR_ERROR_MESSAGES[error.code] ?? fallback;
    }

    return error instanceof Error ? error.message : fallback;
  }
}
