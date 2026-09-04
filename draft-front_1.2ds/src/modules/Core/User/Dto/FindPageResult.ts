/**
 * Ответ findPage: страница и COUNT фильтра.
 */
export interface FindPageResult<T> {
  items: T[];
  total: number;
}
