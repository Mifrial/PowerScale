/**
 * Модификатор характеристики действия (actionCharacteristic): дельта в пунктах шкалы базы
 * + источник (как у CharacteristicModifier/гранта characteristic_modify). Источник нужен
 * для агрегации (макс+/мин− от одного источника, суммирование разных).
 */
export interface ActionCharacteristicModifier {
  /** Смещение в пунктах шкалы базы характеристики («Сила − 2» → −2, «Сила↓» → −3). */
  delta: number;
  source_code: string | null;
  source_label: string | null;
}
