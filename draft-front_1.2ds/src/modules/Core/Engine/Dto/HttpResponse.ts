/** Разобранный HTTP-ответ с телом JSON. */
export interface HttpResponse<T> {
  ok: boolean;
  status: number;
  data: T;
}
