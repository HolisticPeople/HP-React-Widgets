import React from 'react';

// Base64 encoded badge images from hp-shipstation-rates
const UPS_BADGE_BASE64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFoAAABACAYAAACa5WD/AAAAAXNSR0IB2cksfwAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAuIwAALiMBeKU/dgAAAAd0SU1FB+kKDws3MFXHl2QAAA8oSURBVHja7Zx7lBTVncc/t6of84JxxmF4iDMoKoL4iEJcFzwgisT4AHyurNG4gePqMWY3x5iYbJJjcmI22SRszMnuyfpa464oim+RlzwCiDyUxzDKG6ZlEGRmYIaZ6e7qqvvbP6qnu6qmZ8QsA9PKPadP9XR3UXW/93u/v+/vdy8FJ9vJ9kVqqrfcSBUMBs4Fhhom502/S/UtiMi5A8tUuKyvlLW1US2iDAS0BtHgOApx0AUR6g63qEOfNEnKstSWZ19zWkSoBXYqxZY6Ye+XEuhqpUIiMs4wGHvvveqys4fIqEEVnDqgHE4pVpQWC2h8L3FAa4V2FNohfQy+z/7d3AqHW2F/k2Z/kzTu+kSve2Ghs0prVqD4S0xIfSGBroIyYPJd09WNYy6RK886naLTB0AklAZT6ASuD2j92eC6AwHaVjkHJZGEfY2a3Qec+Ae77EVvrXReRqnXYiKH8h7oKvjatLvUjKsvl+svHE64vDT9hXheucANfp5mtGOnwUsDKbZCp2Wk20HI8fehI8KW+pS9clvq9UXr7MdjMC+vgK6CQmD6L36mHhg7mrOGDBb3St6rSQDQz2A0GUbnBu6zgJbMb3LPjL1NDuv3JHc8Pj/5GPBEDOK9Guizi9SMR38sj064jIryUwAj/fICLenjUQLcNdCgHcMHthwl8Lm/Uxxu06zdnWh44p3ED3fa8vixwiV0rIGecbfcc/PVVGTAJXBUHnCVB/S/ogmK1jZFe7viSJuiqVnRloBDRxTt7ZCyhbglpGzQjiAiKCBiSOb2+hSaFJiKkKEoKwlRURDmimHFFfsOO/f8YbXVe4Hucs4EWa0Cv+nq1QGqQEuLyf6DIfbsi7AzZrBlj+LTw7AlBtrRruRrjRYBEURAaxdgEe0eM3+7Lzzm49vTihhXGO4RGELHBWS6YXY3rLYsxb76Aj7aUcB7G8Js3K7Y26gQrTNA6fT73t5CPQZwdyB3g4ttKw7sK2DNhj4sXRVhzTbDZanWiNadL6XUlxjorlgdZLKnJeIGtZvLmLe4hEXvm6RsSQPb+0HsHUBLwG0EnEeizWTN2krmvFHMpj0GWk4wwJJPQHd3s+nZn0oabPqgH8/OLqOmzp3+gv7CVn9CPcqMHGwWgY93lPH8S5W8834ErXUW/b/mMsdanyWfpSN9TLaZLJo/mKdf6cuRhEK8vQoC1mMBTr5gjA70bf/HpTz93ECWboj4wMzFyG5Zmv4u30JkqEdlQ0BEsXl9f2Y+XUF9k5H9iccL+4DzgCzdDMZR3UI6cfl8UpSHjLYtgxUrBvGr/y7F0YCSnDY6A6RI10zNweT/jz4fb+8d6kk2z3trMDNnF6OUYBjKB2RHVueVkA4G43kvnydO9eLEpUcZvWtHiMKIcOeUBEYoG2UaGw1eXBDJgHPpBQ5fOc9Ka43roVeuM1lT4w5O/0rhhnE25w9NMaDcxkAjArZjsGOvwcrNBi/PpUsJCkfhgW+EuLDaoEC5FT4biDU4LNpo8fZCJ881GiiOaiZP2ku0OJWJ6Du3ncrs+ZUZZl9wtsWt18V8JdFDBweypibMw9+yuOqrzRRGLM8qS7bw3+8cxeihBpMujvDTJ6G+3n8rYy9TfHdKmGIRtJ1eONDuKszwohAjxhUw7pwUP/hja48CbfR8hiX+1RTPIGTkwvubwD8w/vw2iguTrgfPfKp8A2qI5txBSf7tHk1lvyybLxqpeGhKmD5oRHtILtmDHYchxeEe99FGT7K5o1TZFcgZRyF+v+09P5f2aw1WSnlOcUdh8Kk2D92ePefuiSZFpna/70hMBVrjGislXYCr8k86xEsdCQQtX6CTTgNBF3g3NoZ5eGYRLe0w9isWt11tUVyYPeGiIQ5jLzGp2SEMG+gfoPYk/PmdOLV7HMKmMGZ4mDHnFGKYZp66DoLMDaTkglucD9qsIKslO1CS9uSOrdi4N4Rozc4FUbQ2mD4lmWFiyBQmXSwURU1UwIXUfizM+YuT8e8bd1osG2pz94SSnlaOHtJoyWWWu5CPTnKRyVQ8PVedZ0S6vbnKpPGQIpObCJw9EBzHlQlvKyrsLAu1OzW/e+NIjzO6h4GW3MEQT0bYURLtJB1Z/c5MCgHED1bTETjQZPou3bdYEFujUBnwBRg2wOAfJkc63e6+/fJFCIbSeQ9HGrWOFRMRugyIEvg+Fw7NbR2WxD1Gw4KTEvY1elN5hak0t341yn8+WMKlowyOZzsx9s4jGyLS/W/SDBbJsjNjDUUQhPak4RsABZQUwBurHWw7IDtaGNLH4CdT+/K9bxbkMdC5JCIAom8lWuvc1o5sQUiCbiQQQONx5QuYApgh4eWlDnNWCraj0jNDZeRdJYVxVYX8/rsleczooBfO4aXFIx0+TQj+RvyuQ3IkNdFI0D6Ctt23j7+V4smFNs2tyu8uUYiGMwrC/HRGUf4zuivHQdDeSRfn5pCNoDyVFEtGyzsGpLU9+9mLS1P84M9x3t+lcbTynS8aLqws4LzhRv4y2j3kAlL8KXiOYOipnPreZ5merfhVlGYDIUDSgoYj/tHYGdP88Ml2/mdVAsu39qtQFkw4P5rPjAbH6SwdCgK7hbrw2d5B6wiKwYwrBJVlOj0YLtit7cKWj3PT/7l5Fus/tn2DKAID+4bzWKNFONissFPKB2KfItvdVuDrrRfUHOyWgI9OD9A3roOiqOPRcag7AIkkTJucO7VuSUmn0rWRhxqtvDqrHU1zS8gHZPkpcaZeYWeWs7z2zq2yuQu3orXPLWSPrq0bOQKmjLEzdRMRNxt8d7N73h1jwvz2oTBnnkX2PBGG9jc6Be1Pm+1gOemYVpeOea3DNEhIWh5M0+3Fjroogwa2e6a75tvTGpg8oZCQCadVxP0g50heJJ3slPTR/NePbUTBGf0cQtpBnKw+7z0gLPggfWoKRp5i8Kf7C9nXrLHQFEuIkpSJtv05VG29xYgzs1mjYZDo1YzWjtrWAUxpuVvAeW1JlETc8LG6MGoxrKqZoac1U5Au6mcYq7NB1M9mRTTsMHxQgnMrk4TF8Vm/RBKeeweOtPldidMKlZgMcsL0SRmZa3Xcy95mm/nv2t6NpWhhW68G+o/PSENHJ06rcKVh+SaDuUtKcewcQU/7mSwCiYTB4Wa/70X77ZsE9DyRFGa/I8xfG1hUEE8yk1a1rKtRHIprnlrmrq70L81q+uurrYO9Wjq0w6aDh6BfmaJqQArRIZRS/Pp/Cmk6rLjuimYGVDoBC+cC19hk8OGOKC8tLGTVR2YmYckFbMcApFKwp154YbHBvLXuTBBAKZiz3OLyESanlihMFTjPFrbut3h2RZyPdrmJU7/iEDhwuN1BhJreXvhfWbff9bb9K5JU9S8kdkBwHOFPr0WYvbCCkWfYDDszRSQCdgosx2BjjcmeBpPGNpVdFe9wI9plpggcbAgze4lBSdSmpV1Rux1q9xrpFRPxrVc9+XaKJ+da/O15iqEDFMURd3WmOS5sjjls3JG1HsPPNSgvMEm1wf4WB2BlrwY6Brs+3E3dxedQXVScZOIlmifmGpkQfqhdsbw2zPLacOc9Gmm6im9bQtZNiICB4rlFJqLVUW2SEYEVNZrlm7rfiHPN6ChWmxvEdzdYdTHY1et99KvLZFYi6Qav8aOSOfMR77YAt7AUzBbFJx3krHV4S6leID/XbhAALhhQgAJSjrBsa3JWXiQsG9arp9Zvd6f9kNPbuPlyHVhozVbuvCzzbaIJICldlVgDOXq25iGe4Nk9m6ffHKXEcQPh1gMWu/fxVF4AXSey/aXFzLJSYBiaWyYmKOuTBrYD3EDHgyBnNTqYrODf8YS/SOX/9LPZPLhaccVZxWgbHA2LPorPqhPZnjeF/1fnyiOrakmKwOBBrTz4d04XGio5QSag0UhwUmTZ3HF+p915R7FF7MGpJYTbXRg21SeS79baj+TVCksMtj42W//y4CFXq8dc0sJ9U7S/8paZ6tIJZAlotPZ67a6iXqfzur/HH00v5PSQmw0ejmteWNv+aAy25hXQANu3qp89NkcvtSwImZrbJrXwnZvEU3PyoxEE2Vsz9qfjErCA0kVlq2t9fuTeIkb3K0KnFClHmLW2ZemBJvl5T2HRo5sct8RF2l9l6oByvXr6tcY54ZDDjRObqR7Qh18/b/BJQ47g5j1q3SkbFFG55YfgElduOp89TPG9m0ro54SxE2Br4dUNrduWbLCnxnpwf/txeYxEFQz+p2+p+XdebY4ojLh9OdhQxEuLozy7oAuQ3YIH40Y4JBI6s4QQCcPSWsPH5izQOisbmRX2LKO/880I44cWwREDcSBuwWvrWz+cvcKaFKNnH55yPJ/XUXLTdeqF+yYbXx9Y7iYb2jHYvqeIZ942WbZROoGc8Q5p0LTXxnktYloqsp/548BN15vcOKqIUieEY7m7UT9t1sxa3Tp36Ub7thi09nT/j/sTaM40uO833zd+Nf5CoyRkupdPWSHWb4ny/BKD92ocv0Hz7NPLpc1BNrsa7krONRMNJo+OUl0cJtXmbvW1LHhvZ6L131+Pf3+Xw38cr36fkEf9VMHAayao39w5yZg2coiRkdNkIswHWyO8uAxW10pukIMJT5DNIlw5TnHL2Chn9AlhtYKk91Jv2ZfilXXx596tcR6MwSfHs88n9OFVVXDp3bcYv506xhxTXZlNSCwrzIbtIV5ZAcs3aP//cQky2sPmaycZ3DA6zJmlJlZr+oEoGmINmvkb4ytfWWE/GIP3TkRfe8VTwqrg2vvvMB792mjzgkHlRlqXXUn5sM7kzTUwb6Xjt38eNt82RXHV+SGGlBpYrS64Wis+aXJYXJvc9OyC1I9i8OaJ7GOveRxbGvBb7/9741+uvtg8f1C5mWG4Y5vUHTSZv16YNdfOBMcZdxhcOdKgImyQiqcf0+Yo6psclmy2ap6Zl/pFDF7wXuO9ByqMv3msQX+pgfYAfuM/3mY+PP4Cc9QZlWZmeUuLQcMRk51NmpHVQqGjcKyO5+Apdh9wWP6htfaZt+1/jcHLvalPvRLojlYNV9x+nfGTqy4KjR82KJRdktJZedAattTbLK5JLZ2zyPl5HSzujX3p1UB7GD5q0ljjn78+OnT7yNNDKmwqkhbU1Nny9vupWQtX6ZkxWMfJdowYrqi+YYT63czbQ3uvH65mViuGnETlZDvZTkT7PwwVYyTWACjIAAAAAElFTkSuQmCC';

const USPS_BADGE_BASE64 = 'data:image/png;base64,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';

// USPS Logo - using actual badge
export const USPSLogo = () => (
  <img src={USPS_BADGE_BASE64} alt="USPS" width="24" height="24" style={{ objectFit: 'contain' }} />
);

// UPS Logo - using actual badge
export const UPSLogo = () => (
  <img src={UPS_BADGE_BASE64} alt="UPS" width="24" height="24" style={{ objectFit: 'contain' }} />
);

// FedEx Logo - Purple and orange (keeping SVG as no badge available)
export const FedExLogo = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="2" y="4" width="20" height="16" rx="2" fill="#4D148C"/>
    <text x="8" y="14" fontSize="5" fill="white" fontWeight="bold">Fed</text>
    <text x="17" y="14" fontSize="5" fill="#FF6600" fontWeight="bold">Ex</text>
  </svg>
);

// DHL Logo - Yellow and red (keeping SVG as no badge available)
export const DHLLogo = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="2" y="6" width="20" height="12" rx="2" fill="#FFCC00"/>
    <text x="12" y="14" textAnchor="middle" fontSize="6" fill="#D40511" fontWeight="bold">DHL</text>
  </svg>
);

// Generic Truck Icon - fallback
export const TruckIcon = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect x="1" y="3" width="15" height="13"/>
    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
    <circle cx="5.5" cy="18.5" r="2.5"/>
    <circle cx="18.5" cy="18.5" r="2.5"/>
  </svg>
);

// Carrier logo detection and mapping
export type CarrierType = 'usps' | 'ups' | 'fedex' | 'dhl' | 'generic';

export const detectCarrier = (serviceName: string): CarrierType => {
  const name = serviceName.toLowerCase();
  if (name.includes('usps') || name.includes('postal') || name.includes('priority mail')) return 'usps';
  if (name.includes('ups') || name.includes('united parcel')) return 'ups';
  if (name.includes('fedex') || name.includes('federal express')) return 'fedex';
  if (name.includes('dhl')) return 'dhl';
  return 'generic';
};

export const getCarrierLogo = (serviceName: string): React.ReactNode => {
  const carrier = detectCarrier(serviceName);
  
  switch (carrier) {
    case 'usps':
      return <USPSLogo />;
    case 'ups':
      return <UPSLogo />;
    case 'fedex':
      return <FedExLogo />;
    case 'dhl':
      return <DHLLogo />;
    default:
      return <TruckIcon />;
  }
};

// Get carrier name for display
export const getCarrierName = (serviceName: string): string => {
  const carrier = detectCarrier(serviceName);
  
  switch (carrier) {
    case 'usps':
      return 'USPS';
    case 'ups':
      return 'UPS';
    case 'fedex':
      return 'FedEx';
    case 'dhl':
      return 'DHL';
    default:
      return '';
  }
};
