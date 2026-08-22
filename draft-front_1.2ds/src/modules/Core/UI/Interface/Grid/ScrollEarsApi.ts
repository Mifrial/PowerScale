import type { Ref } from 'vue';

export interface ScrollEarsApi {
  scrollEl: Ref<HTMLElement | null>;
  earsLeft: Ref<boolean>;
  earsRight: Ref<boolean>;
  updateEars: () => void;
  scroll: (dir: -1 | 1) => void;
  initEars: () => void;
  cleanup: () => void;
}
