// Funnel Section Components - Modular shortcode-based architecture

// Phase 1 Components
export { FunnelHeader } from './FunnelHeader';
export { FunnelHeroSection } from './FunnelHeroSection';
export { FunnelBenefits } from './FunnelBenefits';
export { FunnelProducts } from './FunnelProducts';

// Phase 2 Components
export { FunnelFeatures } from './FunnelFeatures';
export { FunnelAuthority } from './FunnelAuthority';
export { FunnelTestimonials } from './FunnelTestimonials';
export { FunnelFaq } from './FunnelFaq';
export { FunnelCta } from './FunnelCta';
export { FunnelFooter } from './FunnelFooter';
export { FunnelScience } from './FunnelScience';

// Round 2 Components
export { ScrollNavigation } from './ScrollNavigation';
export { NavigationTooltip } from './NavigationTooltip';
export { FunnelInfographics } from './FunnelInfographics';

// Responsive Components (v2.32.0)
export { StickyCTA } from './StickyCTA';
export { 
  Skeleton, 
  SkeletonText, 
  SkeletonImage, 
  SkeletonProductCard, 
  SkeletonHero, 
  SkeletonTestimonial, 
  SkeletonBenefitCard,
  SKELETON_CSS 
} from './SkeletonLoader';

// Re-export types
export type { FunnelHeaderProps, NavItem } from './FunnelHeader';
export type { FunnelHeroSectionProps } from './FunnelHeroSection';
export type { FunnelBenefitsProps, Benefit } from './FunnelBenefits';
export type { FunnelProductsProps, FunnelProduct } from './FunnelProducts';
export type { FunnelFeaturesProps, Feature } from './FunnelFeatures';
export type { FunnelAuthorityProps, Quote } from './FunnelAuthority';
export type { FunnelTestimonialsProps, Testimonial } from './FunnelTestimonials';
export type { FunnelFaqProps, FaqItem } from './FunnelFaq';
export type { FunnelCtaProps } from './FunnelCta';
export type { FunnelFooterProps, FooterLink } from './FunnelFooter';
export type { FunnelScienceProps, ScienceSection } from './FunnelScience';
export type { QuoteCategory, ArticleLink } from './FunnelAuthority';
export type { ScrollNavigationProps } from './ScrollNavigation';
export type { NavigationTooltipProps } from './NavigationTooltip';
export type { FunnelInfographicsProps } from './FunnelInfographics';
export type { StickyCTAProps } from './StickyCTA';
