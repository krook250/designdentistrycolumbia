// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://www.designdentistrycolumbia.com',
  // dental-veneers.html, served at /dental-veneers by .htaccess — keeps the old Duda URLs exactly
  build: { format: 'file' },
  trailingSlash: 'never',
  integrations: [
    sitemap({ filter: (page) => !page.includes('/thank-you') && !page.includes('/portal') }),
  ],
});
