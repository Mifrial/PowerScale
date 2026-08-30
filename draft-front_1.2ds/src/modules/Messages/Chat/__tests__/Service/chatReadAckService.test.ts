import { describe, it, expect, vi, afterEach } from 'vitest';
import { ChatReadAckService } from '@/modules/Messages/Chat/Service/ChatReadAckService';

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('ChatReadAckService', () => {
  it('throw → второй markChatRead после backoff', async () => {
    vi.useFakeTimers();
    const markChatRead = vi.fn().mockRejectedValue(new Error('сеть'));
    const onStatus = vi.fn();
    const service = new ChatReadAckService({
      markChatRead,
      onStatus,
      initialBackoffMs: 1000,
      maxBackoffMs: 30000,
    });

    service.request(1);
    await vi.advanceTimersByTimeAsync(0);

    expect(markChatRead).toHaveBeenCalledTimes(1);
    expect(onStatus).toHaveBeenCalledWith(1, { status: 'retrying', lastError: 'сеть' });

    await vi.advanceTimersByTimeAsync(999);
    expect(markChatRead).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1);
    expect(markChatRead).toHaveBeenCalledTimes(2);
    service.disconnect();
  });

  it('in-flight: повторный request — no-op', async () => {
    vi.useFakeTimers();
    let release!: () => void;
    const markChatRead = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          release = resolve;
        }),
    );
    const service = new ChatReadAckService({ markChatRead, initialBackoffMs: 1000 });

    service.request(1);
    await vi.advanceTimersByTimeAsync(0);
    service.request(1);
    expect(markChatRead).toHaveBeenCalledTimes(1);
    release();
    await vi.advanceTimersByTimeAsync(0);
    service.disconnect();
  });

  it('request во время backoff не дублирует тик', async () => {
    vi.useFakeTimers();
    const markChatRead = vi.fn().mockRejectedValue(new Error('сеть'));
    const service = new ChatReadAckService({ markChatRead, initialBackoffMs: 1000 });

    service.request(1);
    await vi.advanceTimersByTimeAsync(0);
    service.request(1);
    expect(markChatRead).toHaveBeenCalledTimes(1);
    service.disconnect();
  });

  it('retryNow не ждёт backoff', async () => {
    vi.useFakeTimers();
    const markChatRead = vi.fn().mockRejectedValue(new Error('сеть'));
    const service = new ChatReadAckService({ markChatRead, initialBackoffMs: 1000 });

    service.request(1);
    await vi.advanceTimersByTimeAsync(0);
    service.retryNow(1);
    await vi.advanceTimersByTimeAsync(0);
    expect(markChatRead).toHaveBeenCalledTimes(2);
    service.disconnect();
  });

  it('retryNow во время in-flight — no-op', async () => {
    vi.useFakeTimers();
    let release!: () => void;
    const markChatRead = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          release = resolve;
        }),
    );
    const service = new ChatReadAckService({ markChatRead, initialBackoffMs: 1000 });

    service.request(1);
    await vi.advanceTimersByTimeAsync(0);
    service.retryNow(1);
    expect(markChatRead).toHaveBeenCalledTimes(1);
    release();
    await vi.advanceTimersByTimeAsync(0);
    service.disconnect();
  });

  it('disconnect во время backoff — больше никаких вызовов', async () => {
    vi.useFakeTimers();
    const markChatRead = vi.fn().mockRejectedValue(new Error('сеть'));
    const service = new ChatReadAckService({ markChatRead, initialBackoffMs: 1000 });

    service.request(1);
    await vi.advanceTimersByTimeAsync(0);
    service.disconnect();
    await vi.advanceTimersByTimeAsync(30000);
    expect(markChatRead).toHaveBeenCalledTimes(1);
  });
});
