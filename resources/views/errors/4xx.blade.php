{{--
    Cadangan untuk seluruh galat 4xx yang tidak punya berkasnya sendiri.

    Laravel mencari errors.{kode} lebih dulu, lalu errors.{golongan}xx. Dengan
    berkas ini, kode seperti 405, 413, dan 419 ikut memakai tampilan yang sama
    tanpa perlu satu berkas per kode.
--}}
@include('errors.layout')
