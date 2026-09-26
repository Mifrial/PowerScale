import { describe, expect, it } from 'vitest';
import { DescriptionHtmlSanitizerService } from '@/modules/Core/UI/Service/DescriptionHtmlSanitizerService';

const sanitizer = new DescriptionHtmlSanitizerService();

describe('DescriptionHtmlSanitizerService', () => {
  it('сохраняет форматирование, блоки примеров, таблицы и ссылки на правила', () => {
    const clean = sanitizer.sanitize(
      '<p><strong>Сила</strong> <em>важна</em></p><aside class="description-example">Пример</aside><table><tr><td>1</td></tr></table><a data-rule-code="acrobatics">Акробатика</a>',
    );

    expect(clean).toContain('<strong>Сила</strong>');
    expect(clean).toContain('<em>важна</em>');
    expect(clean).toContain('class="description-example"');
    expect(clean).toContain('<table>');
    expect(clean).toContain('data-rule-code="acrobatics"');
  });

  it('сохраняет разворачиваемый блок и вырезает обработчики и script', () => {
    const clean = sanitizer.sanitize(
      '<details class="description-expanded-block"><summary class="description-expanded-block__header"><span class="description-expanded-block__title">Контроль</span><span class="description-expanded-block__difficulty">Базовая Сложность: {2|1}</span></summary><div class="description-expanded-block__body"><p onclick="alert(1)">Текст</p><script>alert(1)</script></div></details>',
    );

    expect(clean).toContain('<details class="description-expanded-block">');
    expect(clean).toContain('<summary');
    expect(clean).toContain('description-expanded-block__title');
    expect(clean).toContain('description-expanded-block__difficulty');
    expect(clean).toContain('<div class="description-expanded-block__body">');
    expect(clean).not.toContain('onclick');
    expect(clean).not.toContain('<script');
  });

  it('удаляет опасные элементы, обработчики событий и внешнюю навигацию', () => {
    const clean = sanitizer.sanitize(
      '<script>alert(1)</script><p onclick="alert(1)">Текст</p><a href="javascript:alert(1)">Опасно</a>',
    );

    expect(clean).not.toContain('<script');
    expect(clean).not.toContain('onclick');
    expect(clean).not.toContain('javascript:');
  });
});
