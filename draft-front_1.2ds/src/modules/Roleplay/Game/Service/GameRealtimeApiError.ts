/** Ошибка канала доставки Game. */
export class GameRealtimeApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
  ) {
    super(message);
    this.name = 'GameRealtimeApiError';
  }
}
