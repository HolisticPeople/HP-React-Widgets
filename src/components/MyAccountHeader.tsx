import React from 'react';
import { User, ShoppingBag, MessageCircle, Award, MapPin, UserCircle, LogOut } from 'lucide-react';

export interface NavItem {
  id: string;
  label: string;
  icon: 'orders' | 'contact' | 'points' | 'addresses' | 'profile';
  href?: string;
  active?: boolean;
}

export interface MyAccountHeaderProps {
  userName: string;
  avatarUrl?: string;
  navItems?: NavItem[];
  activeNavId?: string;
  onNavClick?: (navId: string) => void;
  onLogout?: () => void;
  logoutUrl?: string;
}

const iconMap = {
  orders: ShoppingBag,
  contact: MessageCircle,
  points: Award,
  addresses: MapPin,
  profile: UserCircle,
};

const defaultNavItems: NavItem[] = [
  { id: 'orders', label: 'Orders', icon: 'orders' },
  { id: 'contact', label: 'Contact Us', icon: 'contact' },
  { id: 'points', label: 'My Points', icon: 'points' },
  { id: 'addresses', label: 'Addresses', icon: 'addresses' },
  { id: 'profile', label: 'Profile', icon: 'profile' },
];

export const MyAccountHeader: React.FC<MyAccountHeaderProps> = ({
  userName,
  avatarUrl,
  navItems = defaultNavItems,
  activeNavId,
  onNavClick,
  onLogout,
  logoutUrl,
}) => {
  const handleNavClick = (item: NavItem, e: React.MouseEvent) => {
    if (onNavClick) {
      e.preventDefault();
      onNavClick(item.id);
    }
    // If no onNavClick, let the default link behavior happen
  };

  const handleLogout = (e: React.MouseEvent) => {
    if (onLogout) {
      e.preventDefault();
      onLogout();
    }
    // If no onLogout, let the default link behavior happen
  };

  return (
    <div className="w-full bg-card rounded-lg p-6">
      {/* Header Section */}
      <div className="flex items-center justify-between mb-6">
        <p className="text-sm text-primary font-medium">My Account</p>
      </div>

      {/* User Info Row */}
      <div className="flex items-center justify-between mb-8">
        <div className="flex items-center gap-3">
          {avatarUrl ? (
            <img
              src={avatarUrl}
              alt={userName}
              className="w-10 h-10 rounded-full object-cover border border-border"
            />
          ) : (
            <div className="w-10 h-10 rounded-full bg-secondary flex items-center justify-center border border-border">
              <User className="w-5 h-5 text-muted-foreground" />
            </div>
          )}
          <span className="text-foreground font-medium">{userName}</span>
        </div>
        
        {logoutUrl ? (
          <a
            href={logoutUrl}
            onClick={handleLogout}
            className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            <span>Log Out</span>
          </a>
        ) : (
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            <span>Log Out</span>
          </button>
        )}
      </div>

      {/* Navigation Icons */}
      <div className="flex items-center justify-center gap-3 flex-wrap">
        {navItems.map((item) => {
          const IconComponent = iconMap[item.icon];
          const isActive = item.active || item.id === activeNavId;
          
          const content = (
            <>
              <div className={`nav-icon-button ${isActive ? 'active' : ''}`}>
                <IconComponent className="w-6 h-6" />
              </div>
              <span className="text-xs text-foreground mt-1">{item.label}</span>
            </>
          );

          if (item.href) {
            return (
              <a
                key={item.id}
                href={item.href}
                onClick={(e) => handleNavClick(item, e)}
                className="flex flex-col items-center"
              >
                {content}
              </a>
            );
          }

          return (
            <button
              key={item.id}
              onClick={(e) => handleNavClick(item, e)}
              className="flex flex-col items-center"
            >
              {content}
            </button>
          );
        })}
      </div>
    </div>
  );
};

export default MyAccountHeader;
