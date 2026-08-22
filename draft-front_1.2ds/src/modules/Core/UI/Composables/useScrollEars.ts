import { ref } from 'vue';
import type { ScrollEarsApi } from '@/modules/Core/UI/Interface/Grid/ScrollEarsApi';
import type { ScrollEarsScope } from '@/modules/Core/UI/Interface/Grid/ScrollEarsScope';

export function useScrollEars(scope: ScrollEarsScope): ScrollEarsApi {
  const scrollEl = ref<HTMLElement | null>(null);
  const earsLeft = ref(false);
  const earsRight = ref(false);

  function updateEars() {
    const el = scrollEl.value;
    if (!el) {
      earsLeft.value = false;
      earsRight.value = false;

      return;
    }
    earsLeft.value = el.scrollLeft > 2;
    earsRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 2;
  }

  function scroll(dir: -1 | 1) {
    const el = scrollEl.value;
    if (!el) return;
    el.scrollBy({ left: dir * 300, behavior: 'smooth' });
  }

  function initEars() {
    const container = scope.scrollContainer.value;
    if (!container) {
      updateEars();

      return;
    }

    const target = scope.scrollSelector ? container.querySelector<HTMLElement>(scope.scrollSelector) : container;
    if (!target) {
      updateEars();

      return;
    }
    if (target !== scrollEl.value) {
      if (scrollEl.value) {
        scrollEl.value.removeEventListener('scroll', updateEars);
      }
      scrollEl.value = target;
      target.addEventListener('scroll', updateEars);
    }

    const wrapper = container.closest<HTMLElement>('.smart-ears-wrapper');
    if (wrapper) {
      const topEl = scope.topSelector ? container.querySelector<HTMLElement>(scope.topSelector) : null;
      const bottomEl = scope.bottomSelector ? container.querySelector<HTMLElement>(scope.bottomSelector) : null;
      const top = topEl?.offsetHeight ?? 44;
      const bottom = bottomEl?.offsetHeight ?? 0;
      wrapper.style.setProperty('--ear-top', `${top + 2}px`);
      wrapper.style.setProperty('--ear-bottom', `${bottom + 2}px`);
    }

    updateEars();
  }

  function cleanup() {
    if (scrollEl.value) {
      scrollEl.value.removeEventListener('scroll', updateEars);
    }
  }

  return { scrollEl, earsLeft, earsRight, updateEars, scroll, initEars, cleanup };
}
