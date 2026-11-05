import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import type { AvatarSource } from '@/modules/Core/User/Dto/AvatarSource';

/** Состояние аватара для диалога: производная, источник и последний кроп. */
export interface AvatarState {
  avatar: string | null;
  source: AvatarSource | null;
  crop: AvatarCrop | null;
}
