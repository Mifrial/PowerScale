/** Запрос страницы локаций. */
export interface SceneFindPageQuery {
  limit: number;
  offset: number;
  q?: string;
  isArchived?: boolean;
}
