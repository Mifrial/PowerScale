import { reactive } from 'vue';

/**
 * Одиночное состояние слайдера правила карточки персонажа: открытие деталки правила
 * происходит в слайдере, без ухода со страницы. Монтируется один раз на странице.
 */
const state = reactive<{ open: boolean; ruleId: string | null }>({ open: false, ruleId: null });

export function useRuleDetailSlider() {
  function openRule(ruleId: string | null | undefined): void {
    if (ruleId == null) return;
    state.ruleId = ruleId;
    state.open = true;
  }

  function close(): void {
    state.open = false;
  }

  return { state, openRule, close };
}
