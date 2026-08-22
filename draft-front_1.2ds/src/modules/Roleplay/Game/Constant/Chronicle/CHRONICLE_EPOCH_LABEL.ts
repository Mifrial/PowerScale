import type { ChronicleEpoch } from '@/modules/Roleplay/Game/Enum/ChronicleEpoch';

/** Человекочитаемые подписи точек отсчёта летописи (ТР §8); сейчас — одна эпоха. */
export const CHRONICLE_EPOCH_LABEL: Record<ChronicleEpoch, string> = {
  adventure_start: 'от Начала приключения',
};
