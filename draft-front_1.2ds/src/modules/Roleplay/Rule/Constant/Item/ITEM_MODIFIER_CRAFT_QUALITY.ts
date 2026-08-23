/** Тип модификатора «Качество изделия»; на него действуют keywords изготовления. */
export const ITEM_MODIFIER_CRAFT_QUALITY_TYPE = 'craft-quality';

export const ITEM_MODIFIER_IMPROVISED_CODE = 'improvised';

/** Множители цены craft-quality с предмета (очень трудное важнее трудного). */
export const ITEM_MODIFIER_CRAFT_KEYWORD_FACTOR: Record<string, number> = {
  'very-hard-to-craft': 4,
  'hard-to-craft': 2,
  'easy-to-craft': 0.5,
};
