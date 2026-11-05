<?php

/*
|--------------------------------------------------------------------------
| AI Asset Research
|--------------------------------------------------------------------------
|
| Settings for the "Auto-fill with AI" feature. The AI only ever creates
| suggestions; a person must approve a suggestion before it touches an asset.
|
| Spec field types:
|   string  - free text
|   number  - JSON number only (the unit is part of the field name)
|   boolean - JSON true/false only
|   enum    - one of 'options'
|   list    - array of short strings
|
| Every spec field is nullable.
|
*/

$computerSpecs = [
    'processor' => ['type' => 'string', 'label' => 'Processor'],
    'ram_gb' => ['type' => 'number', 'label' => 'RAM', 'unit' => 'GB'],
    'storage_type' => ['type' => 'string', 'label' => 'Storage type'],
    'storage_gb' => ['type' => 'number', 'label' => 'Storage', 'unit' => 'GB'],
    'display_size_in' => ['type' => 'number', 'label' => 'Display size', 'unit' => 'in'],
    'resolution' => ['type' => 'string', 'label' => 'Resolution'],
    'gpu' => ['type' => 'string', 'label' => 'GPU'],
    'os' => ['type' => 'string', 'label' => 'Operating system'],
    'ports' => ['type' => 'list', 'label' => 'Ports'],
    'weight_kg' => ['type' => 'number', 'label' => 'Weight', 'unit' => 'kg'],
];

return [

    /*
    | Spec category per asset type (asset_names.assetname_abbreviation).
    | Types not listed here use 'default_category'.
    */
    'asset_type_categories' => [
        'DPC' => 'desktop',          // Desktop Computer
        'LTP' => 'laptop',           // Laptop Computer
        'SRV' => 'server',           // Server Machine
        'LJP' => 'printer',          // LaserJet Printer
        'PCM' => 'printer',          // Photocopier Machine (usually type "multifunction")
        'SCN' => 'scanner',          // Scanner
        'EHD' => 'hard_drive',       // External Hard Drive
        'UPS' => 'ups',              // UPS Battery Backup
        'PRJ' => 'projector',        // Projector
        'TVS' => 'display',          // Television Set
        'SBD' => 'display',          // Smart Board
        'NSW' => 'network_device',   // Network Switch
        'WRT' => 'network_device',   // Wireless Router
        'CCTV' => 'camera',          // CCTV Camera
        'ACR' => 'air_conditioner',  // Air Conditioner
        'GEN' => 'generator',        // Generator Set
        'BSH' => 'furniture',        // Bookshelf
        'CFT' => 'furniture',        // Conference Table
        'FBC' => 'furniture',        // Filing Cabinet
        'OFC' => 'furniture',        // Office Chair
        'ODK' => 'furniture',        // Office Desk
        // 'USB' => 'usb_drive', // Add once a USB flash drive asset type exists.
    ],

    /*
    | Category for asset types not listed above (e.g. fans, telephones, water
    | dispensers, and any type added later). Set to null to disable AI research
    | for unlisted types.
    */
    'default_category' => 'general',

    /*
    | Spec schema per category.
    */
    'categories' => [
        'laptop' => $computerSpecs,
        'desktop' => $computerSpecs,
        'server' => $computerSpecs,

        'printer' => [
            'type' => ['type' => 'enum', 'label' => 'Printer type', 'options' => ['laser', 'inkjet', 'thermal', 'multifunction']],
            'color' => ['type' => 'boolean', 'label' => 'Colour'],
            'print_speed_ppm' => ['type' => 'number', 'label' => 'Print speed', 'unit' => 'ppm'],
            'max_paper_size' => ['type' => 'string', 'label' => 'Max paper size'],
            'duplex' => ['type' => 'boolean', 'label' => 'Duplex'],
            'connectivity' => ['type' => 'list', 'label' => 'Connectivity'],
            'consumable_model' => ['type' => 'string', 'label' => 'Toner / ink model'],
        ],

        'scanner' => [
            'scan_type' => ['type' => 'enum', 'label' => 'Scan type', 'options' => ['flatbed', 'sheetfed', 'handheld']],
            'optical_resolution_dpi' => ['type' => 'number', 'label' => 'Optical resolution', 'unit' => 'dpi'],
            'max_paper_size' => ['type' => 'string', 'label' => 'Max paper size'],
            'scan_speed_ppm' => ['type' => 'number', 'label' => 'Scan speed', 'unit' => 'ppm'],
            'duplex' => ['type' => 'boolean', 'label' => 'Duplex'],
            'connectivity' => ['type' => 'list', 'label' => 'Connectivity'],
        ],

        'hard_drive' => [
            'form_factor' => ['type' => 'string', 'label' => 'Form factor'],
            'interface' => ['type' => 'string', 'label' => 'Interface'],
            'capacity_gb' => ['type' => 'number', 'label' => 'Capacity', 'unit' => 'GB'],
            'rpm_or_ssd_type' => ['type' => 'string', 'label' => 'RPM / SSD type'],
            'read_speed_mbps' => ['type' => 'number', 'label' => 'Read speed', 'unit' => 'MB/s'],
            'write_speed_mbps' => ['type' => 'number', 'label' => 'Write speed', 'unit' => 'MB/s'],
        ],

        'usb_drive' => [
            'capacity_gb' => ['type' => 'number', 'label' => 'Capacity', 'unit' => 'GB'],
            'usb_version' => ['type' => 'string', 'label' => 'USB version'],
            'read_speed_mbps' => ['type' => 'number', 'label' => 'Read speed', 'unit' => 'MB/s'],
            'write_speed_mbps' => ['type' => 'number', 'label' => 'Write speed', 'unit' => 'MB/s'],
        ],

        'ups' => [
            'topology' => ['type' => 'enum', 'label' => 'Topology', 'options' => ['standby', 'line-interactive', 'online']],
            'capacity_va' => ['type' => 'number', 'label' => 'Capacity', 'unit' => 'VA'],
            'capacity_w' => ['type' => 'number', 'label' => 'Output power', 'unit' => 'W'],
            'runtime_half_load_min' => ['type' => 'number', 'label' => 'Runtime at half load', 'unit' => 'min'],
            'outlets' => ['type' => 'number', 'label' => 'Outlets'],
            'battery_type' => ['type' => 'string', 'label' => 'Battery type'],
            'form_factor' => ['type' => 'string', 'label' => 'Form factor'],
        ],

        'projector' => [
            'technology' => ['type' => 'string', 'label' => 'Technology'],
            'brightness_lumens' => ['type' => 'number', 'label' => 'Brightness', 'unit' => 'lumens'],
            'native_resolution' => ['type' => 'string', 'label' => 'Native resolution'],
            'contrast_ratio' => ['type' => 'string', 'label' => 'Contrast ratio'],
            'lamp_life_hours' => ['type' => 'number', 'label' => 'Lamp / light source life', 'unit' => 'h'],
            'inputs' => ['type' => 'list', 'label' => 'Inputs'],
        ],

        'display' => [
            'screen_size_in' => ['type' => 'number', 'label' => 'Screen size', 'unit' => 'in'],
            'resolution' => ['type' => 'string', 'label' => 'Resolution'],
            'panel_type' => ['type' => 'string', 'label' => 'Panel type'],
            'touch' => ['type' => 'boolean', 'label' => 'Touch screen'],
            'smart_os' => ['type' => 'string', 'label' => 'Smart platform / OS'],
            'inputs' => ['type' => 'list', 'label' => 'Inputs'],
        ],

        'network_device' => [
            'device_type' => ['type' => 'enum', 'label' => 'Device type', 'options' => ['switch', 'router', 'access point', 'firewall']],
            'ports' => ['type' => 'number', 'label' => 'Ethernet ports'],
            'port_speed' => ['type' => 'string', 'label' => 'Port speed'],
            'poe' => ['type' => 'boolean', 'label' => 'PoE'],
            'managed' => ['type' => 'boolean', 'label' => 'Managed'],
            'wifi_standard' => ['type' => 'string', 'label' => 'Wi-Fi standard'],
            'rack_mountable' => ['type' => 'boolean', 'label' => 'Rack mountable'],
        ],

        'camera' => [
            'camera_type' => ['type' => 'string', 'label' => 'Camera type'],
            'resolution_mp' => ['type' => 'number', 'label' => 'Resolution', 'unit' => 'MP'],
            'night_vision_range_m' => ['type' => 'number', 'label' => 'Night vision range', 'unit' => 'm'],
            'connection' => ['type' => 'enum', 'label' => 'Connection', 'options' => ['IP', 'analog', 'HD-TVI', 'HD-CVI', 'AHD', 'wireless']],
            'poe' => ['type' => 'boolean', 'label' => 'PoE'],
            'weatherproof_rating' => ['type' => 'string', 'label' => 'Weatherproof rating'],
        ],

        'air_conditioner' => [
            'ac_type' => ['type' => 'string', 'label' => 'Type'],
            'cooling_capacity_btu' => ['type' => 'number', 'label' => 'Cooling capacity', 'unit' => 'BTU'],
            'horsepower' => ['type' => 'number', 'label' => 'Horsepower', 'unit' => 'HP'],
            'inverter' => ['type' => 'boolean', 'label' => 'Inverter'],
            'refrigerant' => ['type' => 'string', 'label' => 'Refrigerant'],
            'energy_rating' => ['type' => 'string', 'label' => 'Energy rating'],
            'power_watts' => ['type' => 'number', 'label' => 'Power consumption', 'unit' => 'W'],
        ],

        'generator' => [
            'prime_power_kva' => ['type' => 'number', 'label' => 'Prime power', 'unit' => 'kVA'],
            'standby_power_kva' => ['type' => 'number', 'label' => 'Standby power', 'unit' => 'kVA'],
            'fuel_type' => ['type' => 'enum', 'label' => 'Fuel type', 'options' => ['diesel', 'petrol', 'gas', 'hybrid']],
            'phase' => ['type' => 'enum', 'label' => 'Phase', 'options' => ['single', 'three']],
            'engine_model' => ['type' => 'string', 'label' => 'Engine model'],
            'fuel_tank_l' => ['type' => 'number', 'label' => 'Fuel tank', 'unit' => 'L'],
            'enclosure' => ['type' => 'string', 'label' => 'Enclosure'],
        ],

        'furniture' => [
            'furniture_type' => ['type' => 'string', 'label' => 'Type'],
            'material' => ['type' => 'string', 'label' => 'Material'],
            'dimensions_cm' => ['type' => 'string', 'label' => 'Dimensions (W × D × H)', 'unit' => 'cm'],
            'color' => ['type' => 'string', 'label' => 'Colour'],
            'seating_capacity' => ['type' => 'number', 'label' => 'Seating capacity'],
            'weight_kg' => ['type' => 'number', 'label' => 'Weight', 'unit' => 'kg'],
        ],

        // Fallback for any other asset type. The asset type name is sent to the AI.
        'general' => [
            'product_type' => ['type' => 'string', 'label' => 'Product type'],
            'power_watts' => ['type' => 'number', 'label' => 'Power', 'unit' => 'W'],
            'power_source' => ['type' => 'string', 'label' => 'Power source'],
            'dimensions_cm' => ['type' => 'string', 'label' => 'Dimensions (W × D × H)', 'unit' => 'cm'],
            'weight_kg' => ['type' => 'number', 'label' => 'Weight', 'unit' => 'kg'],
            'color' => ['type' => 'string', 'label' => 'Colour'],
            'connectivity' => ['type' => 'list', 'label' => 'Connectivity'],
            'key_features' => ['type' => 'list', 'label' => 'Key features'],
        ],
    ],

    /*
    | Retailer domains used for price searches (e.g. 'amazon.com', 'jumia.com.gh').
    | When non-empty, price searches are limited to these domains.
    */
    'price_domains' => [],

    /*
    | Prices outside these bounds (in whatever currency was found) are flagged
    | as implausible and the suggestion is marked low confidence.
    */
    'plausible_price_range' => [
        'laptop' => ['min' => 100, 'max' => null],
        'desktop' => ['min' => 100, 'max' => null],
        'printer' => ['min' => 30, 'max' => null],
        'scanner' => ['min' => 30, 'max' => null],
        'hard_drive' => ['min' => 10, 'max' => null],
        'usb_drive' => ['min' => null, 'max' => 500],
        'server' => ['min' => 300, 'max' => null],
        'ups' => ['min' => 20, 'max' => null],
        'projector' => ['min' => 50, 'max' => null],
        'display' => ['min' => 50, 'max' => null],
        'network_device' => ['min' => 10, 'max' => null],
        'camera' => ['min' => 10, 'max' => null],
        'air_conditioner' => ['min' => 100, 'max' => null],
        'generator' => ['min' => 100, 'max' => null],
        'furniture' => ['min' => 10, 'max' => null],
        // 'general' has no bounds: the types it covers vary too much.
    ],

    /*
    | A new research request is skipped while a pending suggestion younger
    | than this exists for the asset (unless the request is forced).
    */
    'dedupe_hours' => 24,

];
