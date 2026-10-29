<?php

return array (
  'Desktop Computer' => 
  array (
    'query' => 'desktop computer tower monitor keyboard office',
    'required_any' => 
    array (
      0 => 'desktop',
      1 => 'computer',
      2 => 'pc',
    ),
    'prefer' => 
    array (
      0 => 'computer',
      1 => 'desktop',
      2 => 'monitor',
      3 => 'tower',
    ),
    'reject' => 
    array (
      0 => 'diagram',
      1 => 'icon',
      2 => 'logo',
      3 => 'illustration',
      4 => 'drawing',
      5 => 'svg',
      6 => 'all-in-one',
    ),
  ),
  'Laptop Computer' => 
  array (
    'query' => 'laptop computer notebook',
    'required_any' => 
    array (
      0 => 'laptop',
      1 => 'notebook',
      2 => 'computer',
    ),
    'prefer' => 
    array (
      0 => 'laptop',
      1 => 'notebook',
    ),
    'reject' => 
    array (
      0 => 'bag',
      1 => 'case',
      2 => 'logo',
      3 => 'diagram',
      4 => 'icon',
      5 => 'svg',
    ),
  ),
  'LaserJet Printer' => 
  array (
    'query' => 'laser printer HP LaserJet office printer',
    'required_any' => 
    array (
      0 => 'printer',
      1 => 'laserjet',
      2 => 'laser',
    ),
    'prefer' => 
    array (
      0 => 'laserjet',
      1 => 'laser',
      2 => 'printer',
    ),
    'reject' => 
    array (
      0 => '3d printer',
      1 => 'label printer',
      2 => 'receipt',
      3 => 'diagram',
      4 => 'icon',
      5 => 'svg',
    ),
  ),
  'Photocopier Machine' => 
  array (
    'query' => 'office photocopier copy machine',
    'required_any' => 
    array (
      0 => 'photocopier',
      1 => 'copier',
      2 => 'copy machine',
    ),
    'prefer' => 
    array (
      0 => 'photocopier',
      1 => 'copier',
      2 => 'office',
    ),
    'reject' => 
    array (
      0 => 'scanner only',
      1 => 'printer only',
      2 => 'diagram',
      3 => 'icon',
      4 => 'svg',
    ),
  ),
  'Scanner' => 
  array (
    'query' => 'flatbed document scanner office',
    'required_any' => 
    array (
      0 => 'scanner',
      1 => 'flatbed',
    ),
    'prefer' => 
    array (
      0 => 'flatbed',
      1 => 'scanner',
      2 => 'document',
    ),
    'reject' => 
    array (
      0 => 'fingerprint',
      1 => 'barcode',
      2 => 'medical',
      3 => 'ct scanner',
      4 => 'radio',
      5 => 'film scanner',
      6 => 'icon',
      7 => 'diagram',
      8 => 'svg',
    ),
  ),
  'Projector' => 
  array (
    'query' => 'digital LCD projector office Epson Panasonic',
    'required_any' => 
    array (
      0 => 'projector',
    ),
    'prefer' => 
    array (
      0 => 'lcd',
      1 => 'digital',
      2 => 'projector',
      3 => 'epson',
      4 => 'panasonic',
    ),
    'reject' => 
    array (
      0 => 'film',
      1 => 'cinema',
      2 => 'movie',
      3 => 'slide projector',
      4 => 'overhead',
      5 => 'magic lantern',
      6 => 'lamp',
      7 => 'icon',
      8 => 'diagram',
      9 => 'svg',
    ),
  ),
  'UPS Battery Backup' => 
  array (
    'query' => 'UPS uninterruptible power supply battery backup',
    'required_any' => 
    array (
      0 => 'ups',
      1 => 'uninterruptible',
      2 => 'power supply',
    ),
    'prefer' => 
    array (
      0 => 'ups',
      1 => 'uninterruptible',
      2 => 'battery backup',
    ),
    'reject' => 
    array (
      0 => 'truck',
      1 => 'vehicle',
      2 => 'icon',
      3 => 'diagram',
      4 => 'svg',
    ),
  ),
  'Network Switch' => 
  array (
    'query' => 'ethernet network switch 24 port',
    'required_any' => 
    array (
      0 => 'switch',
      1 => 'ethernet',
    ),
    'prefer' => 
    array (
      0 => 'ethernet',
      1 => 'network switch',
      2 => 'port',
    ),
    'reject' => 
    array (
      0 => 'light switch',
      1 => 'railway',
      2 => 'keyboard switch',
      3 => 'icon',
      4 => 'diagram',
      5 => 'svg',
    ),
  ),
  'Wireless Router' => 
  array (
    'query' => 'wireless WiFi router antennas',
    'required_any' => 
    array (
      0 => 'router',
      1 => 'wifi',
      2 => 'wireless',
    ),
    'prefer' => 
    array (
      0 => 'wireless',
      1 => 'router',
      2 => 'wifi',
      3 => 'antenna',
    ),
    'reject' => 
    array (
      0 => 'router tool',
      1 => 'wood router',
      2 => 'cnc',
      3 => 'network diagram',
      4 => 'icon',
      5 => 'svg',
    ),
  ),
  'External Hard Drive' => 
  array (
    'query' => 'external USB hard drive portable storage',
    'required_any' => 
    array (
      0 => 'hard drive',
      1 => 'hard disk',
      2 => 'hdd',
      3 => 'external drive',
    ),
    'prefer' => 
    array (
      0 => 'usb',
      1 => 'external',
      2 => 'portable',
      3 => 'hard drive',
    ),
    'reject' => 
    array (
      0 => 'internal',
      1 => 'opened',
      2 => 'platter',
      3 => 'diagram',
      4 => 'icon',
      5 => 'svg',
    ),
  ),
  'Office Desk' => 
  array (
    'query' => 'office desk furniture workstation',
    'required_any' => 
    array (
      0 => 'desk',
      1 => 'office',
    ),
    'prefer' => 
    array (
      0 => 'office desk',
      1 => 'desk',
      2 => 'workstation',
    ),
    'reject' => 
    array (
      0 => 'school desk',
      1 => 'antique',
      2 => 'diagram',
      3 => 'icon',
      4 => 'svg',
    ),
  ),
  'Office Chair' => 
  array (
    'query' => 'office chair swivel desk chair',
    'required_any' => 
    array (
      0 => 'chair',
      1 => 'office chair',
    ),
    'prefer' => 
    array (
      0 => 'office',
      1 => 'swivel',
      2 => 'desk chair',
    ),
    'reject' => 
    array (
      0 => 'wheelchair',
      1 => 'dining',
      2 => 'antique',
      3 => 'icon',
      4 => 'diagram',
      5 => 'svg',
    ),
  ),
  'Filing Cabinet' => 
  array (
    'query' => 'office filing cabinet drawers',
    'required_any' => 
    array (
      0 => 'filing cabinet',
      1 => 'file cabinet',
      2 => 'cabinet',
    ),
    'prefer' => 
    array (
      0 => 'filing',
      1 => 'file cabinet',
      2 => 'drawer',
    ),
    'reject' => 
    array (
      0 => 'kitchen',
      1 => 'server rack',
      2 => 'icon',
      3 => 'diagram',
      4 => 'svg',
    ),
  ),
  'Bookshelf' => 
  array (
    'query' => 'office bookshelf bookcase shelves',
    'required_any' => 
    array (
      0 => 'bookshelf',
      1 => 'bookcase',
      2 => 'shelf',
    ),
    'prefer' => 
    array (
      0 => 'bookshelf',
      1 => 'bookcase',
      2 => 'books',
    ),
    'reject' => 
    array (
      0 => 'library building',
      1 => 'icon',
      2 => 'diagram',
      3 => 'svg',
    ),
  ),
  'Conference Table' => 
  array (
    'query' => 'conference room meeting table office',
    'required_any' => 
    array (
      0 => 'conference',
      1 => 'meeting table',
      2 => 'table',
    ),
    'prefer' => 
    array (
      0 => 'conference',
      1 => 'meeting',
      2 => 'office',
      3 => 'table',
    ),
    'reject' => 
    array (
      0 => 'dining',
      1 => 'picnic',
      2 => 'periodic table',
      3 => 'icon',
      4 => 'diagram',
      5 => 'svg',
    ),
  ),
  'Air Conditioner' => 
  array (
    'query' => 'split air conditioner indoor unit office',
    'required_any' => 
    array (
      0 => 'air conditioner',
      1 => 'air conditioning',
      2 => 'ac unit',
    ),
    'prefer' => 
    array (
      0 => 'split',
      1 => 'air conditioner',
      2 => 'indoor',
    ),
    'reject' => 
    array (
      0 => 'car',
      1 => 'automotive',
      2 => 'diagram',
      3 => 'icon',
      4 => 'svg',
    ),
  ),
  'Standing Fan' => 
  array (
    'query' => 'standing fan pedestal fan electric',
    'required_any' => 
    array (
      0 => 'standing fan',
      1 => 'pedestal fan',
      2 => 'fan',
    ),
    'prefer' => 
    array (
      0 => 'standing',
      1 => 'pedestal',
      2 => 'electric fan',
    ),
    'reject' => 
    array (
      0 => 'ceiling fan',
      1 => 'hand fan',
      2 => 'computer fan',
      3 => 'industrial turbine',
      4 => 'icon',
      5 => 'diagram',
      6 => 'svg',
    ),
  ),
  'Water Dispenser' => 
  array (
    'query' => 'office water dispenser bottled water cooler',
    'required_any' => 
    array (
      0 => 'water dispenser',
      1 => 'water cooler',
      2 => 'dispenser',
    ),
    'prefer' => 
    array (
      0 => 'water',
      1 => 'dispenser',
      2 => 'cooler',
      3 => 'bottle',
    ),
    'reject' => 
    array (
      0 => 'soap dispenser',
      1 => 'vending',
      2 => 'fuel',
      3 => 'icon',
      4 => 'diagram',
      5 => 'svg',
    ),
  ),
  'Telephone Set' => 
  array (
    'query' => 'office desk telephone landline phone',
    'required_any' => 
    array (
      0 => 'telephone',
      1 => 'desk phone',
      2 => 'landline',
    ),
    'prefer' => 
    array (
      0 => 'desk',
      1 => 'office',
      2 => 'telephone',
      3 => 'landline',
    ),
    'reject' => 
    array (
      0 => 'mobile phone',
      1 => 'smartphone',
      2 => 'payphone',
      3 => 'public phone',
      4 => 'icon',
      5 => 'diagram',
      6 => 'svg',
    ),
  ),
  'Television Set' => 
  array (
    'query' => 'flat screen television TV set',
    'required_any' => 
    array (
      0 => 'television',
      1 => 'tv',
    ),
    'prefer' => 
    array (
      0 => 'flat screen',
      1 => 'television',
      2 => 'tv',
    ),
    'reject' => 
    array (
      0 => 'broadcast tower',
      1 => 'camera',
      2 => 'test pattern',
      3 => 'icon',
      4 => 'diagram',
      5 => 'svg',
    ),
  ),
  'Server Machine' => 
  array (
    'query' => 'rack server computer enterprise server',
    'required_any' => 
    array (
      0 => 'server',
    ),
    'prefer' => 
    array (
      0 => 'rack server',
      1 => 'server computer',
      2 => 'enterprise',
    ),
    'reject' => 
    array (
      0 => 'waiter',
      1 => 'restaurant server',
      2 => 'web server diagram',
      3 => 'icon',
      4 => 'svg',
    ),
  ),
  'Network Rack Cabinet' => 
  array (
    'query' => 'network rack cabinet server rack 19 inch',
    'required_any' => 
    array (
      0 => 'rack',
      1 => 'cabinet',
    ),
    'prefer' => 
    array (
      0 => 'network rack',
      1 => 'server rack',
      2 => 'cabinet',
      3 => '19 inch',
    ),
    'reject' => 
    array (
      0 => 'kitchen cabinet',
      1 => 'filing cabinet',
      2 => 'icon',
      3 => 'diagram',
      4 => 'svg',
    ),
  ),
  'Biometric Attendance Device' => 
  array (
    'query' => 'fingerprint biometric attendance terminal device',
    'required_any' => 
    array (
      0 => 'fingerprint',
      1 => 'biometric',
      2 => 'attendance',
    ),
    'prefer' => 
    array (
      0 => 'fingerprint',
      1 => 'biometric',
      2 => 'attendance',
      3 => 'terminal',
    ),
    'reject' => 
    array (
      0 => 'phone fingerprint',
      1 => 'finger anatomy',
      2 => 'medical',
      3 => 'icon',
      4 => 'diagram',
      5 => 'svg',
    ),
  ),
  'Public Address System' => 
  array (
    'query' => 'public address system PA loudspeaker amplifier microphone',
    'required_any' => 
    array (
      0 => 'public address',
      1 => 'loudspeaker',
      2 => 'pa system',
      3 => 'speaker',
    ),
    'prefer' => 
    array (
      0 => 'public address',
      1 => 'speaker',
      2 => 'amplifier',
      3 => 'microphone',
    ),
    'reject' => 
    array (
      0 => 'home cinema',
      1 => 'headphones',
      2 => 'earbuds',
      3 => 'icon',
      4 => 'diagram',
      5 => 'svg',
    ),
  ),
  'Generator Set' => 
  array (
    'query' => 'stationary diesel generator set genset',
    'required_any' => 
    array (
      0 => 'generator',
      1 => 'genset',
    ),
    'prefer' => 
    array (
      0 => 'diesel',
      1 => 'generator set',
      2 => 'genset',
      3 => 'stationary',
    ),
    'reject' => 
    array (
      0 => 'truck',
      1 => 'vehicle',
      2 => 'locomotive',
      3 => 'wind generator',
      4 => 'bicycle',
      5 => 'aircraft',
      6 => 'icon',
      7 => 'diagram',
      8 => 'svg',
    ),
  ),
  'Ceiling Fan' => 
  array (
    'query' => 'ceiling fan electric office ceiling mounted',
    'required_any' => 
    array (
      0 => 'ceiling fan',
      1 => 'electric fan',
    ),
    'prefer' => 
    array (
      0 => 'ceiling',
      1 => 'mounted',
      2 => 'fan',
    ),
    'reject' => 
    array (
      0 => 'standing fan',
      1 => 'pedestal',
      2 => 'desk fan',
      3 => 'computer fan',
      4 => 'icon',
      5 => 'diagram',
      6 => 'svg',
    ),
  ),
);
