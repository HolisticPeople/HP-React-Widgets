import React, { useState } from 'react';
import { MapPin, Plus } from 'lucide-react';

interface Address {
    id: string;
    type: string;
    first_name: string;
    last_name: string;
    address_1: string;
    city: string;
    state: string;
    postcode: string;
    country: string;
}

interface MultiAddressProps {
    addresses: Address[];
    isLoggedIn: boolean;
}

export const MultiAddress: React.FC<MultiAddressProps> = ({ addresses: initialAddresses, isLoggedIn }) => {
    const [addresses, setAddresses] = useState<Address[]>(initialAddresses);

    if (!isLoggedIn) {
        return <div className="p-4 bg-yellow-50 text-yellow-800 rounded-md">Please log in to manage your addresses.</div>;
    }

    return (
        <div className="bg-white shadow rounded-lg p-6 max-w-4xl mx-auto">
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <MapPin className="w-6 h-6 text-blue-600" />
                    My Addresses
                </h2>
                <button
                    className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                    onClick={() => console.log('Add Address Clicked')}
                >
                    <Plus className="w-4 h-4" />
                    Add New Address
                </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {addresses.map((addr, idx) => (
                    <div key={idx} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow relative">
                        <div className="absolute top-4 right-4 text-xs font-semibold uppercase tracking-wider text-gray-500 bg-gray-100 px-2 py-1 rounded">
                            {addr.type}
                        </div>
                        <h3 className="font-bold text-lg mb-2">{addr.first_name} {addr.last_name}</h3>
                        <div className="text-gray-600 space-y-1">
                            <p>{addr.address_1}</p>
                            <p>{addr.city}, {addr.state} {addr.postcode}</p>
                            <p>{addr.country}</p>
                        </div>
                        <div className="mt-4 flex gap-3 text-sm font-medium text-blue-600">
                            <button className="hover:underline">Edit</button>
                            <button className="hover:underline text-red-600">Delete</button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
