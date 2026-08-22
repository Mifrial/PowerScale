import type { GameJoinRequestStatus } from '@/modules/Roleplay/Game/Enum/GameJoinRequestStatus';

/** Подписи статусов заявок на вступление. */
export const GAME_JOIN_REQUEST_STATUS_LABEL: Record<GameJoinRequestStatus, string> = {
  pending: 'На рассмотрении',
  accepted: 'Принята',
  declined: 'Отклонена',
};

/** Цвета статусов заявок на вступление для Vuetify. */
export const GAME_JOIN_REQUEST_STATUS_COLOR: Record<GameJoinRequestStatus, string> = {
  pending: 'info',
  accepted: 'success',
  declined: 'error',
};
