/**
 * Спека характеристики (type='characteristic'): формула производных.
 * Данные как есть: редактор эмитит `{ type: 'characteristic', formula: null }`
 * для непроизводной характеристики; `type` внутри спеки дублирует Rule.type (исторически).
 */
export interface CharacteristicSpec {
  type: 'characteristic'
  formula?: string | null
}
