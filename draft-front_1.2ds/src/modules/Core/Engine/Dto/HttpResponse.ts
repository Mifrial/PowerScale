export interface HttpResponse<T> {
  ok: boolean
  status: number
  data: T
}
