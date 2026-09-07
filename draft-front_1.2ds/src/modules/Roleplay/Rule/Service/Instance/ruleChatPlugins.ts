import { defineAsyncComponent } from 'vue';
import { registerInlineRenderer, registerTokenSource } from '@/modules/Messages/Chat/init';

/** Регистрирует чип правила и источник токенов чата. */
export function registerRuleChatPlugins(): void {
  registerInlineRenderer({
    type: 'rule',
    component: defineAsyncComponent(() => import('@/modules/Roleplay/Rule/Component/RuleChip.vue')),
    describe: (segment) => {
      const override = segment.params[1]?.trim();
      if (override) return override;

      return segment.params[0] ?? '';
    },
  });
  registerTokenSource({
    type: 'rule',
    label: 'Правило',
    icon: 'mdi-book-open-variant',
    search: async () => [],
  });
}
