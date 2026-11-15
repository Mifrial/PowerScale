import { defineStore } from 'pinia';
import { ref } from 'vue';

/**
 * Заголовки миров и статей для хлебных крошек.
 *
 * Маршрут знает только коды (`/lore/myth/...`), а крошки должны показывать
 * названия. Страницы после загрузки кладут сюда название, крошки читают
 * реактивно и обновляются без перезагрузки.
 */
export const useLoreCrumbStore = defineStore('loreCrumbs', () => {
  const worlds = ref<Record<string, string>>({});
  const articles = ref<Record<string, Record<string, string>>>({});

  function setWorld(code: string, title: string): void {
    if (code === '' || title.trim() === '') return;
    worlds.value = { ...worlds.value, [code]: title };
  }

  function setArticle(worldCode: string, code: string, title: string): void {
    if (worldCode === '' || code === '' || title.trim() === '') return;
    articles.value = {
      ...articles.value,
      [worldCode]: { ...(articles.value[worldCode] ?? {}), [code]: title },
    };
  }

  function getWorldTitle(code: string): string | null {
    return worlds.value[code] ?? null;
  }

  function getArticleTitle(worldCode: string, code: string): string | null {
    return articles.value[worldCode]?.[code] ?? null;
  }

  function forgetWorld(code: string): void {
    const nextWorlds = { ...worlds.value };
    delete nextWorlds[code];
    worlds.value = nextWorlds;
    const nextArticles = { ...articles.value };
    delete nextArticles[code];
    articles.value = nextArticles;
  }

  function reset(): void {
    worlds.value = {};
    articles.value = {};
  }

  return { worlds, articles, setWorld, setArticle, getWorldTitle, getArticleTitle, forgetWorld, reset };
});
