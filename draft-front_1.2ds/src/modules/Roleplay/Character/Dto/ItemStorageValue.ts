import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/**
 * Предмет-хранилище магии в листе (`sheet.items`). Мана — рантайм сервера:
 * клиент её только показывает, тратит и восстанавливает сервер.
 */
export interface ItemStorageValue {
  inventoryId: number;
  ruleCode: string;
  /** Предельная мощь: выше неё кристалл не кастует. */
  maxPower: DimensionalNumberValue;
  /** Объём: купленная ступень 20/30/40… */
  volume: number;
  /** Занято записями заклинаний и улучшений. */
  occupied: number;
  /** Текущая мана. */
  mana: number;
  /** Лимит маны: Объём минус занятый Объём. */
  manaMax: number;
}
