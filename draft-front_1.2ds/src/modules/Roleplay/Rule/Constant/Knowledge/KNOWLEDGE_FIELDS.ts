import type { KnowledgeField } from '@/modules/Roleplay/Rule/Dto/Knowledge/KnowledgeField';

const regionSlot = {
  key: 'region',
  required: true,
  domain_ref: 'region',
  allow_custom: true,
} as const;

const speciesSlot = {
  key: 'species',
  required: true,
  domain_ref: 'species',
  allow_custom: true,
} as const;

/** Словарь типов знания (не keyword). */
export const KNOWLEDGE_FIELDS: readonly KnowledgeField[] = [
  { code: 'laws', name: 'Законы', slots: [{ ...regionSlot }] },
  { code: 'animals', name: 'Животные', slots: [{ ...regionSlot }] },
  { code: 'plants', name: 'Растения', slots: [{ ...regionSlot }] },
  { code: 'history', name: 'История', slots: [{ ...regionSlot }] },
  { code: 'diseases', name: 'Болезни', slots: [{ ...speciesSlot }] },
  { code: 'physiology', name: 'Физиология', slots: [{ ...speciesSlot }] },
  { code: 'other', name: 'Прочее', slots: [{ key: 'topic', required: true, domain_ref: null, allow_custom: true }] },
];
