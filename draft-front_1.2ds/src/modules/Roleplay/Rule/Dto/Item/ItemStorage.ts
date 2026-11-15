/** Блок предмета-хранилища магии: кристалл с Предельной мощью и Объёмом. */
export interface ItemStorage {
  power_parameter: string;
  volume_parameter: string;
  recorded_parameter: string;
  volume_base: number;
  volume_increment: number;
  min_volume_power_delta: number;
  /** Признаки, которыми ограничены записи; пусто — без ограничения. */
  recorded_keywords?: string[];
}
