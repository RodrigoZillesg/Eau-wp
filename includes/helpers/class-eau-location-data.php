<?php
namespace EauSystem\Helpers;

/**
 * Location Data Helper
 *
 * Provides countries, states/provinces, and cities data for forms.
 * Primary focus on Australia with support for other common countries.
 *
 * @since 1.46.0
 */
class Eau_Location_Data {

    /**
     * Get list of all countries
     *
     * @return array Associative array [code => name]
     */
    public static function get_countries() {
        return array(
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'IE' => 'Ireland',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'PH' => 'Philippines',
            'IN' => 'India',
            'CN' => 'China',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'ID' => 'Indonesia',
            'HK' => 'Hong Kong',
            'TW' => 'Taiwan',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PT' => 'Portugal',
            'GR' => 'Greece',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'MX' => 'Mexico',
            'ZA' => 'South Africa',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'IL' => 'Israel',
            'EG' => 'Egypt',
            'RU' => 'Russia',
            'TR' => 'Turkey',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'LK' => 'Sri Lanka',
            'NP' => 'Nepal',
        );
    }

    /**
     * Get states/provinces for a country
     *
     * @param string $country_code Two-letter country code
     * @return array Associative array [code => name]
     */
    public static function get_states($country_code) {
        $states = self::get_all_states();
        return isset($states[$country_code]) ? $states[$country_code] : array();
    }

    /**
     * Get all states data
     *
     * @return array
     */
    private static function get_all_states() {
        return array(
            // Australia
            'AU' => array(
                'ACT' => 'Australian Capital Territory',
                'NSW' => 'New South Wales',
                'NT' => 'Northern Territory',
                'QLD' => 'Queensland',
                'SA' => 'South Australia',
                'TAS' => 'Tasmania',
                'VIC' => 'Victoria',
                'WA' => 'Western Australia',
            ),
            // New Zealand
            'NZ' => array(
                'AUK' => 'Auckland',
                'BOP' => 'Bay of Plenty',
                'CAN' => 'Canterbury',
                'GIS' => 'Gisborne',
                'HKB' => "Hawke's Bay",
                'MBH' => 'Marlborough',
                'MWT' => 'Manawatu-Wanganui',
                'NSN' => 'Nelson',
                'NTL' => 'Northland',
                'OTA' => 'Otago',
                'STL' => 'Southland',
                'TAS' => 'Tasman',
                'TKI' => 'Taranaki',
                'WGN' => 'Wellington',
                'WKO' => 'Waikato',
                'WTC' => 'West Coast',
            ),
            // United States
            'US' => array(
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
                'MN' => 'Minnesota',
                'MS' => 'Mississippi',
                'MO' => 'Missouri',
                'MT' => 'Montana',
                'NE' => 'Nebraska',
                'NV' => 'Nevada',
                'NH' => 'New Hampshire',
                'NJ' => 'New Jersey',
                'NM' => 'New Mexico',
                'NY' => 'New York',
                'NC' => 'North Carolina',
                'ND' => 'North Dakota',
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming',
                'DC' => 'District of Columbia',
            ),
            // United Kingdom
            'GB' => array(
                'ENG' => 'England',
                'NIR' => 'Northern Ireland',
                'SCT' => 'Scotland',
                'WLS' => 'Wales',
            ),
            // Canada
            'CA' => array(
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NS' => 'Nova Scotia',
                'NT' => 'Northwest Territories',
                'NU' => 'Nunavut',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'YT' => 'Yukon',
            ),
        );
    }

    /**
     * Get cities for a state
     *
     * @param string $country_code Two-letter country code
     * @param string $state_code State/province code
     * @return array Array of city names
     */
    public static function get_cities($country_code, $state_code) {
        $cities = self::get_all_cities();

        if (isset($cities[$country_code][$state_code])) {
            return $cities[$country_code][$state_code];
        }

        return array();
    }

    /**
     * Get all cities data
     * Focus on Australia with major cities
     *
     * @return array
     */
    private static function get_all_cities() {
        return array(
            // Australia
            'AU' => array(
                'ACT' => array(
                    'Canberra',
                    'Belconnen',
                    'Gungahlin',
                    'Tuggeranong',
                    'Woden Valley',
                    'Weston Creek',
                    'Molonglo Valley',
                    'Queanbeyan',
                ),
                'NSW' => array(
                    'Sydney',
                    'Newcastle',
                    'Wollongong',
                    'Central Coast',
                    'Maitland',
                    'Tweed Heads',
                    'Wagga Wagga',
                    'Albury',
                    'Port Macquarie',
                    'Tamworth',
                    'Orange',
                    'Dubbo',
                    'Bathurst',
                    'Lismore',
                    'Coffs Harbour',
                    'Nowra',
                    'Richmond',
                    'Bowral',
                    'Parramatta',
                    'Penrith',
                    'Liverpool',
                    'Campbelltown',
                    'Blacktown',
                    'Bankstown',
                    'Bondi',
                    'Manly',
                    'Chatswood',
                    'North Sydney',
                    'Surry Hills',
                    'Darlinghurst',
                ),
                'NT' => array(
                    'Darwin',
                    'Alice Springs',
                    'Palmerston',
                    'Katherine',
                    'Nhulunbuy',
                    'Tennant Creek',
                ),
                'QLD' => array(
                    'Brisbane',
                    'Gold Coast',
                    'Sunshine Coast',
                    'Townsville',
                    'Cairns',
                    'Toowoomba',
                    'Mackay',
                    'Rockhampton',
                    'Bundaberg',
                    'Hervey Bay',
                    'Gladstone',
                    'Mount Isa',
                    'Ipswich',
                    'Logan',
                    'Redland',
                    'Moreton Bay',
                    'Noosa',
                    'Caloundra',
                    'Maroochydore',
                    'Surfers Paradise',
                    'Broadbeach',
                    'Southport',
                ),
                'SA' => array(
                    'Adelaide',
                    'Mount Gambier',
                    'Whyalla',
                    'Murray Bridge',
                    'Port Augusta',
                    'Port Pirie',
                    'Port Lincoln',
                    'Victor Harbor',
                    'Gawler',
                    'Mount Barker',
                    'Salisbury',
                    'Elizabeth',
                    'Tea Tree Gully',
                ),
                'TAS' => array(
                    'Hobart',
                    'Launceston',
                    'Devonport',
                    'Burnie',
                    'Kingston',
                    'Ulverstone',
                    'Glenorchy',
                    'Clarence',
                    'Sorell',
                ),
                'VIC' => array(
                    'Melbourne',
                    'Geelong',
                    'Ballarat',
                    'Bendigo',
                    'Shepparton',
                    'Mildura',
                    'Warrnambool',
                    'Wodonga',
                    'Traralgon',
                    'Wangaratta',
                    'Horsham',
                    'Sale',
                    'Bairnsdale',
                    'Echuca',
                    'Frankston',
                    'Dandenong',
                    'Box Hill',
                    'St Kilda',
                    'South Yarra',
                    'Richmond',
                    'Fitzroy',
                    'Carlton',
                    'Brunswick',
                    'Footscray',
                    'Williamstown',
                    'Brighton',
                    'Hawthorn',
                    'Camberwell',
                    'Doncaster',
                    'Glen Waverley',
                ),
                'WA' => array(
                    'Perth',
                    'Mandurah',
                    'Bunbury',
                    'Geraldton',
                    'Kalgoorlie',
                    'Albany',
                    'Karratha',
                    'Broome',
                    'Port Hedland',
                    'Busselton',
                    'Rockingham',
                    'Fremantle',
                    'Joondalup',
                    'Wanneroo',
                    'Armadale',
                    'Gosnells',
                    'Canning',
                    'Stirling',
                    'Scarborough',
                    'Cottesloe',
                    'Subiaco',
                    'Claremont',
                ),
            ),
            // New Zealand - Major cities by region
            'NZ' => array(
                'AUK' => array('Auckland', 'Manukau', 'North Shore', 'Waitakere'),
                'WGN' => array('Wellington', 'Lower Hutt', 'Upper Hutt', 'Porirua'),
                'CAN' => array('Christchurch', 'Timaru', 'Ashburton'),
                'WKO' => array('Hamilton', 'Cambridge', 'Te Awamutu'),
                'BOP' => array('Tauranga', 'Rotorua', 'Whakatane'),
                'OTA' => array('Dunedin', 'Queenstown', 'Oamaru'),
                'MWT' => array('Palmerston North', 'Whanganui', 'Levin'),
                'HKB' => array('Napier', 'Hastings'),
                'TKI' => array('New Plymouth', 'Hawera'),
                'NTL' => array('Whangarei', 'Kerikeri'),
                'STL' => array('Invercargill', 'Gore'),
            ),
        );
    }

    /**
     * Check if country has detailed state/city data
     *
     * @param string $country_code
     * @return bool
     */
    public static function has_detailed_data($country_code) {
        $countries_with_data = array('AU', 'NZ', 'US', 'CA', 'GB');
        return in_array($country_code, $countries_with_data);
    }

    /**
     * Get country name by code
     *
     * @param string $country_code
     * @return string|null
     */
    public static function get_country_name($country_code) {
        $countries = self::get_countries();
        return isset($countries[$country_code]) ? $countries[$country_code] : null;
    }

    /**
     * Get state name by code
     *
     * @param string $country_code
     * @param string $state_code
     * @return string|null
     */
    public static function get_state_name($country_code, $state_code) {
        $states = self::get_states($country_code);
        return isset($states[$state_code]) ? $states[$state_code] : null;
    }
}
