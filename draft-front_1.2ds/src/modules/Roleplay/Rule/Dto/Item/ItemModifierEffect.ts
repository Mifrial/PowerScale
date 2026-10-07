import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';

/** Эффект модификатора: текст карточки. Числа задаёт `operations`, не подпись абзаца. */
export interface ItemModifierEffect {
  /** Метка (например «Оружие» / «Щит» / «Доспех»), когда одно правило варьирует эффекты по типам. */
  label?: string | null;
  text: string;
  ops?: ItemModifierOp[];
}
