import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { EditorAbilityLevel } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityLevel';
import type { EditorAbilityParameter } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityParameter';
import type { EditorAbilityZone } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityZone';
import type { EditorAbilityInstance } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityInstance';

/** Способность в модели редактора: зоны с ценами, выполнимость требований, текущий уровень. */
export interface EditorAbility {
  ruleCode: string;
  code: string;
  name: string;
  type: AbilityType | null;
  description: string;
  /** Шаги процесса (type 'process'): по одному шагу на каждый элемент process.steps. */
  processSteps: ProcessStep[];
  keywordIds: number[];
  zones: EditorAbilityZone[];
  /** Текущий уровень на персонаже (0 = не взята). */
  level: number;
  /** Требования по уровням (для +/− в UI). */
  levels: EditorAbilityLevel[];
  /** Дана расой/видом автоматически (бесплатно). */
  automatic: boolean;
  /** Получена даром-навыком особенности (D100): уровень из гранта, снять нельзя. */
  gifted: boolean;
  /** Уровень дара-навыка особенности (D100); 0 — не дар. За подаренные уровни ОР не списываются. */
  giftedLevel: number;
  /** Производный уровень (D109 «Ближний бой») или агрегат «Развитие X» (D108): уровень
   *  вычисляется автоматически, вручную выбрать нельзя. */
  derived: boolean;
  /** Доступна расой (в списке расовых способностей). */
  racial: boolean;
  /** Видна в каталоге этапа: общая (common) или предоставленная расой; иначе скрыта. */
  visible: boolean;
  /** Черта характеристик (признак «Характеристика»): закупка характеристики способностью на вкладке «Характеристики». */
  characteristic: boolean;
  /** Целевая характеристика черты (из гранта characteristic_modify); null — дар без модификатора (напр. Магия). */
  characteristicCode: string | null;
  /** Код группы-правила (type 'group'), в которую входит способность (null — вне группы). */
  groupCode: string | null;
  /** Код родительской способности-«Улучшения» (null — обычная способность, не улучшение). */
  parentCode: string | null;
  /** Параметры «X» покупки (только у параметрических способностей). */
  parameters: EditorAbilityParameter[];
  /** Множественный навык (D106): изучается по домену. */
  multiple: boolean;
  /** Код справочника домена множественного навыка (напр. 'language' | 'region' | 'species'). */
  domainRef: string | null;
  /** Экземпляры множественного навыка: домен + уровень каждого (пусто у не-multiple). */
  instances: EditorAbilityInstance[];
  /** Домен одиночной способности (domain_ref без multiple): выбранное значение (null — не задан). */
  domain: string | null;
  /** Код правила словаря домена одиночной способности; null — свой текст. */
  domainCode: string | null;
  /** Опции словаря домена по domainRef (правила ревизии); пусто — свободный текст. */
  domainOptions: { code: string; name: string }[];
}
