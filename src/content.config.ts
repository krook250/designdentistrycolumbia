import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const faq = z.object({ q: z.string(), a: z.string() });

const services = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/services' }),
  schema: z.object({
    metaTitle: z.string(),
    description: z.string().optional(),
    h1: z.string(),
    subtitle: z.string().optional(),
    heroVideo: z.string().optional(),
    heroPoster: z.string().optional(),
    inlineVideo: z.string().optional(),
    inlinePoster: z.string().optional(),
    takeaways: z.array(z.string()).optional(),
    faqs: z.array(faq).optional(),
  }),
});

const team = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/team' }),
  schema: z.object({
    name: z.string(),
    role: z.string(),
    order: z.number(),
    metaTitle: z.string().optional(),
    description: z.string().optional(),
    photo: z.string().optional(),
    heroVideo: z.string().optional(),
    heroPoster: z.string().optional(),
  }),
});

export const collections = { services, team };
