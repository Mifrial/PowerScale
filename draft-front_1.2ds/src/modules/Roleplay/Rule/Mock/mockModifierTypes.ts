import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemModifierTypeSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierTypeSpec';

function typeRule(index: number, code: string, name: string, description: string): Rule {
  const spec: ItemModifierTypeSpec = { exclusive: true };

  return {
    id: `rule-${840 + index}`,
    code,
    type: 'item_modifier_type',
    name,
    description,
    spaceId: 1,
    spec,
    keywordIds: [],
    mechanicId: null,
    createdAt: '2026-08-22T10:00:00Z',
  };
}

/** Типы модификаторов предметов (R29). Все из каталога — exclusive. */
export const mockModifierTypes: Rule[] = [
  typeRule(0, 'craft-quality', 'Качество изделия', 'Качество изготовления оружия или доспеха.'),
  typeRule(1, 'material-quality', 'Качество материала', 'Качество материала оружия, щита или доспеха.'),
  typeRule(2, 'item-weight', 'Вес', 'Утяжеление или облегчение предмета.'),
  typeRule(3, 'balance', 'Баланс', 'Баланс оружия (рукоять / наконечник).'),
  typeRule(4, 'coating', 'Покрытие', 'Покрытие предмета (посеребрение).'),
  typeRule(5, 'material', 'Материал', 'Материал изделия: серебро, золото, кожи.'),
  typeRule(6, 'ferrule', 'Оковка', 'Оковка посоха.'),
  typeRule(7, 'kit', 'Комплектация', 'Комплектация доспеха.'),
  typeRule(8, 'insulation', 'Утепление', 'Утепление доспеха.'),
];
