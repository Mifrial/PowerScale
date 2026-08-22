import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import { GAME_STATUS_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameStatus/GAME_STATUS_OPTIONS';
import { GAME_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameVisibility/GAME_VISIBILITY_OPTIONS';

export const filterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  { key: 'status', label: 'Статус', type: 'select', options: GAME_STATUS_OPTIONS },
  { key: 'ownerName', label: 'Владелец', type: 'string' },
  { key: 'visibility', label: 'Видимость', type: 'select', options: GAME_VISIBILITY_OPTIONS },
];
