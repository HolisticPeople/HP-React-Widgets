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
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Mount Multi-Address Widget
    const multiAddressRoot = document.getElementById('hp-multi-address-root');
    if (multiAddressRoot) {
        const props = JSON.parse(multiAddressRoot.dataset.props || '{}');
        ReactDOM.createRoot(multiAddressRoot).render(
            <React.StrictMode>
                <MultiAddress {...props} />
            </React.StrictMode>,
        )
    }

    // Mount My Account Header Widget
    const myAccountHeaderRoot = document.getElementById('hp-my-account-header-root');
    if (myAccountHeaderRoot) {
        const propsStr = myAccountHeaderRoot.dataset.props || '{}';
        console.log('[HP-React-Widgets] MyAccountHeader raw props:', propsStr);
        const props = JSON.parse(propsStr);
        console.log('[HP-React-Widgets] MyAccountHeader parsed props:', props);
        ReactDOM.createRoot(myAccountHeaderRoot).render(
            <React.StrictMode>
                <MyAccountHeader {...props} />
            </React.StrictMode>,
        )
    } else {
        console.warn('[HP-React-Widgets] Could not find #hp-my-account-header-root');
    }
});
