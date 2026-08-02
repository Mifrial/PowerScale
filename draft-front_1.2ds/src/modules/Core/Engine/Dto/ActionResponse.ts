export interface ActionError {
  code: string
  message: string
}

export interface ActionResponse<T = unknown> {
  success: boolean
  data: T | null
  error?: ActionError
}
