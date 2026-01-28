/**
 * State/Province data utilities using country-state-city library
 * 
 * @package HP-React-Widgets
 * @since 2.43.44
 * @version 2.43.45 - Switched to country-state-city library
 * @author Amnon Manneberg
 */

import { State, IState } from 'country-state-city';

export interface StateOption {
  code: string;
  name: string;
}

/**
 * Countries that have subdivisions in the library but shouldn't show a dropdown
 * (e.g., subdivisions not commonly used in addresses)
 */
const COUNTRIES_WITHOUT_STATE_DROPDOWN = [
  'IL', // Israel - districts not used in addresses
  'SG', // Singapore - no subdivisions needed
  'HK', // Hong Kong - no subdivisions needed
];

/**
 * Get states for a country using country-state-city library
 */
export function getStatesForCountry(countryCode: string): StateOption[] {
  if (COUNTRIES_WITHOUT_STATE_DROPDOWN.includes(countryCode)) {
    return [];
  }
  const states = State.getStatesOfCountry(countryCode);
  return states.map((state: IState) => ({
    code: state.isoCode,
    name: state.name,
  }));
}

/**
 * Check if a country has defined states/provinces
 */
export function countryHasStates(countryCode: string): boolean {
  if (COUNTRIES_WITHOUT_STATE_DROPDOWN.includes(countryCode)) {
    return false;
  }
  const states = State.getStatesOfCountry(countryCode);
  return states.length > 0;
}

/**
 * Get state label based on country
 */
export function getStateLabel(countryCode: string): string {
  switch (countryCode) {
    case 'US':
      return 'State';
    case 'CA':
      return 'Province';
    case 'AU':
      return 'State/Territory';
    case 'GB':
      return 'County';
    case 'DE':
    case 'AT':
    case 'CH':
      return 'State';
    case 'MX':
    case 'BR':
      return 'State';
    case 'JP':
      return 'Prefecture';
    default:
      return 'State/Province';
  }
}
