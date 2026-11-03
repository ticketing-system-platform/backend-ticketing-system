<?php

// Pesan validasi Bahasa Indonesia (hanya aturan yang dipakai di proyek ini).
// Aturan lain otomatis memakai pesan bahasa Inggris (fallback_locale).
return [
    'array' => ':attribute harus berupa daftar.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang valid.',
    'distinct' => ':attribute tidak boleh duplikat.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa angka bulat.',
    'max' => [
        'array' => ':attribute maksimal berisi :max item.',
        'file' => ':attribute maksimal :max kilobyte.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],
    'min' => [
        'array' => ':attribute minimal berisi :min item.',
        'file' => ':attribute minimal :min kilobyte.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terdaftar.',

    // Nama field yang tampil di pesan error
    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Password',
        'title' => 'Judul',
        'description' => 'Deskripsi',
        'venue' => 'Lokasi',
        'start_at' => 'Waktu mulai',
        'status' => 'Status',
        'banner' => 'Banner',
        'event_id' => 'Event',
        'price' => 'Harga',
        'quota' => 'Kuota',
        'items' => 'Tiket',
        'items.*.ticket_type_id' => 'Jenis tiket',
        'items.*.qty' => 'Jumlah tiket',
        'code' => 'Kode tiket',
    ],
];
