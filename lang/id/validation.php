<?php

return [
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi saat :other adalah :value.',
    'string' => ':attribute harus berupa teks.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Password tidak benar.',
    'array' => ':attribute harus berupa daftar.',
    'file' => ':attribute harus berupa file.',
    'uploaded' => ':attribute gagal diunggah.',
    'in' => ':attribute yang dipilih tidak valid.',
    'uuid' => ':attribute harus berupa pengenal yang valid.',
    'accepted' => ':attribute harus dikonfirmasi.',
    'distinct' => ':attribute berisi nilai duplikat.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'min' => ['string' => ':attribute minimal :min karakter.', 'array' => ':attribute minimal :min item.', 'numeric' => ':attribute minimal :min.', 'file' => ':attribute minimal :min KB.'],
    'max' => ['string' => ':attribute maksimal :max karakter.', 'array' => ':attribute maksimal :max item.', 'numeric' => ':attribute maksimal :max.', 'file' => ':attribute maksimal :max KB.'],
    'password' => ['letters' => ':attribute harus mengandung huruf.', 'mixed' => ':attribute harus mengandung huruf besar dan kecil.', 'numbers' => ':attribute harus mengandung angka.', 'symbols' => ':attribute harus mengandung simbol.', 'uncompromised' => ':attribute pernah muncul dalam kebocoran data. Pilih password lain.'],
    'attributes' => ['q' => 'Kata pencarian', 'name' => 'Nama', 'email' => 'Email', 'password' => 'Password', 'path' => 'Lokasi', 'sources' => 'Pilihan item', 'files' => 'File', 'files.*' => 'File', 'content' => 'Isi file', 'action' => 'Tindakan', 'confirm' => 'Penghapusan permanen', 'id' => 'Pengenal'],
];
