/**
 * Custom inline SVG icons for WordPress theme compatibility
 * DO NOT use lucide-react - theme CSS often breaks these icons
 * @see HP-React-Widgets-Shortcodes-Guide.md Section 7.1
 */

interface IconProps {
  className?: string;
}

export const HpCloseIcon = ({ className = '' }: IconProps) => (
  <svg 
    className={`hp-icon ${className}`} 
    viewBox="0 0 24 24" 
    aria-hidden="true"
    style={{ width: '1.25rem', height: '1.25rem' }}
  >
    <path
      d="M18 6L6 18M6 6l12 12"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

export const HpChevronDownIcon = ({ className = '' }: IconProps) => (
  <svg 
    className={`hp-icon ${className}`} 
    viewBox="0 0 24 24" 
    aria-hidden="true"
    style={{ width: '1rem', height: '1rem' }}
  >
    <path
      d="M6 9l6 6 6-6"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

export const HpMenuIcon = ({ className = '' }: IconProps) => (
  <svg 
    className={`hp-icon ${className}`} 
    viewBox="0 0 24 24" 
    aria-hidden="true"
    style={{ width: '1.25rem', height: '1.25rem' }}
  >
    <path
      d="M3 12h18M3 6h18M3 18h12"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);
