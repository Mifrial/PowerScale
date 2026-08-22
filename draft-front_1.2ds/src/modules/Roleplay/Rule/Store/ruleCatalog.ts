import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

// TODO (Roleplay, чипы правил): доработать резолюцию правил по контексту чата.
// Требование: в обычных чатах чип правила всегда резолвит правило из последней ревизии
// (не draft) пространства «Актуальные правила»; в чатах по персонажу и по игре — правило
// из ревизии, к которой относится этот персонаж/игра. Сейчас каталог — единый плоский
// список из getRules(0) (mock игнорирует spaceId), контекст ревизии не учитывается.
export const useRuleCatalogStore = defineStore('ruleCatalog', () => {
  const rules = ref<Rule[]>([]);

  async function ensureLoaded(): Promise<Rule[]> {
    if (rules.value.length) return rules.value;

    const { getRuleApi } = await import('@/modules/Roleplay/Rule/init');
    rules.value = await getRuleApi().getRules(0);

    return rules.value;
  }

  function findRule(code: string): Rule | undefined {
    return rules.value.find((r) => r.code === code);
  }

  return { rules, ensureLoaded, findRule };
});
