import type { ActionCost } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCost';
import type { MaterialMode } from '@/modules/Roleplay/Rule/Enum/Ability/MaterialMode';

/** Компонент действия — всё расходуемое и используемое для совершения действия.
 * `resource` — траты ресурсов (в т.ч. ОД); `verbal`/`somatic`/`material` — прочие требования.
 * Термин «компоненты заклинания» — лишь группировка не-ОД затрат в карточке заклинания. */
export type ActionComponent =
  | ({ type: 'resource' } & ActionCost)
  | { type: 'verbal'; note?: string }
  | { type: 'somatic'; note?: string }
  | {
      type: 'material';
      mode: MaterialMode;
      item_code?: string;
      keyword_codes?: string[];
      description?: string;
    };
