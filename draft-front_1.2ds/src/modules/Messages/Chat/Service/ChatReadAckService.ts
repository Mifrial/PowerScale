import type { ChatReadAckConfig } from '@/modules/Messages/Chat/Dto/ChatReadAckConfig';
import type { ChatSyncHealth } from '@/modules/Messages/Chat/Dto/ChatSyncHealth';
import { READ_ACK_INITIAL_BACKOFF_MS } from '@/modules/Messages/Chat/Constant/Chat/READ_ACK_INITIAL_BACKOFF_MS';
import { READ_ACK_MAX_BACKOFF_MS } from '@/modules/Messages/Chat/Constant/Chat/READ_ACK_MAX_BACKOFF_MS';

interface ChatReadAckSlot {
  generation: number;
  inFlight: boolean;
  timer: ReturnType<typeof setTimeout> | null;
  nextBackoffMs: number;
}

export class ChatReadAckService {
  private readonly slots = new Map<number, ChatReadAckSlot>();
  private readonly initialBackoffMs: number;
  private readonly maxBackoffMs: number;

  constructor(private readonly config: ChatReadAckConfig) {
    this.initialBackoffMs = config.initialBackoffMs ?? READ_ACK_INITIAL_BACKOFF_MS;
    this.maxBackoffMs = config.maxBackoffMs ?? READ_ACK_MAX_BACKOFF_MS;
  }

  request(chatId: number): void {
    const slot = this.ensureSlot(chatId);
    if (slot.inFlight || slot.timer !== null) return;
    void this.tick(chatId);
  }

  retryNow(chatId: number): void {
    const slot = this.ensureSlot(chatId);
    if (slot.inFlight) return;
    slot.generation += 1;
    slot.nextBackoffMs = this.initialBackoffMs;
    this.clearTimer(slot);
    void this.tick(chatId);
  }

  disconnect(): void {
    for (const slot of this.slots.values()) {
      slot.generation += 1;
      slot.inFlight = false;
      this.clearTimer(slot);
    }
    this.slots.clear();
  }

  private ensureSlot(chatId: number): ChatReadAckSlot {
    let slot = this.slots.get(chatId);
    if (!slot) {
      slot = {
        generation: 0,
        inFlight: false,
        timer: null,
        nextBackoffMs: this.initialBackoffMs,
      };
      this.slots.set(chatId, slot);
    }

    return slot;
  }

  private clearTimer(slot: ChatReadAckSlot): void {
    if (slot.timer !== null) {
      clearTimeout(slot.timer);
      slot.timer = null;
    }
  }

  private emitStatus(chatId: number, health: ChatSyncHealth): void {
    this.config.onStatus?.(chatId, health);
  }

  private scheduleBackoff(chatId: number, slot: ChatReadAckSlot): void {
    const delayMs = slot.nextBackoffMs;
    slot.nextBackoffMs = Math.min(this.maxBackoffMs, slot.nextBackoffMs * 2);
    const generation = slot.generation;
    slot.timer = setTimeout(() => {
      slot.timer = null;
      if (generation !== slot.generation) return;
      void this.tick(chatId);
    }, delayMs);
  }

  private async tick(chatId: number): Promise<void> {
    const slot = this.slots.get(chatId);
    if (!slot || slot.inFlight) return;
    const generation = slot.generation;
    slot.inFlight = true;
    try {
      await this.config.markChatRead(chatId);
      if (generation !== slot.generation) return;
      slot.nextBackoffMs = this.initialBackoffMs;
      this.emitStatus(chatId, { status: 'ok', lastError: null });
    } catch (error) {
      if (generation !== slot.generation) return;
      const message = error instanceof Error ? error.message : 'Не удалось сохранить отметку прочтения';
      this.emitStatus(chatId, { status: 'retrying', lastError: message });
      this.scheduleBackoff(chatId, slot);
    } finally {
      if (generation === slot.generation) slot.inFlight = false;
    }
  }
}
