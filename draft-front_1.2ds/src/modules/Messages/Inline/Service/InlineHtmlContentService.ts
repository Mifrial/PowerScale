import { descriptionHtmlSanitizerService } from '@/modules/Core/UI/Service/Instance/descriptionHtmlSanitizerService';

/** Приводит значение заметки к HTML и прогоняет через общий санитайзер. */
export class InlineHtmlContentService {
  normalize(value: string): string {
    if (/<[a-z][\s\S]*>/i.test(value)) return value;

    return value
      .split('\n')
      .map((paragraph) => `<p>${this.escapeHtml(paragraph)}</p>`)
      .join('');
  }

  sanitize(value: string): string {
    return descriptionHtmlSanitizerService.sanitize(this.normalize(value));
  }

  private escapeHtml(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
}
