import type { SyncResponse } from '../Interface/types'
import type { IChatApi } from '../Interface/IChatApi'

export interface ChatSyncConfig {
  onSync: (data: SyncResponse) => void
  pollInterval?: number
  baseUrl?: string
  getSyncApi?: () => IChatApi
}

export class ChatSyncService {
  private source: EventSource | null = null
  private timer: ReturnType<typeof setInterval> | null = null
  private lastSync = ''

  constructor(private config: ChatSyncConfig) {}

  get lastSyncTimestamp(): string {
    return this.lastSync
  }

  connect(since: string): void {
    this.lastSync = since
    if (this.config.getSyncApi) {
      this.startPolling()
    } else {
      this.startSSE()
    }
  }

  disconnect(): void {
    this.source?.close()
    this.source = null
    if (this.timer !== null) {
      clearInterval(this.timer)
      this.timer = null
    }
  }

  private startPolling(): void {
    const sync = async () => {
      try {
        const res = await this.config.getSyncApi!().sync(this.lastSync)
        this.lastSync = res.now
        this.config.onSync(res)
      } catch {
      }
    }
    sync()
    this.timer = setInterval(sync, this.config.pollInterval ?? 5000)
  }

  private startSSE(): void {
    const url = `${this.config.baseUrl ?? ''}/api/chat/sync?since=${encodeURIComponent(this.lastSync)}`
    this.source = new EventSource(url, { withCredentials: true })
    this.source.addEventListener('sync', (e: MessageEvent) => {
      try {
        const data = JSON.parse(e.data) as SyncResponse
        this.lastSync = data.now
        this.config.onSync(data)
      } catch {
      }
    })
  }
}
