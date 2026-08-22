/**
 * Тип модификатора предмета — правило `item_modifier_type`.
 * Модификаторы ссылаются на него через `ItemModifierSpec.type_code`.
 */
export interface ItemModifierTypeSpec {
  /** true — на предмете не больше одного модификатора этого типа. */
  exclusive: boolean;
}
