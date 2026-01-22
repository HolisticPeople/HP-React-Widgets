/**
 * SkeletonLoader - Loading placeholder components
 * 
 * Provides skeleton UI for sections while content loads.
 * Improves perceived performance on mobile.
 * 
 * @package HP-React-Widgets
 * @since 2.32.0
 * @author Amnon Manneberg
 */

import { CSSProperties } from 'react';

interface SkeletonProps {
  width?: string | number;
  height?: string | number;
  borderRadius?: string | number;
  className?: string;
  style?: CSSProperties;
}

/**
 * Base skeleton element with shimmer animation
 */
export const Skeleton = ({
  width = '100%',
  height = '20px',
  borderRadius = '4px',
  className = '',
  style = {},
}: SkeletonProps) => {
  return (
    <div
      className={`hp-skeleton ${className}`}
      style={{
        width,
        height,
        borderRadius,
        background: 'linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%)',
        backgroundSize: '200% 100%',
        animation: 'hp-skeleton-shimmer 1.5s infinite',
        ...style,
      }}
    />
  );
};

/**
 * Skeleton for text lines
 */
export const SkeletonText = ({
  lines = 3,
  className = '',
}: {
  lines?: number;
  className?: string;
}) => {
  return (
    <div className={`hp-skeleton-text ${className}`} style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton
          key={i}
          width={i === lines - 1 ? '70%' : '100%'}
          height="16px"
        />
      ))}
    </div>
  );
};

/**
 * Skeleton for images
 */
export const SkeletonImage = ({
  aspectRatio = '16/9',
  className = '',
}: {
  aspectRatio?: string;
  className?: string;
}) => {
  return (
    <div
      className={`hp-skeleton-image ${className}`}
      style={{
        width: '100%',
        aspectRatio,
        borderRadius: '8px',
        background: 'linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%)',
        backgroundSize: '200% 100%',
        animation: 'hp-skeleton-shimmer 1.5s infinite',
      }}
    />
  );
};

/**
 * Skeleton for product cards
 */
export const SkeletonProductCard = ({ className = '' }: { className?: string }) => {
  return (
    <div
      className={`hp-skeleton-product-card ${className}`}
      style={{
        padding: '16px',
        borderRadius: '12px',
        backgroundColor: 'rgba(255,255,255,0.05)',
        display: 'flex',
        flexDirection: 'column',
        gap: '12px',
      }}
    >
      <SkeletonImage aspectRatio="1/1" />
      <Skeleton width="80%" height="24px" />
      <Skeleton width="50%" height="20px" />
      <Skeleton width="100%" height="40px" borderRadius="8px" />
    </div>
  );
};

/**
 * Skeleton for hero section
 */
export const SkeletonHero = ({ className = '' }: { className?: string }) => {
  return (
    <div
      className={`hp-skeleton-hero ${className}`}
      style={{
        minHeight: '80vh',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center',
        padding: '40px 20px',
        gap: '24px',
      }}
    >
      <Skeleton width="60%" height="48px" />
      <Skeleton width="80%" height="24px" />
      <Skeleton width="70%" height="24px" />
      <SkeletonImage aspectRatio="16/9" />
      <Skeleton width="200px" height="48px" borderRadius="24px" />
    </div>
  );
};

/**
 * Skeleton for testimonial cards
 */
export const SkeletonTestimonial = ({ className = '' }: { className?: string }) => {
  return (
    <div
      className={`hp-skeleton-testimonial ${className}`}
      style={{
        padding: '20px',
        borderRadius: '12px',
        backgroundColor: 'rgba(255,255,255,0.05)',
        display: 'flex',
        flexDirection: 'column',
        gap: '12px',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
        <Skeleton width="48px" height="48px" borderRadius="50%" />
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '4px' }}>
          <Skeleton width="60%" height="16px" />
          <Skeleton width="40%" height="12px" />
        </div>
      </div>
      <SkeletonText lines={3} />
    </div>
  );
};

/**
 * Skeleton for benefit cards
 */
export const SkeletonBenefitCard = ({ className = '' }: { className?: string }) => {
  return (
    <div
      className={`hp-skeleton-benefit ${className}`}
      style={{
        padding: '24px',
        borderRadius: '12px',
        backgroundColor: 'rgba(255,255,255,0.05)',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: '16px',
        textAlign: 'center',
      }}
    >
      <Skeleton width="64px" height="64px" borderRadius="50%" />
      <Skeleton width="80%" height="20px" />
      <SkeletonText lines={2} />
    </div>
  );
};

/**
 * CSS for skeleton animations (inject globally)
 */
export const SKELETON_CSS = `
@keyframes hp-skeleton-shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

.hp-skeleton {
  will-change: background-position;
}
`;

export default Skeleton;
