import type { ActionCharacteristicModifier } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCharacteristicModifier';

export type Formula =
  | { type: 'fixed'; value: number }
  | { type: 'characteristic'; characteristic_code: string; modifier: number }
  | { type: 'ability_level'; ability_code: string; multiplier?: number; offset?: number }
  | { type: 'dimensional'; base: number; size: number }
  | { type: 'parameter'; parameter_code: string; per_unit: number }
  | {
      /**
       * Размер характеристики (простое число): {3|-1} → −1, {5|1} → 1. Используется в
       * лимитах авто-ресурсов («+1 за каждый размер Ловкости выше/ниже среднего»).
       */
      type: 'characteristic_size';
      characteristic_code: string;
    }
  | {
      /**
       * Число ПОЛНЫХ размеров, на которое характеристика `characteristic_code_from` выше
       * `characteristic_code_to`: trunc(modifyDiffTo(from, to) / 3), остаток отбрасывается в
       * сторону нуля. {3|1} vs {5|0} → 0, {3|1} vs {3|0} → 1, {3|0} vs {3|1} → −1.
       */
      type: 'characteristic_size_gap';
      characteristic_code_from: string;
      characteristic_code_to: string;
    }
  | {
      /**
       * «Сила удара/броска/выстрела» — значение характеристики для действия (база из
       * WeaponProfile.action_characteristics, дефолт — характеристика персонажа) + модификаторы.
       * Модификаторы атаки (сильный удар +2) подключаются на уровне оценки позже.
       */
      type: 'actionCharacteristic';
      action: 'strike' | 'throw' | 'shoot';
      characteristic: string;
      modifier: ActionCharacteristicModifier[];
      multiplier?: number;
    };
