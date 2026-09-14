/** Слот типа знания: справочник домена и/или свободный текст. */
export interface KnowledgeFieldSlot {
  key: string;
  required: boolean;
  domain_ref: string | null;
  allow_custom: boolean;
}
