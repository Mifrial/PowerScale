const closers = new Set<() => void>();

/** Общий счётчик открытых панелей: закрытая вложенная панель не должна снимать overflow у родителя. */
let overflowLocks = 0;

/**
 * Состояние стопки базовых слайдеров: закрытие всех открытых панелей при переходе
 * на страницу сущности и единая блокировка скролла для вложенных панелей.
 */
export function useSlidePanelStack() {
  function registerCloser(close: () => void): () => void {
    closers.add(close);

    return () => {
      closers.delete(close);
    };
  }

  function closeAllPanels(): void {
    for (const close of [...closers]) close();
  }

  function acquireOverflowLock(): void {
    if (overflowLocks === 0) {
      document.documentElement.style.overflow = 'hidden';
      document.body.style.overflow = 'hidden';
    }
    overflowLocks += 1;
  }

  function releaseOverflowLock(): void {
    overflowLocks = Math.max(0, overflowLocks - 1);
    if (overflowLocks === 0) {
      document.documentElement.style.overflow = '';
      document.body.style.overflow = '';
    }
  }

  return { registerCloser, closeAllPanels, acquireOverflowLock, releaseOverflowLock };
}
