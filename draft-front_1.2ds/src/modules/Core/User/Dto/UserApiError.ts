/** Типизированный отказ Core/User с кодом конверта. */
export class UserApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details?: unknown,
  ) {
    super(message);
    this.name = 'UserApiError';
  }
}
