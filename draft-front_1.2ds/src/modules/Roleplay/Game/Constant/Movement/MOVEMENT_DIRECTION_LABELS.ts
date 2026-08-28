import type { MovementDirection } from '@/modules/Roleplay/Rule/Dto/Ability/MovementOperation';

export const MOVEMENT_DIRECTION_LABELS: Record<MovementDirection, string> = {
  front: 'Вперёд',
  flank: 'Во фланг',
  rear: 'В тыл',
  up: 'Вверх',
  down: 'Вниз',
};
