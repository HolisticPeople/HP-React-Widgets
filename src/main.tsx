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
        const props = JSON.parse(myAccountHeaderRoot.dataset.props || '{}');
        ReactDOM.createRoot(myAccountHeaderRoot).render(
            <React.StrictMode>
                <MyAccountHeader {...props} />
            </React.StrictMode>,
        )
    }
});
