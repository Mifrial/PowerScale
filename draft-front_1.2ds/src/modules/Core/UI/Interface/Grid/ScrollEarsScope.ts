import type { Ref } from 'vue';

export interface ScrollEarsScope {
  scrollContainer: Ref<HTMLElement | null>;
  scrollSelector?: string;
  topSelector?: string;
  bottomSelector?: string;
}
