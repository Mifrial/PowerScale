import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';

/** Правка группы: новые поля участников и, отдельно, подпись самой группы. */
export interface MapGroupEdit {
  /** Новая подпись группы; null — не менять. */
  groupLabel: string | null;
  /** Поля участников; пусто — не менять. */
  fields: {
    color?: string;
    /** Цвет границы `#rrggbb`; пусто — границы нет. */
    stroke?: string;
    /** Толщина границы, экранные пиксели. */
    strokeWidth?: number;
    speed?: number;
    label?: string;
    /** Частичный декор: поля накладываются поверх текущего. null — очистить. */
    decor?: Partial<MapAreaDecor> | null;
  };
}
