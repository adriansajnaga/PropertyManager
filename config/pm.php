<?php

return [

    'vat_rate' => (float) env('PM_VAT_RATE', 0.23),

    'boiler_meter_name' => env('PM_BOILER_METER_NAME', 'KOTŁOWNIA'),

    'sync' => [
        'connection' => env('PM_SYNC_CONNECTION', 'iascomm'),

        'batch_size' => (int) env('PM_SYNC_BATCH_SIZE', 500),

        'stale_after_hours' => (int) env('PM_SYNC_STALE_HOURS', 2),

        'tables' => [
            'modbus_electric_readings' => 'electric',
            'modbus_heating_readings' => 'heat',
            'wmbus_water_readings' => 'water',
        ],
    ],

    'settlement_number_prefix' => env('PM_SETTLEMENT_PREFIX', 'ROZ'),

    /** Domyślna marża doliczana do ceny z faktury przy nowym rachunku (w procentach). */
    'default_margin_percent' => (float) env('PM_DEFAULT_MARGIN', 5),

    /** Podpis pod logo na ekranach logowania. */
    'brand_owner' => env('PM_BRAND_OWNER', 'ASCOMM Adrian Sajnaga'),

    /** Podpis w stopce rozliczenia PDF i w wiadomościach e-mail. */
    'report_issuer' => env('PM_REPORT_ISSUER', 'PM Property Manager, ASCOMM Adrian Sajnaga'),
];
