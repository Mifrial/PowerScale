import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';
import { MAP_ERROR_MESSAGES } from '@/modules/Roleplay/Map/Constant/Map/MAP_ERROR_MESSAGES';

/** Отказ транспорта карт с кодом конверта и русской подсказкой. */
export class MapApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details: unknown = null,
  ) {
    super(message);
    this.name = 'MapApiError';
  }

  static fromActionError(error: ActionError): MapApiError {
    const base = MAP_ERROR_MESSAGES[error.code];
    const detail = detailOf(error.message);
    const message = base && detail !== null ? `${base}: ${detail}` : (base ?? error.message);

    return new MapApiError(error.code, message, error.details ?? null);
  }
}

const DETAIL_HINTS: { pattern: RegExp; text: string }[] = [
  { pattern: /\.points has an invalid point$/, text: 'недопустимая точка' },
  { pattern: /\.handles? has an invalid handle$/, text: 'недопустимая ручка' },
  { pattern: /\.handles does not match the points$/, text: 'ручек не столько же, сколько точек' },
  { pattern: /\.widths has an invalid width$/, text: 'недопустимая ширина' },
  { pattern: /\.widths does not match the points$/, text: 'ширин не столько же, сколько точек' },
  { pattern: /\.label is empty$/, text: 'пустая подпись' },
  { pattern: /\.name is empty$/, text: 'пустое имя места' },
  { pattern: /\.color is invalid$/, text: 'недопустимый цвет' },
  { pattern: /\.segments is invalid$/, text: 'стилей сегментов не столько, сколько сегментов' },
  { pattern: /\.segments does not match the points$/, text: 'стилей сегментов не столько, сколько сегментов' },
  { pattern: /\.segments\[\d+\] is invalid$/, text: 'недопустимый стиль сегмента' },
  { pattern: /\.decor\.flow\.direction is invalid$/, text: 'направление течения только по точкам или против' },
  { pattern: /\.decor\.flow\b.*is invalid$/, text: 'недопустимое течение воды' },
  { pattern: /\.decor(\.flow)? has an unknown key$/, text: 'неизвестное поле декора' },
  { pattern: /\.layer is invalid$/, text: 'недопустимый слой' },
  { pattern: /\.nodeId is unknown$/, text: 'объект ссылается на неизвестную точку' },
  { pattern: /\.filePublicId has no dimensions$/, text: 'у подложки нет размеров' },
  { pattern: /\.filePublicId is archived$/, text: 'подложка в архиве' },
  { pattern: /\.filePublicId is not available$/, text: 'подложка недоступна' },
  { pattern: / is out of range$/, text: 'значение вне допустимого диапазона' },
  { pattern: /^Map scale is not calibrated$/, text: 'масштаб карты не задан' },
  { pattern: /^map\.variants have a duplicate label$/, text: 'подписи вариантов повторяются' },
  { pattern: /^map\.variants must have exactly one default$/, text: 'ровно один вариант должен быть по умолчанию' },
  { pattern: /^map\.variants is empty$/, text: 'нужен хотя бы один вариант' },
  { pattern: /^map\.name is empty$/, text: 'пустое название карты' },
  { pattern: /^map\.visibility is invalid$/, text: 'недопустимая видимость карты' },
  { pattern: /^map\.expectedVersion is required$/, text: 'карту изменили, обновите её' },
];

function detailOf(message: string): string | null {
  const text = typeof message === 'string' ? message.trim() : '';
  if (text === '') return null;
  if (!/^(Map\b|[a-z][\w]*(\[\d+\])?\.)/.test(text)) return null;
  const translated = DETAIL_HINTS.find((hint) => hint.pattern.test(text));
  if (!translated) return text;
  const path = /^([\w.[\]]+?)\s/.exec(text)?.[1] ?? '';
  if (path === '' || /^Map\b/.test(text) || text.startsWith('map.')) return translated.text;

  return `${path} — ${translated.text}`;
}
