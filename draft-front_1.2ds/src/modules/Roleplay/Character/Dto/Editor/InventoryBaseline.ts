import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';

/** Базовая линия «Инвентаря» (R2): снапшот на момент входа на шаг. */
export interface InventoryBaseline {
  inventory: InventoryItem[];
  money: number;
}
