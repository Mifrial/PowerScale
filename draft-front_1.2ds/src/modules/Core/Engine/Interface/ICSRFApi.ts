export interface ICSRFApi {
  initToken(): Promise<void>;
  getToken(): string | null;
}
