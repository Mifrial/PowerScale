import { describe, expect, it, vi } from 'vitest';
import { useSlidePanelStack } from '@/modules/Core/UI/Composables/useSlidePanelStack';

describe('useSlidePanelStack', () => {
  it('закрывает все зарегистрированные панели и снимает регистрацию', () => {
    const stack = useSlidePanelStack();
    const first = vi.fn();
    const second = vi.fn();
    const unregisterFirst = stack.registerCloser(first);
    const unregisterSecond = stack.registerCloser(second);

    stack.closeAllPanels();
    expect(first).toHaveBeenCalledTimes(1);
    expect(second).toHaveBeenCalledTimes(1);

    unregisterFirst();
    stack.closeAllPanels();
    expect(first).toHaveBeenCalledTimes(1);
    expect(second).toHaveBeenCalledTimes(2);

    unregisterSecond();
    stack.closeAllPanels();
    expect(second).toHaveBeenCalledTimes(2);
  });

  it('снимает блокировку скролла только после последней открытой панели', () => {
    const stack = useSlidePanelStack();
    stack.acquireOverflowLock();
    stack.acquireOverflowLock();

    expect(document.documentElement.style.overflow).toBe('hidden');
    expect(document.body.style.overflow).toBe('hidden');

    stack.releaseOverflowLock();
    expect(document.documentElement.style.overflow).toBe('hidden');
    expect(document.body.style.overflow).toBe('hidden');

    stack.releaseOverflowLock();
    expect(document.documentElement.style.overflow).toBe('');
    expect(document.body.style.overflow).toBe('');
  });
});
