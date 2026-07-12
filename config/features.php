<?php

$boolean = static fn (string $key, bool $default = false): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOLEAN
);

return [
    /*
    |--------------------------------------------------------------------------
    | Feature flags sementara KBSM
    |--------------------------------------------------------------------------
    |
    | SHU ditunda sementara sampai keputusan RAT/client berikutnya. Kode,
    | migration, model, service, dan data historis tetap dipertahankan agar
    | fitur dapat diaktifkan kembali lewat konfigurasi tanpa restore file.
    |
    */
    'shu_enabled' => $boolean('FEATURE_SHU_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Jasa Print
    |--------------------------------------------------------------------------
    |
    | Modul transaksi Jasa Print belum diimplementasikan. Kategori ledger
    | future-proof tetap boleh ada, tetapi UI/route runtime tidak tersedia
    | selama flag ini false.
    |
    */
    'jasa_print_enabled' => $boolean('FEATURE_JASA_PRINT_ENABLED', false),
];
