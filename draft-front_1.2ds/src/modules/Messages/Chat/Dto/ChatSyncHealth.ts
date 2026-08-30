export interface ChatSyncHealth {
  status: 'ok' | 'retrying';
  lastError: string | null;
}
