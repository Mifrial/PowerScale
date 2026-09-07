/** Опции набора активных механик. */
export interface ResolveActiveOptions {
  /** Коды механик «всегда в силе» (срез ревизии фильтруется по ним; undefined — все). */
  includeCodes?: string[];
  /** Коды правил пер-ролл активации (добавляются независимо от includeCodes). */
  extraRuleCodes?: string[];
}
