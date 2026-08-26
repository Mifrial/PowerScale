import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';

/** Эффект модификатора: текст для UI + структурные ops на спек предмета. */
export interface ItemModifierEffect {
  /** Метка (например «Оружие» / «Щит» / «Доспех»), когда одно правило варьирует эффекты по типам. */
  label?: string | null;
  text: string;
  ops?: ItemModifierOp[];
}
