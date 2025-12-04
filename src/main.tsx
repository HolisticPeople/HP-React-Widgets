import React from 'react'
import ReactDOM from 'react-dom/client'
import './index.css'
import { MultiAddress } from './components/MultiAddress'
import { MyAccountHeader } from '@/components/MyAccountHeader'

// Global settings injected by PHP
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
widgetRegistry.MultiAddress = MultiAddress;
widgetRegistry.MyAccountHeader = MyAccountHeader;

document.addEventListener('DOMContentLoaded', () => {
    const nodes = document.querySelectorAll<HTMLElement>('[data-hp-widget=\"1\"]');

    nodes.forEach((node) => {
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
        }

        ReactDOM.createRoot(node).render(
            <React.StrictMode>
                <Component {...props} />
            </React.StrictMode>,
        );
    });
});
