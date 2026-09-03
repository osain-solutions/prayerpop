const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  use: { baseURL: process.env.WP_E2E_URL || 'http://127.0.0.1:8888' },
  reporter: 'line'
});
