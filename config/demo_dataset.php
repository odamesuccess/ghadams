<?php

/*
|--------------------------------------------------------------------------
| GhanaDAMS synthetic demonstration dataset
|--------------------------------------------------------------------------
|
| Everything generated from this file is SYNTHETIC DEMONSTRATION DATA for
| development, testing and research demonstration. It is not a record of
| actual Ghana Education Service procurement, inventory, maintenance or
| finance.
|
| PRICE ASSUMPTIONS: every GHS band and the nominal price index below are
| rough estimates chosen for testing. They are not market research, not
| verified historical Ghana prices, not GES prices and not exchange rates.
|
| MODEL CATALOGUE: model names are used only inside the year window given
| ('from'..'to'), which never starts before the model's public release year.
| Where a model's release year could not be stated with confidence the entry
| uses a generic description with no model number. The "config" text is the
| configuration "as recorded" on the synthetic register entry; it is not a
| manufacturer specification and is never written to the `specifications`
| column (that column is reserved for human-approved AI research).
|
| Run:  php artisan demo:synthetic-dataset            (dry run)
|       php artisan demo:synthetic-dataset --execute --confirm=SYNTHETIC-2000
|
*/

return [

    'dataset' => 'ghadams-synthetic-demo',
    'version' => '1.0.0',
    'label' => 'SYNTHETIC DEMONSTRATION DATA',
    'seed' => 20261105,

    // Never generate any event after this date (inclusive upper bound).
    'start_date' => '2012-01-01',
    'end_date' => '2026-11-05',

    // Only these environments may write. Production is always refused.
    'allowed_environments' => ['local', 'testing'],

    'asset_code_prefix' => 'SYN',
    'serial_prefix' => 'SYN',
    'user_email_domain' => 'demo.ghadams.test',

    /*
    | Exact acquisitions per year. Must total 2,000.
    | Synthetic pattern with peaks in 2013, 2016, 2019 and 2022.
    */
    'year_counts' => [
        2012 => 124, 2013 => 158, 2014 => 118, 2015 => 134, 2016 => 168,
        2017 => 129, 2018 => 123, 2019 => 156, 2020 => 117, 2021 => 134,
        2022 => 186, 2023 => 136, 2024 => 125, 2025 => 118, 2026 => 74,
    ],

    /*
    | Nominal GHS price multiplier relative to 2012 (ASSUMPTION for testing).
    | Loosely follows the shape of cedi depreciation and inflation; it is not an
    | official index.
    */
    'price_index' => [
        2012 => 1.00, 2013 => 1.10, 2014 => 1.35, 2015 => 1.60, 2016 => 1.80,
        2017 => 2.00, 2018 => 2.20, 2019 => 2.45, 2020 => 2.70, 2021 => 2.90,
        2022 => 3.80, 2023 => 5.00, 2024 => 5.80, 2025 => 6.40, 2026 => 6.70,
    ],

    // Relative purchasing activity per month before per-year randomisation.
    'month_weights' => [
        1 => 0.45, 2 => 0.70, 3 => 1.10, 4 => 1.00, 5 => 0.90, 6 => 1.20,
        7 => 1.00, 8 => 0.80, 9 => 1.10, 10 => 1.20, 11 => 1.30, 12 => 0.70,
    ],

    // Fixed-date days treated as non-working when picking approval/purchase
    // dates. Approximation only: actual observance is not asserted.
    'assumed_non_working_days' => ['01-01', '03-06', '05-01', '12-25', '12-26'],

    // Batch sizes: routine purchases, medium batches, occasional large events.
    'batch_profiles' => [
        'routine' => ['weight' => 55, 'min' => 1, 'max' => 3],
        'medium' => ['weight' => 35, 'min' => 4, 'max' => 12],
        'large' => ['weight' => 10, 'min' => 15, 'max' => 40],
    ],

    // Types bought together in medium/large procurement events.
    'themes' => [
        'ict_refresh' => ['DPC', 'LTP', 'UPS', 'LJP', 'EHD', 'SCN'],
        'furniture_fitout' => ['ODK', 'OFC', 'FBC', 'BSH', 'CFT'],
        'network_upgrade' => ['NSW', 'WRT', 'NRC', 'SRV', 'UPS'],
        'office_equipment' => ['PCM', 'SCN', 'PRJ', 'TEL', 'TVS', 'PAS', 'BAD'],
        'facilities' => ['ACR', 'STF', 'CLF', 'WDP', 'GEN'],
    ],

    'source_weights' => [
        'Government Procurement' => 45,
        'Purchase - Local Vendor' => 20,
        'Replacement Purchase' => 8,
        'Project Allocation' => 6,
        'Donations' => 5,
        'International Aid' => 3,
        'NGO Support' => 3,
        'Emergency Procurement' => 2,
        'Research Grant' => 2,
        'Corporate Sponsorship' => 2,
        'Pilot Programme' => 2,
        'Lease Acquisition' => 1,
        'Purchase - Foreign Vendor' => 1,
    ],

    // Warranty profiles: months => weight (null = warranty not on record).
    'warranty_profiles' => [
        'ict' => [12 => 45, 24 => 15, 36 => 25, 'none' => 15],
        'laptop' => [12 => 40, 36 => 40, 'none' => 20],
        'storage' => [12 => 20, 24 => 30, 36 => 30, 'none' => 20],
        'infrastructure' => [36 => 60, 12 => 10, 60 => 15, 'none' => 15],
        'copier' => [12 => 70, 24 => 15, 'none' => 15],
        'furniture' => [12 => 25, 'none' => 75],
        'appliance' => [12 => 55, 24 => 25, 'none' => 20],
        'generator' => [12 => 50, 24 => 30, 'none' => 20],
        'audio' => [12 => 60, 'none' => 40],
    ],

    /*
    | Asset types, keyed by asset_names.assetname_abbreviation.
    | weight      relative share of purchases
    | trend       weight change per year after 2012 (e.g. 0.06 = +6%/year)
    | band        2012 GHS unit price band (ASSUMPTION), scaled by price_index
    | units       preferred offices (unit abbreviations); others still possible
    | shared      held by the unit asset officer rather than an individual
    | movable     eligible for transfer requests
    | models      brand/model catalogue with year windows and price tier
    */
    'types' => [
        'DPC' => [
            'weight' => 230, 'trend' => -0.025, 'band' => [1400, 2600], 'warranty' => 'ict', 'movable' => true,
            'models' => [
                ['brand' => 'HP', 'model' => 'Compaq Pro 6300 SFF', 'from' => 2012, 'to' => 2013, 'tier' => 1.00, 'config' => 'Intel Core i3 (3rd gen), 4 GB RAM, 500 GB HDD, Windows 7 Professional'],
                ['brand' => 'Dell', 'model' => 'OptiPlex 7010', 'from' => 2012, 'to' => 2014, 'tier' => 1.15, 'config' => 'Intel Core i5 (3rd gen), 4 GB RAM, 500 GB HDD, Windows 7 Professional'],
                ['brand' => 'Lenovo', 'model' => 'ThinkCentre M72e', 'from' => 2012, 'to' => 2013, 'tier' => 0.95, 'config' => 'Intel Core i3 (3rd gen), 4 GB RAM, 500 GB HDD'],
                ['brand' => 'HP', 'model' => 'ProDesk 400 G1', 'from' => 2014, 'to' => 2015, 'tier' => 1.00, 'config' => 'Intel Core i3 (4th gen), 4 GB RAM, 500 GB HDD, Windows 8.1 Pro'],
                ['brand' => 'Dell', 'model' => 'OptiPlex 3020', 'from' => 2014, 'to' => 2015, 'tier' => 0.95, 'config' => 'Intel Core i3 (4th gen), 4 GB RAM, 500 GB HDD'],
                ['brand' => 'Lenovo', 'model' => 'ThinkCentre M73', 'from' => 2014, 'to' => 2016, 'tier' => 1.05, 'config' => 'Intel Core i5 (4th gen), 4 GB RAM, 500 GB HDD'],
                ['brand' => 'HP', 'model' => 'ProDesk 400 G3', 'from' => 2016, 'to' => 2017, 'tier' => 1.05, 'config' => 'Intel Core i5 (6th gen), 8 GB RAM, 1 TB HDD, Windows 10 Pro'],
                ['brand' => 'Lenovo', 'model' => 'ThinkCentre M700', 'from' => 2016, 'to' => 2017, 'tier' => 0.95, 'config' => 'Intel Core i3 (6th gen), 4 GB RAM, 500 GB HDD, Windows 10 Pro'],
                ['brand' => 'Dell', 'model' => 'OptiPlex 3050', 'from' => 2017, 'to' => 2018, 'tier' => 1.05, 'config' => 'Intel Core i5 (7th gen), 8 GB RAM, 1 TB HDD, Windows 10 Pro'],
                ['brand' => 'HP', 'model' => 'ProDesk 400 G5', 'from' => 2018, 'to' => 2019, 'tier' => 1.10, 'config' => 'Intel Core i5 (8th gen), 8 GB RAM, 256 GB SSD, Windows 10 Pro'],
                ['brand' => 'Lenovo', 'model' => 'ThinkCentre M720t', 'from' => 2018, 'to' => 2020, 'tier' => 1.00, 'config' => 'Intel Core i5 (8th gen), 8 GB RAM, 1 TB HDD'],
                ['brand' => 'Dell', 'model' => 'OptiPlex 3070', 'from' => 2019, 'to' => 2020, 'tier' => 1.05, 'config' => 'Intel Core i5 (9th gen), 8 GB RAM, 256 GB SSD'],
                ['brand' => 'HP', 'model' => 'ProDesk 400 G7', 'from' => 2020, 'to' => 2022, 'tier' => 1.05, 'config' => 'Intel Core i5 (10th gen), 8 GB RAM, 256 GB SSD, Windows 10 Pro'],
                ['brand' => 'Dell', 'model' => 'OptiPlex 3080', 'from' => 2020, 'to' => 2021, 'tier' => 1.00, 'config' => 'Intel Core i5 (10th gen), 8 GB RAM, 256 GB SSD'],
                ['brand' => 'Lenovo', 'model' => 'ThinkCentre M70t', 'from' => 2020, 'to' => 2022, 'tier' => 1.05, 'config' => 'Intel Core i5 (10th gen), 8 GB RAM, 512 GB SSD'],
                ['brand' => 'Dell', 'model' => 'OptiPlex 3000', 'from' => 2022, 'to' => 2023, 'tier' => 1.10, 'config' => 'Intel Core i5 (12th gen), 8 GB RAM, 512 GB SSD, Windows 11 Pro'],
                ['brand' => 'HP', 'model' => 'Pro Tower 400 G9', 'from' => 2022, 'to' => 2025, 'tier' => 1.10, 'config' => 'Intel Core i5 (12th gen), 8 GB RAM, 512 GB SSD, Windows 11 Pro'],
                ['brand' => 'Lenovo', 'model' => 'ThinkCentre neo 50t', 'from' => 2022, 'to' => 2026, 'tier' => 1.05, 'config' => 'Intel Core i5 (12th gen), 8 GB RAM, 512 GB SSD, Windows 11 Pro'],
                ['brand' => 'Dell', 'model' => 'OptiPlex Tower 7010', 'from' => 2023, 'to' => 2026, 'tier' => 1.25, 'config' => 'Intel Core i5 (13th gen), 16 GB RAM, 512 GB SSD, Windows 11 Pro'],
            ],
        ],
        'LTP' => [
            'weight' => 210, 'trend' => 0.06, 'band' => [1500, 3200], 'warranty' => 'laptop', 'movable' => true,
            'models' => [
                ['brand' => 'HP', 'model' => 'ProBook 4540s', 'from' => 2012, 'to' => 2013, 'tier' => 1.00, 'config' => 'Intel Core i5 (3rd gen), 4 GB RAM, 500 GB HDD, 15.6-inch display'],
                ['brand' => 'Dell', 'model' => 'Latitude E5430', 'from' => 2012, 'to' => 2014, 'tier' => 1.05, 'config' => 'Intel Core i5 (3rd gen), 4 GB RAM, 500 GB HDD, 14-inch display'],
                ['brand' => 'Lenovo', 'model' => 'ThinkPad E430', 'from' => 2012, 'to' => 2013, 'tier' => 0.90, 'config' => 'Intel Core i3 (3rd gen), 4 GB RAM, 500 GB HDD, 14-inch display'],
                ['brand' => 'HP', 'model' => 'ProBook 450 G1', 'from' => 2013, 'to' => 2014, 'tier' => 1.00, 'config' => 'Intel Core i5 (4th gen), 4 GB RAM, 500 GB HDD, 15.6-inch display'],
                ['brand' => 'Dell', 'model' => 'Latitude E5440', 'from' => 2014, 'to' => 2015, 'tier' => 1.05, 'config' => 'Intel Core i5 (4th gen), 4 GB RAM, 500 GB HDD, 14-inch display'],
                ['brand' => 'Lenovo', 'model' => 'ThinkPad E450', 'from' => 2015, 'to' => 2016, 'tier' => 0.95, 'config' => 'Intel Core i5 (5th gen), 4 GB RAM, 500 GB HDD, 14-inch display'],
                ['brand' => 'HP', 'model' => 'ProBook 450 G3', 'from' => 2016, 'to' => 2017, 'tier' => 1.00, 'config' => 'Intel Core i5 (6th gen), 8 GB RAM, 1 TB HDD, 15.6-inch display'],
                ['brand' => 'Dell', 'model' => 'Latitude 3470', 'from' => 2016, 'to' => 2017, 'tier' => 0.95, 'config' => 'Intel Core i5 (6th gen), 4 GB RAM, 500 GB HDD, 14-inch display'],
                ['brand' => 'Lenovo', 'model' => 'ThinkPad E470', 'from' => 2017, 'to' => 2018, 'tier' => 0.95, 'config' => 'Intel Core i5 (7th gen), 8 GB RAM, 1 TB HDD, 14-inch display'],
                ['brand' => 'HP', 'model' => 'ProBook 450 G5', 'from' => 2018, 'to' => 2019, 'tier' => 1.05, 'config' => 'Intel Core i5 (8th gen), 8 GB RAM, 256 GB SSD, 15.6-inch display'],
                ['brand' => 'Dell', 'model' => 'Latitude 3490', 'from' => 2018, 'to' => 2019, 'tier' => 1.00, 'config' => 'Intel Core i5 (8th gen), 8 GB RAM, 256 GB SSD, 14-inch display'],
                ['brand' => 'HP', 'model' => 'ProBook 450 G7', 'from' => 2020, 'to' => 2021, 'tier' => 1.05, 'config' => 'Intel Core i5 (10th gen), 8 GB RAM, 256 GB SSD, 15.6-inch display'],
                ['brand' => 'Dell', 'model' => 'Latitude 3410', 'from' => 2020, 'to' => 2021, 'tier' => 1.00, 'config' => 'Intel Core i5 (10th gen), 8 GB RAM, 256 GB SSD, 14-inch display'],
                ['brand' => 'Lenovo', 'model' => 'ThinkPad E14 Gen 2', 'from' => 2021, 'to' => 2022, 'tier' => 1.00, 'config' => 'Intel Core i5 (11th gen), 8 GB RAM, 512 GB SSD, 14-inch display'],
                ['brand' => 'Dell', 'model' => 'Latitude 3420', 'from' => 2021, 'to' => 2022, 'tier' => 1.00, 'config' => 'Intel Core i5 (11th gen), 8 GB RAM, 256 GB SSD, 14-inch display'],
                ['brand' => 'HP', 'model' => 'EliteBook 840 G8', 'from' => 2021, 'to' => 2023, 'tier' => 1.45, 'config' => 'Intel Core i7 (11th gen), 16 GB RAM, 512 GB SSD, 14-inch display'],
                ['brand' => 'HP', 'model' => 'ProBook 450 G9', 'from' => 2022, 'to' => 2024, 'tier' => 1.05, 'config' => 'Intel Core i5 (12th gen), 8 GB RAM, 512 GB SSD, 15.6-inch display'],
                ['brand' => 'Lenovo', 'model' => 'ThinkPad E14 Gen 4', 'from' => 2022, 'to' => 2023, 'tier' => 1.00, 'config' => 'Intel Core i5 (12th gen), 8 GB RAM, 512 GB SSD, 14-inch display'],
                ['brand' => 'Dell', 'model' => 'Latitude 3440', 'from' => 2023, 'to' => 2026, 'tier' => 1.05, 'config' => 'Intel Core i5 (13th gen), 8 GB RAM, 512 GB SSD, 14-inch display'],
                ['brand' => 'HP', 'model' => 'ProBook 450 G10', 'from' => 2023, 'to' => 2026, 'tier' => 1.10, 'config' => 'Intel Core i5 (13th gen), 16 GB RAM, 512 GB SSD, 15.6-inch display'],
                ['brand' => 'HP', 'model' => 'EliteBook 840 G10', 'from' => 2023, 'to' => 2026, 'tier' => 1.50, 'config' => 'Intel Core i7 (13th gen), 16 GB RAM, 512 GB SSD, 14-inch display'],
                ['brand' => 'Lenovo', 'model' => 'ThinkPad E14 Gen 5', 'from' => 2023, 'to' => 2026, 'tier' => 1.05, 'config' => 'Intel Core i5 (13th gen), 16 GB RAM, 512 GB SSD, 14-inch display'],
            ],
        ],
        'LJP' => [
            'weight' => 150, 'trend' => 0.0, 'band' => [500, 1400], 'warranty' => 'ict', 'movable' => true,
            'units' => ['GAU', 'FIN', 'HRD', 'PSC', 'ODG', 'ECU', 'IAU'],
            'models' => [
                ['brand' => 'HP', 'model' => 'LaserJet Pro P1102', 'from' => 2012, 'to' => 2015, 'tier' => 0.60, 'config' => 'Monochrome laser printer, USB'],
                ['brand' => 'HP', 'model' => 'LaserJet Pro 400 M401dn', 'from' => 2012, 'to' => 2015, 'tier' => 1.10, 'config' => 'Monochrome laser printer, duplex, network'],
                ['brand' => 'Canon', 'model' => 'i-SENSYS LBP6030', 'from' => 2014, 'to' => 2018, 'tier' => 0.55, 'config' => 'Monochrome laser printer, USB'],
                ['brand' => 'HP', 'model' => 'LaserJet Pro M402dn', 'from' => 2016, 'to' => 2019, 'tier' => 1.05, 'config' => 'Monochrome laser printer, duplex, network'],
                ['brand' => 'HP', 'model' => 'LaserJet Pro MFP M426fdn', 'from' => 2016, 'to' => 2019, 'tier' => 1.50, 'config' => 'Monochrome laser multifunction (print/copy/scan/fax), duplex, network'],
                ['brand' => 'Brother', 'model' => 'HL-L2370DN', 'from' => 2018, 'to' => 2023, 'tier' => 0.80, 'config' => 'Monochrome laser printer, duplex, network'],
                ['brand' => 'Brother', 'model' => 'MFC-L2750DW', 'from' => 2018, 'to' => 2023, 'tier' => 1.10, 'config' => 'Monochrome laser multifunction, duplex, wireless'],
                ['brand' => 'HP', 'model' => 'LaserJet Pro M404dn', 'from' => 2019, 'to' => 2022, 'tier' => 1.05, 'config' => 'Monochrome laser printer, duplex, network'],
                ['brand' => 'HP', 'model' => 'LaserJet Pro MFP M428fdn', 'from' => 2019, 'to' => 2022, 'tier' => 1.50, 'config' => 'Monochrome laser multifunction, duplex, network'],
                ['brand' => 'Canon', 'model' => 'i-SENSYS MF445dw', 'from' => 2019, 'to' => 2023, 'tier' => 1.35, 'config' => 'Monochrome laser multifunction, duplex, wireless'],
                ['brand' => 'HP', 'model' => 'LaserJet Pro 4003dn', 'from' => 2022, 'to' => 2026, 'tier' => 1.05, 'config' => 'Monochrome laser printer, duplex, network'],
                ['brand' => 'HP', 'model' => 'LaserJet Pro MFP 4103fdn', 'from' => 2022, 'to' => 2026, 'tier' => 1.50, 'config' => 'Monochrome laser multifunction, duplex, network'],
            ],
        ],
        'PCM' => [
            'weight' => 45, 'trend' => 0.0, 'band' => [6000, 16000], 'warranty' => 'copier', 'shared' => true,
            'units' => ['GAU', 'ODG', 'FIN', 'HRD', 'PSC', 'ECU', 'TED', 'CRDD'],
            'models' => [
                ['brand' => 'Canon', 'model' => 'imageRUNNER 2520', 'from' => 2012, 'to' => 2014, 'tier' => 0.90, 'config' => 'A3 monochrome copier, 20 ppm'],
                ['brand' => 'Canon', 'model' => 'imageRUNNER 2525', 'from' => 2013, 'to' => 2017, 'tier' => 0.95, 'config' => 'A3 monochrome copier/printer, 25 ppm'],
                ['brand' => 'Kyocera', 'model' => 'TASKalfa 2551ci', 'from' => 2015, 'to' => 2017, 'tier' => 1.20, 'config' => 'A3 colour multifunction, 25 ppm'],
                ['brand' => 'Ricoh', 'model' => 'MP 2014', 'from' => 2016, 'to' => 2019, 'tier' => 0.75, 'config' => 'A3 monochrome copier, 20 ppm'],
                ['brand' => 'Xerox', 'model' => 'AltaLink C8045', 'from' => 2018, 'to' => 2022, 'tier' => 1.45, 'config' => 'A3 colour multifunction, 45 ppm'],
                ['brand' => 'Kyocera', 'model' => 'TASKalfa 2553ci', 'from' => 2019, 'to' => 2021, 'tier' => 1.20, 'config' => 'A3 colour multifunction, 25 ppm'],
                ['brand' => 'Canon', 'model' => 'imageRUNNER 2625i', 'from' => 2020, 'to' => 2023, 'tier' => 1.00, 'config' => 'A3 monochrome multifunction, 25 ppm'],
                ['brand' => 'Ricoh', 'model' => 'IM 2702', 'from' => 2021, 'to' => 2026, 'tier' => 0.85, 'config' => 'A3 monochrome multifunction, 27 ppm'],
                ['brand' => 'Canon', 'model' => 'imageRUNNER ADVANCE DX C3826i', 'from' => 2022, 'to' => 2026, 'tier' => 1.40, 'config' => 'A3 colour multifunction, 26 ppm'],
            ],
        ],
        'SCN' => [
            'weight' => 55, 'trend' => 0.0, 'band' => [300, 900], 'warranty' => 'ict', 'movable' => true,
            'units' => ['HRD', 'FIN', 'LAU', 'IAU', 'SRIM', 'GAU', 'ECU'],
            'models' => [
                ['brand' => 'Canon', 'model' => 'CanoScan LiDE 110', 'from' => 2012, 'to' => 2014, 'tier' => 0.40, 'config' => 'A4 flatbed scanner, USB'],
                ['brand' => 'Fujitsu', 'model' => 'fi-7160', 'from' => 2014, 'to' => 2021, 'tier' => 2.00, 'config' => 'Duplex document scanner with automatic document feeder'],
                ['brand' => 'HP', 'model' => 'ScanJet Pro 3000 s2', 'from' => 2014, 'to' => 2016, 'tier' => 1.40, 'config' => 'Sheet-feed duplex document scanner'],
                ['brand' => 'HP', 'model' => 'ScanJet Pro 2500 f1', 'from' => 2015, 'to' => 2020, 'tier' => 1.20, 'config' => 'Flatbed scanner with automatic document feeder'],
                ['brand' => 'Epson', 'model' => 'Perfection V39', 'from' => 2015, 'to' => 2021, 'tier' => 0.60, 'config' => 'A4 flatbed scanner, USB'],
                ['brand' => 'Epson', 'model' => 'WorkForce DS-530', 'from' => 2017, 'to' => 2021, 'tier' => 1.60, 'config' => 'Duplex document scanner with automatic document feeder'],
                ['brand' => 'Canon', 'model' => 'imageFORMULA DR-C225 II', 'from' => 2018, 'to' => 2024, 'tier' => 1.30, 'config' => 'Compact duplex document scanner'],
                ['brand' => 'Canon', 'model' => 'CanoScan LiDE 300', 'from' => 2018, 'to' => 2026, 'tier' => 0.45, 'config' => 'A4 flatbed scanner, USB'],
                ['brand' => 'Epson', 'model' => 'WorkForce DS-530 II', 'from' => 2021, 'to' => 2026, 'tier' => 1.70, 'config' => 'Duplex document scanner with automatic document feeder'],
            ],
        ],
        'PRJ' => [
            'weight' => 70, 'trend' => 0.0, 'band' => [1200, 2800], 'warranty' => 'ict', 'movable' => true,
            'units' => ['TED', 'CRDD', 'PBME', 'MEU', 'ODG', 'GCU', 'SHEP', 'NSRU'],
            'models' => [
                ['brand' => 'Epson', 'model' => 'EB-X12', 'from' => 2012, 'to' => 2013, 'tier' => 0.95, 'config' => '3LCD XGA projector'],
                ['brand' => 'Epson', 'model' => 'EB-X18', 'from' => 2014, 'to' => 2015, 'tier' => 1.00, 'config' => '3LCD XGA projector'],
                ['brand' => 'BenQ', 'model' => 'MS506', 'from' => 2014, 'to' => 2016, 'tier' => 0.75, 'config' => 'DLP SVGA projector'],
                ['brand' => 'Epson', 'model' => 'EB-X41', 'from' => 2017, 'to' => 2019, 'tier' => 1.00, 'config' => '3LCD XGA projector'],
                ['brand' => 'BenQ', 'model' => 'MX550', 'from' => 2018, 'to' => 2020, 'tier' => 0.85, 'config' => 'DLP XGA projector'],
                ['brand' => 'ViewSonic', 'model' => 'PA503S', 'from' => 2018, 'to' => 2022, 'tier' => 0.75, 'config' => 'DLP SVGA projector'],
                ['brand' => 'Epson', 'model' => 'EB-X49', 'from' => 2019, 'to' => 2022, 'tier' => 1.05, 'config' => '3LCD XGA projector'],
                ['brand' => 'BenQ', 'model' => 'MW560', 'from' => 2019, 'to' => 2023, 'tier' => 0.95, 'config' => 'DLP WXGA projector'],
                ['brand' => 'Epson', 'model' => 'EB-FH06', 'from' => 2020, 'to' => 2024, 'tier' => 1.20, 'config' => '3LCD Full HD projector'],
                ['brand' => 'Epson', 'model' => 'EB-E20', 'from' => 2021, 'to' => 2025, 'tier' => 0.95, 'config' => '3LCD XGA projector'],
                ['brand' => 'Panasonic', 'model' => 'PT-TMZ400', 'from' => 2023, 'to' => 2026, 'tier' => 1.60, 'config' => 'Laser LCD WUXGA projector'],
            ],
        ],
        'UPS' => [
            'weight' => 150, 'trend' => 0.0, 'band' => [250, 1400], 'warranty' => 'ict', 'movable' => true,
            'models' => [
                ['brand' => 'Mercury', 'model' => null, 'from' => 2012, 'to' => 2020, 'tier' => 0.55, 'config' => '1000VA line-interactive UPS (generic, model not recorded)'],
                ['brand' => 'APC', 'model' => 'Back-UPS Pro 1500', 'part' => 'BR1500GI', 'from' => 2012, 'to' => 2026, 'tier' => 1.60, 'config' => '1500VA line-interactive UPS'],
                ['brand' => 'APC', 'model' => 'Smart-UPS 1500', 'part' => 'SMT1500I', 'from' => 2012, 'to' => 2026, 'tier' => 2.40, 'config' => '1500VA line-interactive UPS, pure sine wave'],
                ['brand' => 'Eaton', 'model' => '5E 850i', 'from' => 2014, 'to' => 2022, 'tier' => 0.65, 'config' => '850VA line-interactive UPS'],
                ['brand' => 'Eaton', 'model' => '5SC 1500i', 'from' => 2017, 'to' => 2026, 'tier' => 2.00, 'config' => '1500VA line-interactive UPS, pure sine wave'],
                ['brand' => 'APC', 'model' => 'Back-UPS 650', 'part' => 'BX650LI-MS', 'from' => 2017, 'to' => 2026, 'tier' => 0.45, 'config' => '650VA line-interactive UPS'],
                ['brand' => 'APC', 'model' => 'Back-UPS 1100', 'part' => 'BX1100LI-MS', 'from' => 2017, 'to' => 2026, 'tier' => 0.80, 'config' => '1100VA line-interactive UPS'],
            ],
        ],
        'NSW' => [
            'weight' => 50, 'trend' => 0.02, 'band' => [400, 1200], 'warranty' => 'infrastructure', 'shared' => true,
            'units' => ['ICTD', 'SRIM'],
            'models' => [
                ['brand' => 'TP-Link', 'model' => 'TL-SG1016D', 'from' => 2012, 'to' => 2026, 'tier' => 0.70, 'config' => '16-port Gigabit unmanaged switch'],
                ['brand' => 'TP-Link', 'model' => 'TL-SG1024D', 'from' => 2012, 'to' => 2026, 'tier' => 1.00, 'config' => '24-port Gigabit unmanaged switch'],
                ['brand' => 'D-Link', 'model' => 'DGS-1024D', 'from' => 2012, 'to' => 2020, 'tier' => 0.95, 'config' => '24-port Gigabit unmanaged switch'],
                ['brand' => 'Cisco', 'model' => 'Catalyst 2960', 'part' => 'WS-C2960-24TT-L', 'from' => 2012, 'to' => 2013, 'tier' => 3.50, 'config' => '24-port managed switch'],
                ['brand' => 'Cisco', 'model' => 'Catalyst 2960-X', 'part' => 'WS-C2960X-24TS-L', 'from' => 2014, 'to' => 2020, 'tier' => 4.50, 'config' => '24-port Gigabit managed switch, 4 SFP uplinks'],
                ['brand' => 'Cisco', 'model' => 'SG350-28', 'from' => 2017, 'to' => 2021, 'tier' => 2.20, 'config' => '28-port Gigabit managed switch'],
                ['brand' => 'Ubiquiti', 'model' => 'UniFi Switch 24', 'part' => 'US-24', 'from' => 2017, 'to' => 2020, 'tier' => 1.80, 'config' => '24-port Gigabit managed switch'],
                ['brand' => 'TP-Link', 'model' => 'TL-SG3428', 'from' => 2020, 'to' => 2026, 'tier' => 1.90, 'config' => '24-port Gigabit L2+ managed switch, 4 SFP slots'],
                ['brand' => 'Cisco', 'model' => 'Catalyst 9200L', 'part' => 'C9200L-24T-4G', 'from' => 2020, 'to' => 2026, 'tier' => 7.00, 'config' => '24-port Gigabit managed switch, 4x1G uplinks'],
                ['brand' => 'Ubiquiti', 'model' => 'UniFi Switch 24 PoE', 'part' => 'USW-24-POE', 'from' => 2021, 'to' => 2026, 'tier' => 2.60, 'config' => '24-port Gigabit managed PoE switch'],
            ],
        ],
        'WRT' => [
            'weight' => 40, 'trend' => 0.02, 'band' => [150, 450], 'warranty' => 'ict', 'shared' => true,
            'units' => ['ICTD', 'SRIM', 'ODG', 'GAU'],
            'models' => [
                ['brand' => 'D-Link', 'model' => 'DIR-615', 'from' => 2012, 'to' => 2016, 'tier' => 0.50, 'config' => 'Wireless N router'],
                ['brand' => 'TP-Link', 'model' => 'TL-WR841N', 'from' => 2012, 'to' => 2020, 'tier' => 0.50, 'config' => '300 Mbps wireless N router'],
                ['brand' => 'TP-Link', 'model' => 'TL-WR940N', 'from' => 2014, 'to' => 2019, 'tier' => 0.70, 'config' => '450 Mbps wireless N router'],
                ['brand' => 'MikroTik', 'model' => 'hAP ac lite', 'part' => 'RB952Ui-5ac2nD', 'from' => 2016, 'to' => 2023, 'tier' => 0.90, 'config' => 'Dual-band wireless router'],
                ['brand' => 'MikroTik', 'model' => 'hAP ac2', 'part' => 'RBD52G-5HacD2HnD', 'from' => 2018, 'to' => 2026, 'tier' => 1.10, 'config' => 'Dual-band Gigabit wireless router'],
                ['brand' => 'TP-Link', 'model' => 'Archer C6', 'from' => 2019, 'to' => 2023, 'tier' => 1.00, 'config' => 'AC1200 dual-band wireless router'],
                ['brand' => 'MikroTik', 'model' => 'RB4011', 'part' => 'RB4011iGS+RM', 'from' => 2019, 'to' => 2026, 'tier' => 3.50, 'config' => 'Rack-mount Gigabit edge router'],
                ['brand' => 'TP-Link', 'model' => 'Archer AX23', 'from' => 2022, 'to' => 2026, 'tier' => 1.30, 'config' => 'AX1800 dual-band Wi-Fi 6 router'],
            ],
        ],
        'EHD' => [
            'weight' => 60, 'trend' => 0.0, 'band' => [150, 400], 'warranty' => 'storage', 'movable' => true,
            'units' => ['ICTD', 'SRIM', 'FIN', 'IAU', 'HRD'],
            'models' => [
                ['brand' => 'Western Digital', 'model' => 'My Passport 1TB', 'from' => 2012, 'to' => 2016, 'tier' => 1.00, 'config' => '1 TB portable hard drive, USB 3.0'],
                ['brand' => 'Seagate', 'model' => 'Expansion Portable 1TB', 'from' => 2013, 'to' => 2019, 'tier' => 0.90, 'config' => '1 TB portable hard drive, USB 3.0'],
                ['brand' => 'Toshiba', 'model' => 'Canvio Basics 1TB', 'from' => 2015, 'to' => 2026, 'tier' => 0.90, 'config' => '1 TB portable hard drive, USB 3.0'],
                ['brand' => 'Western Digital', 'model' => 'My Passport Ultra 2TB', 'from' => 2015, 'to' => 2019, 'tier' => 1.30, 'config' => '2 TB portable hard drive, USB 3.0'],
                ['brand' => 'Western Digital', 'model' => 'Elements Portable 2TB', 'from' => 2016, 'to' => 2026, 'tier' => 1.20, 'config' => '2 TB portable hard drive, USB 3.0'],
                ['brand' => 'Western Digital', 'model' => 'My Passport Ultra Metal Edition 2TB', 'from' => 2017, 'to' => 2021, 'tier' => 1.40, 'config' => '2 TB portable hard drive, USB 3.0'],
                ['brand' => 'Seagate', 'model' => 'One Touch 2TB', 'from' => 2021, 'to' => 2026, 'tier' => 1.30, 'config' => '2 TB portable hard drive, USB 3.0'],
                ['brand' => 'Samsung', 'model' => 'Portable SSD T7 1TB', 'from' => 2021, 'to' => 2026, 'tier' => 2.20, 'config' => '1 TB portable SSD, USB 3.2'],
            ],
        ],
        'ODK' => [
            'weight' => 200, 'trend' => 0.0, 'band' => [500, 1600], 'warranty' => 'furniture', 'movable' => true,
            'models' => [
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.80, 'config' => '1200 x 600 mm melamine office desk with drawer pedestal'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.90, 'config' => 'Wooden office desk with three lockable drawers'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.30, 'config' => '1500 x 750 mm executive desk, hardwood veneer'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2015, 'to' => 2026, 'tier' => 1.10, 'config' => '1400 mm L-shaped workstation desk'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.60, 'config' => '1800 mm executive desk with side return'],
            ],
        ],
        'OFC' => [
            'weight' => 260, 'trend' => 0.0, 'band' => [180, 900], 'warranty' => 'furniture', 'movable' => true,
            'models' => [
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.80, 'config' => 'Mesh-back swivel task chair'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.50, 'config' => 'High-back executive chair, leather-look upholstery'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.50, 'config' => 'Visitor chair, fabric seat, steel frame'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2016, 'to' => 2026, 'tier' => 1.20, 'config' => 'Ergonomic mesh chair with lumbar support'],
            ],
        ],
        'FBC' => [
            'weight' => 120, 'trend' => -0.01, 'band' => [400, 1300], 'warranty' => 'furniture', 'movable' => true,
            'units' => ['HRD', 'FIN', 'IAU', 'LAU', 'GAU', 'PSC', 'SICU', 'SGD', 'ECU'],
            'models' => [
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.00, 'config' => '4-drawer steel filing cabinet'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.65, 'config' => '2-drawer steel filing cabinet'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.10, 'config' => 'Two-door steel cupboard with four shelves'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2014, 'to' => 2026, 'tier' => 1.30, 'config' => 'Lateral steel filing cabinet, three drawers'],
            ],
        ],
        'BSH' => [
            'weight' => 60, 'trend' => 0.0, 'band' => [300, 1000], 'warranty' => 'furniture', 'movable' => true,
            'models' => [
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.00, 'config' => '5-tier wooden bookshelf'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.30, 'config' => 'Glass-door wooden bookcase'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.80, 'config' => 'Steel shelving unit, five shelves'],
            ],
        ],
        'CFT' => [
            'weight' => 25, 'trend' => 0.0, 'band' => [1500, 5000], 'warranty' => 'furniture', 'shared' => true,
            'units' => ['ODG', 'DDG-MS', 'DDG-QA', 'GAU', 'PBME', 'TED'],
            'models' => [
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.60, 'config' => '6-seater meeting table'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.90, 'config' => '8-seater boardroom table'],
                ['brand' => 'Local Fabrication', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.30, 'config' => '12-seater conference table, veneer finish'],
            ],
        ],
        'ACR' => [
            'weight' => 80, 'trend' => 0.01, 'band' => [1300, 2600], 'warranty' => 'appliance', 'shared' => true,
            'models' => [
                ['brand' => 'Hisense', 'model' => null, 'from' => 2012, 'to' => 2018, 'tier' => 1.00, 'config' => '1.5 HP wall split air conditioner, non-inverter'],
                ['brand' => 'LG', 'model' => null, 'from' => 2012, 'to' => 2018, 'tier' => 1.30, 'config' => '2.0 HP wall split air conditioner, non-inverter'],
                ['brand' => 'Samsung', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.80, 'config' => '1.0 HP wall split air conditioner'],
                ['brand' => 'Midea', 'model' => null, 'from' => 2019, 'to' => 2026, 'tier' => 1.15, 'config' => '1.5 HP wall split air conditioner, inverter'],
                ['brand' => 'LG', 'model' => null, 'from' => 2019, 'to' => 2026, 'tier' => 1.25, 'config' => '1.5 HP wall split air conditioner, inverter'],
                ['brand' => 'Hisense', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 2.20, 'config' => '2.5 HP floor-standing air conditioner'],
            ],
        ],
        'STF' => [
            'weight' => 70, 'trend' => -0.02, 'band' => [80, 200], 'warranty' => 'appliance', 'movable' => true,
            'models' => [
                ['brand' => 'Binatone', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.00, 'config' => '16-inch standing fan'],
                ['brand' => 'Nasco', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.95, 'config' => '16-inch standing fan'],
                ['brand' => 'Midea', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.20, 'config' => '18-inch standing fan'],
            ],
        ],
        'CLF' => [
            'weight' => 40, 'trend' => 0.0, 'band' => [70, 180], 'warranty' => 'appliance', 'shared' => true,
            'models' => [
                ['brand' => 'Binatone', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.00, 'config' => '56-inch three-blade ceiling fan'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.80, 'config' => '56-inch ceiling fan'],
            ],
        ],
        'WDP' => [
            'weight' => 35, 'trend' => 0.0, 'band' => [200, 600], 'warranty' => 'appliance', 'shared' => true,
            'models' => [
                ['brand' => 'Nasco', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.00, 'config' => 'Hot and cold standing water dispenser'],
                ['brand' => 'Midea', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.05, 'config' => 'Hot and cold standing water dispenser'],
                ['brand' => 'Hisense', 'model' => null, 'from' => 2018, 'to' => 2026, 'tier' => 1.50, 'config' => 'Bottom-load water dispenser with storage compartment'],
            ],
        ],
        'TEL' => [
            'weight' => 60, 'trend' => -0.01, 'band' => [60, 400], 'warranty' => 'ict', 'movable' => true,
            'models' => [
                ['brand' => 'Panasonic', 'model' => 'KX-TS500', 'from' => 2012, 'to' => 2026, 'tier' => 0.40, 'config' => 'Corded analogue desk telephone'],
                ['brand' => 'Panasonic', 'model' => 'KX-TS880', 'from' => 2012, 'to' => 2020, 'tier' => 0.80, 'config' => 'Corded analogue telephone with speakerphone'],
                ['brand' => 'Grandstream', 'model' => 'GXP1625', 'from' => 2015, 'to' => 2023, 'tier' => 1.20, 'config' => 'IP desk phone, 2 SIP lines, PoE'],
                ['brand' => 'Yealink', 'model' => 'SIP-T21P E2', 'from' => 2016, 'to' => 2021, 'tier' => 1.30, 'config' => 'IP desk phone, 2 SIP lines, PoE'],
                ['brand' => 'Yealink', 'model' => 'SIP-T31P', 'from' => 2021, 'to' => 2026, 'tier' => 1.20, 'config' => 'IP desk phone, 2 SIP lines, PoE'],
                ['brand' => 'Grandstream', 'model' => 'GRP2602P', 'from' => 2022, 'to' => 2026, 'tier' => 1.10, 'config' => 'IP desk phone, 2 SIP lines, PoE'],
            ],
        ],
        'TVS' => [
            'weight' => 25, 'trend' => 0.02, 'band' => [900, 3200], 'warranty' => 'appliance', 'shared' => true,
            'units' => ['ODG', 'PRCS', 'GAU', 'DDG-MS', 'DDG-QA', 'TED'],
            'models' => [
                ['brand' => 'Samsung', 'model' => null, 'from' => 2012, 'to' => 2016, 'tier' => 0.60, 'config' => '32-inch LED HD television'],
                ['brand' => 'LG', 'model' => null, 'from' => 2012, 'to' => 2016, 'tier' => 1.00, 'config' => '43-inch LED Full HD television'],
                ['brand' => 'Samsung', 'model' => null, 'from' => 2017, 'to' => 2026, 'tier' => 1.00, 'config' => '43-inch 4K UHD smart television'],
                ['brand' => 'Hisense', 'model' => null, 'from' => 2017, 'to' => 2026, 'tier' => 1.40, 'config' => '55-inch 4K UHD smart television'],
                ['brand' => 'TCL', 'model' => null, 'from' => 2019, 'to' => 2026, 'tier' => 2.30, 'config' => '65-inch 4K UHD smart television'],
            ],
        ],
        'SRV' => [
            'weight' => 12, 'trend' => 0.0, 'band' => [9000, 28000], 'warranty' => 'infrastructure', 'shared' => true,
            'units' => ['ICTD', 'SRIM'],
            'models' => [
                ['brand' => 'Dell', 'model' => 'PowerEdge R720', 'from' => 2012, 'to' => 2014, 'tier' => 1.00, 'config' => '2U rack server, dual Intel Xeon E5-2600 series, RAID controller'],
                ['brand' => 'HP', 'model' => 'ProLiant DL380 Gen8', 'from' => 2012, 'to' => 2014, 'tier' => 1.05, 'config' => '2U rack server, dual Intel Xeon E5-2600 series'],
                ['brand' => 'Dell', 'model' => 'PowerEdge R730', 'from' => 2015, 'to' => 2017, 'tier' => 1.00, 'config' => '2U rack server, dual Intel Xeon E5-2600 v3/v4 series'],
                ['brand' => 'HPE', 'model' => 'ProLiant DL380 Gen9', 'from' => 2016, 'to' => 2017, 'tier' => 1.05, 'config' => '2U rack server, dual Intel Xeon E5-2600 v3/v4 series'],
                ['brand' => 'Dell', 'model' => 'PowerEdge R740', 'from' => 2018, 'to' => 2021, 'tier' => 1.05, 'config' => '2U rack server, Intel Xeon Scalable'],
                ['brand' => 'HPE', 'model' => 'ProLiant DL380 Gen10', 'from' => 2018, 'to' => 2022, 'tier' => 1.10, 'config' => '2U rack server, Intel Xeon Scalable'],
                ['brand' => 'Lenovo', 'model' => 'ThinkSystem SR650', 'from' => 2018, 'to' => 2022, 'tier' => 1.00, 'config' => '2U rack server, Intel Xeon Scalable'],
                ['brand' => 'Dell', 'model' => 'PowerEdge T340', 'from' => 2019, 'to' => 2022, 'tier' => 0.45, 'config' => 'Tower server, Intel Xeon E-2100 series'],
                ['brand' => 'Dell', 'model' => 'PowerEdge R750', 'from' => 2021, 'to' => 2023, 'tier' => 1.10, 'config' => '2U rack server, 3rd gen Intel Xeon Scalable'],
                ['brand' => 'Dell', 'model' => 'PowerEdge T150', 'from' => 2022, 'to' => 2026, 'tier' => 0.40, 'config' => 'Tower server, Intel Xeon E-2300 series'],
                ['brand' => 'Dell', 'model' => 'PowerEdge R760', 'from' => 2023, 'to' => 2026, 'tier' => 1.15, 'config' => '2U rack server, 4th gen Intel Xeon Scalable'],
                ['brand' => 'HPE', 'model' => 'ProLiant DL380 Gen11', 'from' => 2023, 'to' => 2026, 'tier' => 1.20, 'config' => '2U rack server, 4th gen Intel Xeon Scalable'],
            ],
        ],
        'NRC' => [
            'weight' => 10, 'trend' => 0.0, 'band' => [800, 2500], 'warranty' => 'furniture', 'shared' => true,
            'units' => ['ICTD', 'SRIM'],
            'models' => [
                ['brand' => 'APC', 'model' => 'NetShelter SX 42U', 'part' => 'AR3100', 'from' => 2012, 'to' => 2026, 'tier' => 2.00, 'config' => '42U floor-standing server rack enclosure'],
                ['brand' => 'Tripp Lite', 'model' => 'SmartRack 42U', 'part' => 'SR42UB', 'from' => 2012, 'to' => 2026, 'tier' => 1.90, 'config' => '42U floor-standing rack enclosure'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.30, 'config' => '9U wall-mount network cabinet'],
                ['brand' => 'Unbranded (imported)', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.80, 'config' => '22U floor-standing network cabinet'],
            ],
        ],
        'BAD' => [
            'weight' => 10, 'trend' => 0.05, 'band' => [500, 1600], 'warranty' => 'ict', 'shared' => true,
            'units' => ['HRD', 'GAU', 'ODG'],
            'models' => [
                ['brand' => 'ZKTeco', 'model' => 'F18', 'from' => 2012, 'to' => 2017, 'tier' => 0.80, 'config' => 'Fingerprint access control and time attendance terminal'],
                ['brand' => 'ZKTeco', 'model' => 'uFace 800', 'from' => 2014, 'to' => 2019, 'tier' => 1.10, 'config' => 'Face and fingerprint time attendance terminal'],
                ['brand' => 'ZKTeco', 'model' => 'K40', 'from' => 2015, 'to' => 2020, 'tier' => 0.60, 'config' => 'Fingerprint time attendance terminal'],
                ['brand' => 'ZKTeco', 'model' => 'SpeedFace-V5L', 'from' => 2021, 'to' => 2026, 'tier' => 1.50, 'config' => 'Visible-light face and palm recognition terminal'],
            ],
        ],
        'PAS' => [
            'weight' => 12, 'trend' => 0.0, 'band' => [1500, 6000], 'warranty' => 'audio', 'shared' => true,
            'units' => ['PRCS', 'GAU', 'ECU', 'TED', 'ODG'],
            'models' => [
                ['brand' => 'Ahuja', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.00, 'config' => 'Mixer-amplifier with two column speakers and wired microphones'],
                ['brand' => 'TOA', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.40, 'config' => 'Conference-room PA: 120 W amplifier with ceiling speakers'],
                ['brand' => 'Yamaha', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.60, 'config' => 'Portable PA system with wireless microphones'],
            ],
        ],
        'GEN' => [
            'weight' => 6, 'trend' => 0.0, 'band' => [9000, 45000], 'warranty' => 'generator', 'shared' => true,
            'units' => ['IMU', 'GAU', 'ICTD'],
            'models' => [
                ['brand' => 'Perkins', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.60, 'config' => '20 kVA diesel standby generator'],
                ['brand' => 'FG Wilson', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 1.40, 'config' => '60 kVA diesel standby generator, soundproof canopy'],
                ['brand' => 'Cummins', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 2.20, 'config' => '100 kVA diesel standby generator with automatic transfer switch'],
                ['brand' => 'Perkins', 'model' => null, 'from' => 2012, 'to' => 2026, 'tier' => 0.35, 'config' => '10 kVA diesel generator'],
            ],
        ],
    ],

    /*
    | Lifecycle: chance an asset older than its useful life is now obsolete or
    | disposed. Younger assets keep a small chance (damage, theft write-off).
    */
    'lifecycle' => [
        'obsolete_types' => ['DPC', 'LTP', 'LJP', 'SCN', 'PRJ', 'UPS', 'NSW', 'WRT', 'EHD', 'TEL', 'SRV', 'BAD', 'TVS', 'PCM'],
        'p_obsolete_past_life' => 0.13,
        'p_disposed_past_life' => 0.12,
        'p_disposed_per_extra_year' => 0.02,
        'p_disposed_max' => 0.35,
        'p_early_disposal' => 0.004,
    ],

    // Maintenance job targets (Phase 7).
    'maintenance' => [
        'completed_paid' => 120,
        'completed_unpaid' => 48,
        'completed_payment_processing' => 12,  // payment_status = pending: still outstanding
        'in_progress' => 100,
        'pending_approval' => 45,
        'approved_awaiting_schedule' => 25,
        'multi_job_assets' => 40,
    ],

    // Transfer targets (Phase 9). "Approved" = 80 completed + 20 approved awaiting hand-over.
    'transfers' => [
        'completed' => 80,
        'approved' => 20,
        'pending' => 60,
        'rejected' => 20,
    ],

    // Fault catalogue per asset type: issue, resolution, 2012 GHS cost band, priority.
    'issues' => [
        'LJP' => [
            ['Paper-feed fault: printer picks several sheets and jams at the tray', 'Replaced pickup roller and separation pad; test pages printed cleanly.', [60, 180], 'normal'],
            ['Fuser error displayed; pages come out smudged', 'Replaced fuser assembly and reset maintenance counter.', [200, 600], 'high'],
            ['Faint, streaky print output', 'Replaced imaging drum and cleaned transfer roller.', [80, 250], 'low'],
        ],
        'PCM' => [
            ['Scheduled photocopier servicing (meter-based)', 'Cleaned optics, replaced developer and drum per service schedule.', [300, 900], 'normal'],
            ['Repeated paper jams in the duplex unit', 'Replaced duplex feed rollers and realigned paper path.', [200, 500], 'high'],
            ['Fuser fault code; copier stops mid-job', 'Replaced fuser unit and thermistor.', [600, 1500], 'urgent'],
        ],
        'LTP' => [
            ['Battery no longer holds charge (under 20 minutes)', 'Replaced battery pack and calibrated charging.', [150, 400], 'normal'],
            ['Cracked display panel after a fall', 'Replaced LCD panel and bezel.', [300, 700], 'high'],
            ['Several keyboard keys unresponsive', 'Replaced keyboard assembly.', [80, 200], 'low'],
            ['Laptop overheats and shuts down', 'Cleaned cooling fan, replaced thermal paste.', [60, 150], 'normal'],
        ],
        'DPC' => [
            ['Desktop does not power on', 'Replaced power supply unit; system boots normally.', [120, 300], 'high'],
            ['Random blue-screen crashes', 'Diagnosed faulty RAM; replaced memory module.', [80, 200], 'normal'],
            ['Very slow performance and pop-ups (suspected malware)', 'Removed malware, reinstalled operating system, applied updates.', [50, 120], 'low'],
            ['Monitor flickers and loses signal', 'Replaced monitor backlight board and video cable.', [60, 180], 'normal'],
        ],
        'ACR' => [
            ['Routine air-conditioner servicing: weak cooling, dirty filters', 'General service: cleaned filters and coils, checked gas pressure.', [80, 200], 'low'],
            ['Unit runs but does not cool (suspected gas leak)', 'Located and sealed leak, recharged refrigerant.', [250, 600], 'high'],
            ['Compressor fails to start', 'Replaced start capacitor and contactor.', [120, 300], 'high'],
            ['Water dripping from indoor unit', 'Cleared blocked condensate drain line.', [60, 150], 'normal'],
        ],
        'PRJ' => [
            ['Lamp warning and dim image', 'Replaced projector lamp and reset lamp timer.', [300, 800], 'normal'],
            ['Projector shuts down from overheating', 'Cleaned air filter and fan; replaced filter.', [60, 150], 'normal'],
            ['Colour blotches on projected image', 'Cleaned LCD panels and optics.', [100, 250], 'low'],
        ],
        'UPS' => [
            ['UPS beeps continuously and fails battery self-test', 'Replaced sealed lead-acid batteries; self-test passed.', [150, 450], 'high'],
            ['No output under load during power cuts', 'Replaced inverter board.', [250, 600], 'urgent'],
        ],
        'NSW' => [
            ['Several switch ports drop link intermittently', 'Updated firmware, isolated faulty ports and re-patched users.', [80, 250], 'high'],
            ['Switch dead after power surge', 'Replaced internal power supply.', [250, 800], 'urgent'],
            ['Network loop causing broadcast storms', 'Reconfigured spanning tree and corrected patching.', [100, 300], 'urgent'],
        ],
        'WRT' => [
            ['Intermittent Wi-Fi drop-outs', 'Reset and reconfigured router; firmware updated.', [50, 150], 'normal'],
            ['Router does not power on', 'Replaced power adapter.', [30, 90], 'high'],
        ],
        'SCN' => [
            ['Document feeder misfeeds and double-feeds', 'Replaced feed roller kit.', [80, 250], 'normal'],
        ],
        'SRV' => [
            ['Disk failure alert in RAID array', 'Hot-swapped failed drive and rebuilt array.', [800, 2500], 'urgent'],
            ['Redundant power supply failure alarm', 'Replaced power supply module.', [700, 2000], 'high'],
        ],
        'TVS' => [
            ['Sound present but no picture', 'Replaced LED backlight strips.', [200, 600], 'normal'],
        ],
        'GEN' => [
            ['Routine generator servicing (250 running hours)', 'Changed oil and oil/fuel/air filters; load-tested.', [800, 2000], 'normal'],
            ['Generator fails to start on mains outage', 'Replaced starter battery and serviced automatic transfer switch.', [500, 1500], 'urgent'],
        ],
        'PAS' => [
            ['Crackling speaker output', 'Replaced faulty speaker cable and amplifier channel fuse.', [100, 300], 'normal'],
        ],
        'BAD' => [
            ['Fingerprint reader fails to recognise staff', 'Cleaned sensor, re-enrolled templates and updated firmware.', [60, 180], 'normal'],
        ],
        'TEL' => [
            ['No dial tone on handset', 'Replaced handset cord and line cord.', [30, 90], 'low'],
        ],
        'OFC' => [
            ['Gas-lift cylinder failed; chair sinks', 'Replaced gas-lift cylinder.', [60, 150], 'low'],
            ['Broken castor wheels', 'Replaced castor set.', [30, 80], 'low'],
        ],
        'ODK' => [
            ['Drawer runners broken', 'Replaced drawer runners and lock.', [50, 150], 'low'],
        ],
        'FBC' => [
            ['Drawer lock jammed', 'Replaced lock barrel and keys.', [40, 120], 'low'],
        ],
        'WDP' => [
            ['Dispenser no longer cools water', 'Replaced thermostat.', [60, 180], 'normal'],
        ],
        'STF' => [
            ['Fan no longer oscillates', 'Replaced oscillation gearbox.', [30, 80], 'low'],
        ],
        'CLF' => [
            ['Ceiling fan noisy and wobbling', 'Replaced capacitor and bearing; balanced blades.', [40, 120], 'low'],
        ],
    ],

    // Relative chance that an asset of this type has a maintenance history.
    'maintenance_type_weights' => [
        'LJP' => 3.0, 'PCM' => 3.5, 'ACR' => 3.0, 'LTP' => 2.2, 'DPC' => 2.0, 'UPS' => 2.4, 'PRJ' => 2.0,
        'NSW' => 1.8, 'SRV' => 2.5, 'GEN' => 3.0, 'WRT' => 1.2, 'SCN' => 1.2, 'TVS' => 0.8, 'PAS' => 1.2,
        'BAD' => 1.0, 'TEL' => 0.4, 'OFC' => 0.5, 'ODK' => 0.2, 'FBC' => 0.2, 'WDP' => 0.8, 'STF' => 0.4, 'CLF' => 0.5,
    ],

    'transfer_reasons' => [
        'Officer reassigned to the destination directorate',
        'Redistribution of equipment after office relocation',
        'Replacement for a faulty unit in the destination office',
        'New staff member onboarding at the destination office',
        'Office restructuring and consolidation of shared equipment',
        'Temporary pooling of equipment for examination coordination',
        'Surplus equipment released to an under-equipped office',
    ],

    'rejection_reasons' => [
        'Destination office already exceeds its equipment allocation.',
        'Asset is due for servicing; transfer deferred until maintenance is complete.',
        'Request lacks endorsement from the head of the releasing unit.',
        'Proposed custodian has not yet been posted to the destination unit.',
        'Asset is earmarked for the annual audit verification exercise.',
    ],

    'approval_remarks' => [
        'Approved. Hand over with accessories and update the custody register.',
        'Approved following confirmation from both unit heads.',
        'Approved; stores to witness hand-over.',
        'Approved. Ensure asset tag remains visible after relocation.',
        'Approved in line with the redistribution plan.',
    ],

    // Synthetic people. Names carry a "(Demo)" suffix in the app.
    'first_names' => [
        'Kwame', 'Kofi', 'Kwabena', 'Yaw', 'Kojo', 'Kwaku', 'Kwesi', 'Fiifi', 'Ekow', 'Nana', 'Selorm', 'Elikem', 'Mawuli', 'Delali',
        'Ama', 'Akosua', 'Abena', 'Adwoa', 'Afua', 'Yaa', 'Efua', 'Esi', 'Akua', 'Adjoa', 'Dzifa', 'Enyonam', 'Naa', 'Aseye',
        'Ibrahim', 'Abdul', 'Fuseini', 'Mariam', 'Zainab', 'Fatima', 'Sena', 'Edem', 'Prince', 'Gifty', 'Comfort', 'Emmanuel',
    ],
    'last_names' => [
        'Mensah', 'Asante', 'Owusu', 'Boateng', 'Osei', 'Agyeman', 'Appiah', 'Darko', 'Ofori', 'Amoah', 'Tetteh', 'Quaye', 'Lartey',
        'Addo', 'Ankrah', 'Bonsu', 'Frimpong', 'Gyamfi', 'Kusi', 'Nkansah', 'Opoku', 'Sarpong', 'Yeboah', 'Adjei', 'Antwi', 'Baah',
        'Dogbe', 'Agbeko', 'Kpodo', 'Amenyo', 'Issah', 'Alhassan', 'Mahama', 'Abubakar', 'Arthur', 'Quansah', 'Eshun', 'Mensah-Bonsu',
    ],

    // Users who request/approve on behalf of units. They get the existing
    // "Admin" role, which carries the transfer/maintenance permissions.
    'staff' => [
        ['key' => 'requester-1', 'title' => 'Logistics Officer', 'unit' => 'SICU', 'role' => 'Admin'],
        ['key' => 'requester-2', 'title' => 'Estates and Assets Officer', 'unit' => 'IMU', 'role' => 'Admin'],
        ['key' => 'approver-1', 'title' => 'Head of Stores and Inventory', 'unit' => 'SICU', 'role' => 'Admin'],
        ['key' => 'approver-2', 'title' => 'Director, General Administration', 'unit' => 'GAU', 'role' => 'Admin'],
    ],

    'custodians_per_unit' => [2, 4],
];
