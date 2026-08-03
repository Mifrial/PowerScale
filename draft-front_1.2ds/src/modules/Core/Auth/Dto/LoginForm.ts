export interface FormRef {
  validate: () => Promise<{ valid: boolean }>
  reset: () => void
  resetValidation: () => void
}
