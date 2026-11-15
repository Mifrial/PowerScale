export interface SpellCastPathOption {
  pathCode: string;
  name: string;
  checkCode: string | null;
  castCheckCode: string | null;
  powerCharacteristicCode: string | null;
  controlCharacteristicCode: string | null;
  /** Путь не использует Контроль: сравнение только по мощи. */
  noControl: boolean;
  /** Спека пути кастует из записей источника. */
  recordedCasting: boolean;
}
