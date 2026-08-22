/** Закупленная характеристика (режим purchased расы): код + потраченные ОС. */
export interface CharacteristicPurchase {
  characteristicCode: string;
  /** Потраченные ОС (0 = не закуплена; ищется в лестнице покупки расы). */
  cost: number;
}
