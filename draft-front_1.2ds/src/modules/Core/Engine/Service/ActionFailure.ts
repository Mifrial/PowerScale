/**
 * Отказ action с машиночитаемым кодом и полями ошибки.
 */
export class ActionFailure extends Error {
  /**
   * @param code Код ошибки action.
   * @param message Текст для человека.
   * @param details Дополнительные поля конверта.
   */
  constructor(
    readonly code: string,
    message: string,
    readonly details?: unknown,
  ) {
    super(message);
    this.name = 'ActionFailure';
  }
}
