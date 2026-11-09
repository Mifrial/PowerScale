/** Выбранное улучшение каста для серверного запроса. */
export interface GamePhpSpellCastUpgrade {
  code: string;
  /** Только для улучшения с resistance_penetration_per_step. */
  value?: number;
}
