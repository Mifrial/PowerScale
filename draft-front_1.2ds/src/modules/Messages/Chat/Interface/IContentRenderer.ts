import type { Component } from 'vue';

export interface IContentRenderer {
  type: string;
  component: Component;
}
