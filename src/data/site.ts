export const SITE = {
  name: 'Design Dentistry Columbia',
  phone: '803-667-3919',
  phoneHref: 'tel:+18036673919',
  email: 'customerservice@ddcola.com',
  address: '126 Professional Park Rd, Columbia, SC 29229',
  mapsUrl: 'https://maps.app.goo.gl/Dqyr3ekw5vxym6st8',
  mapEmbed:
    'https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d13208.895388711198!2d-80.9003706!3d34.1406148!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x88f8a989b14a48bb%3A0xaecd25da5b953731!2sDesign%20Dentistry%20Columbia!5e0!3m2!1sen!2sus!4v1697645061880!5m2!1sen!2sus',
  facebook: 'https://www.facebook.com/designdentistrycolumbia',
  instagram: 'https://instagram.com/dentalmanike',
  defaultDescription:
    'Design Dentistry Columbia is a Cosmetic Dental practice that also offers General Dentistry with state-of-the-art technology and restorative services . We pride ourselves on creating lifelong relationships and work to ensure that each patient receive treatments in a comfortable and relaxed environment.',
  hours: [
    ['Monday', '8am - 5pm'],
    ['Tuesday', '8am - 5pm'],
    ['Wednesday', '8am - 5pm'],
    ['Thursday', '8am - 5pm'],
    ['Friday', '8am - 12pm'],
    ['Saturday', 'Closed'],
    ['Sunday', 'Closed'],
  ],
};

export type NavItem = { label: string; href?: string; children?: NavItem[] };

export const NAV_LEFT: NavItem[] = [
  {
    label: 'About',
    children: [
      { label: 'Dr. Ike', href: '/Isaiahdavis' },
      { label: 'Meet The Team', href: '/team' },
      { label: 'Our Location', href: '/location' },
      { label: 'Real Stories', href: '/testimonials' },
      { label: 'Before & After', href: '/realresults' },
      { label: 'In The Media', href: '/in-the-media' },
      { label: 'Blog', href: '/blog' },
    ],
  },
  {
    label: 'Services',
    children: [
      {
        label: 'Cosmetic Dentistry',
        children: [
          { label: 'Procedures', href: '/Procedures' },
          { label: 'Cosmetic Dentistry', href: '/cosmetic-dentistry' },
          { label: 'Teeth Whitening', href: '/teeth-whitening' },
          { label: 'Dental Veneers', href: '/dental-veneers' },
          { label: 'Porcelain Inlays And Onlays', href: '/porcelain-inlays-and-onlays' },
        ],
      },
      {
        label: 'General Dentistry',
        children: [
          { label: 'General Dentistry', href: '/general-dentistry' },
          { label: 'Hygiene Need To Know', href: '/hygiene' },
          { label: 'Root Canal Therapy', href: '/rootcanal-therapy' },
          { label: 'Tooth Fillings', href: '/Tooth-Fillings' },
          { label: 'Core Buildup', href: '/corebuildups' },
          { label: 'Dental Sealants', href: '/dental-sealants' },
          { label: 'Tooth Extractions', href: '/tooth-extractions' },
        ],
      },
      {
        label: 'Restorative Dentistry',
        children: [
          { label: 'Restorative Dentistry', href: '/restorative-dentistry' },
          { label: 'Dental Crowns', href: '/crowns' },
          { label: 'Dental Bridges', href: '/dental-bridges' },
          { label: 'Dental Implants', href: '/implants' },
          { label: 'Full Arch Dental Implants', href: '/full-arch-dental-implants' },
        ],
      },
    ],
  },
  { label: 'Tech', href: '/technology' },
];

export const NAV_RIGHT: NavItem[] = [
  { label: 'Financing Options', href: '/financing-options' },
  { label: 'Contact', href: '/contact' },
];
