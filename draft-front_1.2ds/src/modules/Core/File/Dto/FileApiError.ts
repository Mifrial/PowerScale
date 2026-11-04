/** Типизированный отказ Core/File с кодом конверта. */
export class FileApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details?: unknown,
  ) {
    super(message);
    this.name = 'FileApiError';
  }
}
