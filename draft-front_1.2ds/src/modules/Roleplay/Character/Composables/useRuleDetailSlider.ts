import { reactive } from 'vue';

/**
 * Одиночное состояние слайдера правила карточки персонажа: открытие деталки правила
 * происходит в слайдере, без ухода со страницы. Монтируется один раз на странице.
 */
const state = reactive<{ open: boolean; ruleCode: string | null }>({ open: false, ruleCode: null });

export function useRuleDetailSlider() {
  function openRule(ruleCode: string | null | undefined): void {
    if (ruleCode == null) return;
    state.ruleCode = ruleCode;
    state.open = true;
  }

  function close(): void {
    state.open = false;
  }

  return { state, openRule, close };
}
