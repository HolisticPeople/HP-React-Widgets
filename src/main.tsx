import React from 'react'
import ReactDOM from 'react-dom/client'
import { flushSync } from 'react-dom'
import './index.css'
import { MultiAddress } from './components/MultiAddress'
import { MyAccountHeader } from '@/components/MyAccountHeader'
import { AddressCardPicker } from '@/components/AddressCardPicker'
import { FunnelHero } from '@/components/FunnelHero'
import { FunnelCheckout } from '@/components/FunnelCheckout'
import { FunnelUpsell } from '@/components/FunnelUpsell'
import { FunnelThankYou } from '@/components/FunnelThankYou'

// Checkout SPA (new hybrid approach)
import { FunnelCheckoutApp } from '@/components/checkout-app'
import { TooltipProvider } from '@/components/ui/tooltip'

// Modular funnel section components
import {
  FunnelHeader,
  FunnelHeroSection,
  FunnelBenefits,
  FunnelProducts,
  FunnelFeatures,
  FunnelAuthority,
  FunnelTestimonials,
  FunnelFaq,
  FunnelCta,
  FunnelFooter,
  FunnelScience,
} from '@/components/funnel'

// Global settings injected by PHP (v2.14.16)
declare global {
    interface Window {
        hpReactSettings: {
            root: string;
            nonce: string;
            user_id: number;
        }
        hpReactWidgets?: Record<string, React.ComponentType<any>>;
    }
}

// Ensure a global registry of available widgets exists.
const widgetRegistry: Record<string, React.ComponentType<any>> = window.hpReactWidgets || {};
window.hpReactWidgets = widgetRegistry;

// Register built-in widgets.
// Account components
widgetRegistry.MultiAddress = MultiAddress;
widgetRegistry.MyAccountHeader = MyAccountHeader;
widgetRegistry.AddressCardPicker = AddressCardPicker;

// Funnel components (legacy monolithic)
widgetRegistry.FunnelHero = FunnelHero;
widgetRegistry.FunnelCheckout = FunnelCheckout;
widgetRegistry.FunnelUpsell = FunnelUpsell;
widgetRegistry.FunnelThankYou = FunnelThankYou;

// Checkout SPA (hybrid approach - single component handles checkout->upsell->thankyou)
widgetRegistry.FunnelCheckoutApp = FunnelCheckoutApp;

// Funnel section components (modular)
widgetRegistry.FunnelHeader = FunnelHeader;
widgetRegistry.FunnelHeroSection = FunnelHeroSection;
widgetRegistry.FunnelBenefits = FunnelBenefits;
widgetRegistry.FunnelProducts = FunnelProducts;
widgetRegistry.FunnelFeatures = FunnelFeatures;
widgetRegistry.FunnelAuthority = FunnelAuthority;
widgetRegistry.FunnelTestimonials = FunnelTestimonials;
widgetRegistry.FunnelFaq = FunnelFaq;
widgetRegistry.FunnelCta = FunnelCta;
widgetRegistry.FunnelFooter = FunnelFooter;
widgetRegistry.FunnelScience = FunnelScience;

// Error Boundary Component
class ErrorBoundary extends React.Component<
    { children: React.ReactNode; fallback?: React.ReactNode },
    { hasError: boolean; error?: Error }
> {
    constructor(props: { children: React.ReactNode; fallback?: React.ReactNode }) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error: Error) {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
        console.error('[HP-React-Widgets] *** ERROR BOUNDARY CAUGHT ***');
        console.error('[HP-React-Widgets] Error:', error.message);
        console.error('[HP-React-Widgets] Stack:', error.stack);
        console.error('[HP-React-Widgets] Component stack:', errorInfo.componentStack);
    }

    render() {
        if (this.state.hasError) {
            return this.props.fallback || (
                <div style={{ padding: '20px', background: '#331', color: '#fc0', borderRadius: '8px' }}>
                    <p>Something went wrong loading this component.</p>
                    <button onClick={() => window.location.reload()} style={{ marginTop: '10px', padding: '8px 16px', cursor: 'pointer' }}>
                        Reload Page
                    </button>
                </div>
            );
        }
        return this.props.children;
    }
}

// Render a single widget node
function renderWidget(node: HTMLElement, index: number) {
    const componentName = node.dataset.component;
    
    if (!componentName) {
        console.warn('[HP-React-Widgets] data-component is missing on widget node', node);
        return;
    }

    const Component = window.hpReactWidgets?.[componentName];
    if (!Component) {
        console.warn('[HP-React-Widgets] No component registered for', componentName);
        return;
    }

    let props: any = {};
    const rawProps = node.dataset.props || '{}';
    try {
        props = JSON.parse(rawProps);
    } catch (e) {
        console.error('[HP-React-Widgets] Failed to parse data-props JSON for', componentName, e);
        return;
    }

    // Skip if already rendered
    if (node.dataset.rendered === '1') {
        return;
    }
    node.dataset.rendered = '1';

    try {
        const root = ReactDOM.createRoot(node);
        // Use flushSync to force synchronous rendering, bypassing React's async scheduler
        // This avoids conflicts with browser extensions that hook into MessagePort/MessageChannel
        flushSync(() => {
            root.render(
                <ErrorBoundary>
                    <TooltipProvider>
                        <Component {...props} />
                    </TooltipProvider>
                </ErrorBoundary>,
            );
        });
    } catch (e: any) {
        console.error('[HP-React-Widgets] Failed to render', componentName, e?.message, e);
        // Show error in the widget container
        node.innerHTML = `<div style="padding:20px;background:#400;color:#faa;border-radius:8px;">
            <p>Widget failed to load: ${e?.message || 'Unknown error'}</p>
            <button onclick="location.reload()" style="margin-top:10px;padding:8px 16px;cursor:pointer;">Reload</button>
        </div>`;
    }
}

// Initialize widgets - render immediately and synchronously
function initWidgets() {
    const nodes = document.querySelectorAll<HTMLElement>('[data-hp-widget="1"]');
    
    nodes.forEach((node, index) => {
        // Render each widget directly - flushSync inside renderWidget handles sync rendering
        renderWidget(node, index);
    });
}

// Wait for DOM to be ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWidgets);
} else {
    // DOM already ready, render immediately
    initWidgets();
}
