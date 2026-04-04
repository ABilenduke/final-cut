import type { StorybookConfig } from '@storybook/vue3-vite'

const config: StorybookConfig = {
  stories: ['../app/**/*.stories.@(js|jsx|ts|tsx)'],
  framework: {
    name: '@storybook/vue3-vite',
    options: {},
  },
  addons: [],
}

export default config
