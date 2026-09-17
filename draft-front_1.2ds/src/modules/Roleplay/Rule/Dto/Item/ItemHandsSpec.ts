/** Сколько слотов рук занимает предмет в покое и на действии. */
export interface ItemHandsSpec {
  min: number;
  max: number;
  /** Занятость на действии (лук: выстрел 2 при покое 1). Нет — как текущий покой. */
  action?: number;
}
