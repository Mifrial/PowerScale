/** Типизированный отказ Library/Asset с кодом конверта. */
export class AssetApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details?: unknown,
  ) {
    super(message);
    this.name = 'AssetApiError';
  }
}
