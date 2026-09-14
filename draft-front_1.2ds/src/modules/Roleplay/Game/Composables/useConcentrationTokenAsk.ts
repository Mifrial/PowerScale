import { ref } from 'vue';

/** Диалог траты жетонов перед автоматической проверкой Воли. */
export function useConcentrationTokenAsk() {
  const open = ref(false);
  const maxSpend = ref(0);
  const remaining = ref(0);
  const amount = ref(0);
  let settle: ((value: number) => void) | null = null;

  function finish(value: number): void {
    open.value = false;
    settle?.(value);
    settle = null;
  }

  async function askTokenSpend(max: number, current: number): Promise<number> {
    if (max < 1) return 0;
    maxSpend.value = max;
    remaining.value = current;
    amount.value = 0;
    open.value = true;

    return new Promise((resolve) => {
      settle = resolve;
    });
  }

  function confirm(): void {
    finish(amount.value);
  }

  function skip(): void {
    finish(0);
  }

  return { open, maxSpend, remaining, amount, askTokenSpend, confirm, skip };
}
