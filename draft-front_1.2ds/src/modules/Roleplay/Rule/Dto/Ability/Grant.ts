import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { MagicStudyScope } from '@/modules/Roleplay/Rule/Enum/Ability/MagicStudyScope';
import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';
import type { LightingLevel } from '@/modules/Roleplay/Rule/Enum/LightingLevel';

export type Grant =
  | { type: 'characteristic'; characteristic_code: string; value: DimensionalNumberValue; permanent?: boolean }
  | {
      /** Дать характеристику значением параметра «X»: база = X (напр. «Врождённая Магия X»). */
      type: 'characteristic_parameter';
      characteristic_code: string;
      parameter_code: string;
      per_unit: number;
      permanent?: boolean;
    }
  | {
      type: 'characteristic_modify';
      characteristic_code: string;
      amount: Formula;
      source_code: string;
      /** Ограничивает модификатор указанными кодами проверок вместо обычного значения характеристики. */
      check_codes?: string[];
      permanent?: boolean;
    }
  | { type: 'resource'; resource_code: string; limit: DimensionalNumberValue | number; permanent?: boolean }
  | { type: 'resource_limit_change'; resource_code: string; amount: Formula; source_code: string; permanent?: boolean }
  | { type: 'ability'; ability_code: string; level?: number; permanent?: boolean }
  | { type: 'keyword'; keyword_code: string; remove?: boolean; permanent?: boolean }
  | { type: 'item'; item_code: string; quantity?: number; permanent?: boolean }
  | { type: 'magic_path'; path_code: string; permanent?: boolean }
  | {
      type: 'magic_study';
      scope: MagicStudyScope;
      max_cost: number;
      /** Путь как домен изучения, без гранта самого пути (каст этим путём не открывается). */
      path_code?: string;
      /** Сколько способностей этот грант может открыть; без поля — без лимита. */
      max_instances?: number;
      /** Оплата изучения вместо каталожной стоимости; 0 — бесплатно. */
      paid_cost?: number;
      permanent?: boolean;
    }
  | {
      type: 'resistance';
      damage_type_code: string;
      /**
       * Размерное число (напр. «20 сопротивления отравлению») или формула параметра
       * «X»: {type:'parameter', per_unit} → per_unit × X способности.
       */
      value: DimensionalNumberValue | Formula;
      source_code: string;
      permanent?: boolean;
    }
  | {
      /** Модификатор чувства: добавляет вклад к значению чувства (модификатор к Внимательности). */
      type: 'sense_modify';
      sense_code: string;
      amount: Formula;
      source_code: string;
      /** Если задан — перекрывает статус из спеки чувства (глухота: absent). */
      status?: SenseStatus;
      /**
       * Худшее освещение, при котором это чувство работает как при хорошем.
       * Нет поля — как обычное зрение (нужен свет). `minimal` — ночное зрение, не полная тьма.
       */
      treat_as_good_down_to?: LightingLevel;
      permanent?: boolean;
    }
  | {
      /** Множитель дистанции шагов процесса (напр. бег ×2). */
      type: 'process_distance_multiplier';
      ability_code: string;
      multiplier: number;
      permanent?: boolean;
    }
  | {
      type: 'state_modify';
      state_code: string;
      amount: Formula;
      source_code: string;
      permanent?: boolean;
    }
  | {
      type: 'check_advantage';
      amount: number;
      check_codes: string[];
      source_code?: string;
      permanent?: boolean;
    }
  | {
      /**
       * Стартовый капитал от особенности богатства: значение = apply(fixed, percent% от лимита денег игры).
       * max — берётся больший из двух (Обеспеченный/Преуспевающий/Богатый), min — меньший (Нищий).
       * При отсутствии лимита денег (moneyBudget = null) учитывается только fixed.
       */
      type: 'money';
      fixed: number;
      percent: number;
      apply: 'max' | 'min';
      permanent?: boolean;
    };
