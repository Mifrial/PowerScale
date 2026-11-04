import { formatFileSize } from '@/modules/Core/File/Service/FormatFileSize';

/** Ограничения, которые потребитель задаёт полю загрузки. */
export interface FileSelectionLimits {
  maxSize?: number;
  accept?: string[];
}

/**
 * Клиентская проверка выбранного файла.
 *
 * @param file Выбранный файл.
 * @param limits Ограничения потребителя.
 *
 * @returns Текст ошибки или null, если файл допустим.
 */
export function validateFileSelection(file: File, limits: FileSelectionLimits): string | null {
  if (limits.maxSize !== undefined && file.size > limits.maxSize) {
    return `Файл больше ${formatFileSize(limits.maxSize)}`;
  }

  if (limits.accept && limits.accept.length > 0 && !limits.accept.includes(file.type)) {
    return `Тип ${file.type || '—'} не разрешён`;
  }

  return null;
}
