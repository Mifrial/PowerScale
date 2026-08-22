import type { Component } from 'vue';

export interface IChatToolbarExtension {
  id: string;
  component: Component;
  /**
   * Куда рендерить расширение: 'bar' — над полем ввода (панель, напр. макросы),
   * 'actions' — в колонку действий справа внизу (рядом с «Вставить ссылку»/отправить).
   * По умолчанию 'bar'.
   */
  placement?: 'bar' | 'actions';
}
