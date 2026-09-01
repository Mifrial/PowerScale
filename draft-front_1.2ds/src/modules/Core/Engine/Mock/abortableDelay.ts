/**
 * Задержка, которую можно отменить через AbortSignal (для mock API).
 *
 * @param ms Длительность в миллисекундах.
 * @param signal Сигнал отмены.
 */
export function abortableDelay(ms = 150, signal?: AbortSignal): Promise<void> {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      if (signal?.aborted) {
        reject(new DOMException('Aborted', 'AbortError'));
      } else {
        resolve();
      }
    }, ms);

    signal?.addEventListener(
      'abort',
      () => {
        clearTimeout(timer);
        reject(new DOMException('Aborted', 'AbortError'));
      },
      { once: true },
    );
  });
}
