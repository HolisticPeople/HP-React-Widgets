/**
 * Off-Canvas Menu Types
 * For HP React Widgets WordPress integration
 */

export interface SubCategory {
  label: string;
  href: string;
}

export interface MenuItem {
  label: string;
  href?: string;
  image?: string;
  children?: SubCategory[];
}

export interface MenuSection {
  title?: string;
  items: MenuItem[];
}

export interface HpMenuProps {
  sections?: MenuSection[];
  title?: string;
  footerText?: string;
  triggerClassName?: string;
}
