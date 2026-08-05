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
    | Operasional SHU tahunan dan Dana Sosial sudah dilengkapi dengan pembagian
    | berbobot berdasarkan keputusan RAT, maker-checker, pembayaran personal,
    | jurnal, Mutasi Kas, preflight, dan histori periode.
    |
    */
    'shu_enabled' => $boolean('FEATURE_SHU_ENABLED', true),

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

    /*
    |--------------------------------------------------------------------------
    | Master Printer Koperasi
    |--------------------------------------------------------------------------
    |
    | Transaksi Sewa Hardware final memakai vendor eksternal dan snapshot
    | transaksi, bukan aset Printer koperasi. Master aset printer lama disimpan
    | untuk histori/rollback, tetapi route/menu runtime dimatikan secara default.
    |
    */
    'master_printer_enabled' => $boolean('FEATURE_MASTER_PRINTER_ENABLED', false),
];
