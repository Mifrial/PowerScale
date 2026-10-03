/** Машиночитаемая ошибка action. */
export interface ActionError {
  code: string;
  message: string;
  /** Машиночитаемые дополнительные данные доменной ошибки. */
  details?: unknown;
}
