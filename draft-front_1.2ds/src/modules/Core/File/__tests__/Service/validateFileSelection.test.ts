import { describe, it, expect } from 'vitest';
import { validateFileSelection } from '@/modules/Core/File/Service/ValidateFileSelection';

function fileOfSize(size: number, type = 'image/png', name = 'cover.png'): File {
  return new File([new Uint8Array(size)], name, { type });
}

describe('validateFileSelection', () => {
  it('пропускает файл в пределах лимита и типа', () => {
    expect(validateFileSelection(fileOfSize(10), { maxSize: 10, accept: ['image/png'] })).toBeNull();
  });

  it('отбивает размер', () => {
    expect(validateFileSelection(fileOfSize(11), { maxSize: 10 })).toBe('Файл больше 10 Б');
  });

  it('отбивает тип вне accept', () => {
    expect(validateFileSelection(fileOfSize(1, 'text/plain'), { accept: ['image/png'] })).toBe(
      'Тип text/plain не разрешён',
    );
  });

  it('без ограничений пропускает всё', () => {
    expect(validateFileSelection(fileOfSize(1, 'text/plain'), {})).toBeNull();
  });
});
