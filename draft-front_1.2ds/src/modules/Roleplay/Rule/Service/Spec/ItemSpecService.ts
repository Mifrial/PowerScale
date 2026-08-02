import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec'
import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft'
import { ITEM_SUBTYPE_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_SUBTYPE_FIELDS'
import { ITEM_BLOCK_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_BLOCK_FIELDS'

export class ItemSpecService {
  constructor(
    private subtypeFields: Record<string, (keyof ItemSpecDraft)[]>,
    private blockFields: (keyof ItemSpecDraft)[],
  ) {}

  /**
   * Оставляет в спеке только блоки активных подтипов (по манифесту).
   * Применяется на границе эмита (specToEmit): при смене подтипа черновые поля
   * редактора НЕ чистятся, но в сохранённый результат мусор не попадает.
   */
  prune(spec: ItemSpecDraft, subtypes: string[]): ItemSpec {
    const active = new Set<keyof ItemSpecDraft>()
    for (const subtype of subtypes) {
      for (const field of this.subtypeFields[subtype] ?? []) {
        active.add(field)
      }
    }
    const out: ItemSpecDraft = { ...spec }
    for (const field of this.blockFields) {
      if (!active.has(field)) {
        delete out[field]
      }
    }
    return out as ItemSpec
  }
}

export const itemSpecService = new ItemSpecService(ITEM_SUBTYPE_FIELDS, ITEM_BLOCK_FIELDS)
