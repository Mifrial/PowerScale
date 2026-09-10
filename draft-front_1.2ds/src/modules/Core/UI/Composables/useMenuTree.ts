import { computed, onUnmounted, ref } from 'vue';
import { menuRegistry } from '@/modules/Core/UI/Service/Instance/menuRegistry';

export function useMenuTree() {
  const revision = ref(menuRegistry.revision);
  const unsubscribe = menuRegistry.subscribe(() => {
    revision.value = menuRegistry.revision;
  });
  onUnmounted(unsubscribe);

  const tree = computed(() => {
    void revision.value;

    return menuRegistry.getMenuTree();
  });

  return { tree };
}
