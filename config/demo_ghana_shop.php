<?php

/*
|--------------------------------------------------------------------------
| Ghana shop catalogue (SYNTHETIC DEMONSTRATION DATA)
|--------------------------------------------------------------------------
|
| A small demo set of assets modelled on products listed by large Ghanaian
| retailers, priced in Ghana cedis as listed on the shop's website on the
| retrieval date. They give AI research realistic models that are sold in
| Ghana, so prices can be checked in GHS.
|
| These are NOT real GES purchases: the shop listing is only the reference
| for the model and price. Every row created is recorded in
| demo_dataset_records (dataset below), so it can be removed in one step:
|
|   php artisan demo:ghana-shop-assets                      (dry run)
|   php artisan demo:ghana-shop-assets --execute --confirm=GHANA-SHOP-2026
|   php artisan demo:ghana-shop-assets --remove  --confirm=GHANA-SHOP-2026
|
*/

return [

    'dataset' => 'ghana-shop-2026',

    'retrieved_at' => '2026-11-05',

    // Purchase dates are spread across this window (before the retrieval date).
    'purchase_from' => '2026-01-05',
    'purchase_to' => '2026-10-31',

    'warranty_months' => 12,

    /*
    | type: asset type abbreviation (asset_names.assetname_abbreviation)
    | price_ghs: price listed by the shop
    | quantity: number of demo assets to create for this item
    */
    'items' => [
        // ---- Laptops -------------------------------------------------------
        ['type' => 'LTP', 'brand' => 'HP', 'model' => '250 G10 (Core 3, 8GB RAM, 512GB SSD)', 'part' => null, 'price_ghs' => 6999, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/laptops', 'quantity' => 4],
        ['type' => 'LTP', 'brand' => 'HP', 'model' => '240 G10 (Core 3, 8GB RAM, 512GB SSD)', 'part' => null, 'price_ghs' => 6999, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/laptops', 'quantity' => 3],
        ['type' => 'LTP', 'brand' => 'HP', 'model' => '250R G10 (Core 5)', 'part' => null, 'price_ghs' => 9499, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/laptops', 'quantity' => 3],
        ['type' => 'LTP', 'brand' => 'Lenovo', 'model' => 'ThinkBook 16', 'part' => null, 'price_ghs' => 10499, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/laptops', 'quantity' => 2],
        ['type' => 'LTP', 'brand' => 'Lenovo', 'model' => 'IdeaPad Slim 3 (Core i5, 8GB RAM, 512GB SSD)', 'part' => null, 'price_ghs' => 8500, 'seller' => 'Franko Trading', 'url' => 'https://www.frankotrading.com/computers', 'quantity' => 3],
        ['type' => 'LTP', 'brand' => 'HP', 'model' => 'OmniBook Ultra 7 (8GB RAM, 512GB SSD)', 'part' => null, 'price_ghs' => 13000, 'seller' => 'Franko Trading', 'url' => 'https://www.frankotrading.com/computers', 'quantity' => 1],

        // ---- Laser printers ------------------------------------------------
        ['type' => 'LJP', 'brand' => 'HP', 'model' => 'LaserJet 108a', 'part' => null, 'price_ghs' => 1599, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/printers', 'quantity' => 4],
        ['type' => 'LJP', 'brand' => 'HP', 'model' => 'LaserJet M111a', 'part' => null, 'price_ghs' => 1690, 'seller' => 'Starlite', 'url' => 'https://starlite.com.gh/collections/hp-printers', 'quantity' => 3],
        ['type' => 'LJP', 'brand' => 'HP', 'model' => 'LaserJet MFP 139fnw', 'part' => null, 'price_ghs' => 3900, 'seller' => 'Starlite', 'url' => 'https://starlite.com.gh/collections/hp-printers', 'quantity' => 2],
        ['type' => 'LJP', 'brand' => 'HP', 'model' => 'Color LaserJet Pro MFP 3303sdw', 'part' => null, 'price_ghs' => 7900, 'seller' => 'Starlite', 'url' => 'https://starlite.com.gh/collections/hp-printers', 'quantity' => 2],
        ['type' => 'LJP', 'brand' => 'HP', 'model' => 'LaserJet Enterprise MFP M528f', 'part' => null, 'price_ghs' => 28999, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/printers', 'quantity' => 1],

        // ---- UPS -----------------------------------------------------------
        ['type' => 'UPS', 'brand' => 'Blutek', 'model' => '1000VA UPS', 'part' => null, 'price_ghs' => 519, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/ups', 'quantity' => 4],
        ['type' => 'UPS', 'brand' => 'Blutek', 'model' => '1500VA UPS', 'part' => null, 'price_ghs' => 1099, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/it-networking', 'quantity' => 2],
        ['type' => 'UPS', 'brand' => 'APC', 'model' => 'Back-UPS 800VA', 'part' => 'BV800I-MSX', 'price_ghs' => 1499, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/ups', 'quantity' => 3],

        // ---- Projectors ----------------------------------------------------
        ['type' => 'PRJ', 'brand' => 'HP', 'model' => 'CC360', 'part' => null, 'price_ghs' => 4399, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/projectors', 'quantity' => 2],
        ['type' => 'PRJ', 'brand' => 'BenQ', 'model' => 'MX560', 'part' => null, 'price_ghs' => 4849, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/projectors', 'quantity' => 2],
        ['type' => 'PRJ', 'brand' => 'Epson', 'model' => 'EB-E12', 'part' => null, 'price_ghs' => 7599, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/projectors', 'quantity' => 2],
        ['type' => 'PRJ', 'brand' => 'Epson', 'model' => 'EB-W53', 'part' => null, 'price_ghs' => 9499, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/projectors', 'quantity' => 1],
    ],

];
