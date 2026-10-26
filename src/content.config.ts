import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const faq = z.object({ q: z.string(), a: z.string() });

const blog = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/blog' }),
  schema: z.object({
    title: z.string(),
    metaTitle: z.string().optional(),
    description: z.string().optional(),
    date: z.coerce.date(),
    updated: z.coerce.date().optional(),
    author: z.string().optional(),
    image: z.string().optional(),
  }),
});

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

export const collections = { blog, services, team };
