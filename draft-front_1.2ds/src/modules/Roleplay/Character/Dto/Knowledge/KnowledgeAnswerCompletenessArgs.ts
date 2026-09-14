/** Вход полноты ответа после броска. */
export interface KnowledgeAnswerCompletenessArgs {
  success: boolean;
  shortage: number;
  fieldCode: string;
  physiologyLevel: number;
}
