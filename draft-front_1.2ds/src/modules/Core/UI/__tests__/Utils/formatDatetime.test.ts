import { describe, it, expect } from 'vitest';
import { formatDatetime } from '@/modules/Core/UI/Utils/formatDatetime';

function localeLabel(date: Date): string {
  return (
    date.toLocaleDateString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }) +
    ' ' +
    date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  );
}

describe('formatDatetime', () => {
  it('строку unix-секунд показывает как локальные дату и время', () => {
    const seconds = 1_757_419_200;
    expect(formatDatetime(String(seconds))).toBe(localeLabel(new Date(seconds * 1000)));
  });

  it('ISO-строку форматирует той же локалью', () => {
    const iso = '2024-01-02T03:04:00Z';
    expect(formatDatetime(iso)).toBe(localeLabel(new Date(iso)));
  });

  it('неразобранную строку возвращает как есть', () => {
    expect(formatDatetime('не дата')).toBe('не дата');
  });
});
