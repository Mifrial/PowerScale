/** Пункт блока «Разное»: очки (ОС/ОЛ/ОР) или деньги. */
export interface MiscItemOverview {
  code: 'os' | 'ol' | 'or' | 'money';
  label: string;
  /** Основное значение: есть (ОЛ/ОР), потрачено при создании (ОС), сумма (деньги). */
  valueLabel: string;
  /** Уточнение: «потрачено X / всего Y» (ОЛ/ОР), «потрачено при создании» (ОС). */
  subtitle: string | null;
}
