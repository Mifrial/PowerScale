import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';

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
      permanent?: boolean;
    }
  | { type: 'resource'; resource_code: string; limit: DimensionalNumberValue | number; permanent?: boolean }
  | { type: 'resource_limit_change'; resource_code: string; amount: Formula; source_code: string; permanent?: boolean }
  | { type: 'ability'; ability_code: string; level?: number; permanent?: boolean }
  | { type: 'keyword'; keyword_code: string; remove?: boolean; permanent?: boolean }
  | { type: 'item'; item_code: string; quantity?: number; permanent?: boolean }
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
