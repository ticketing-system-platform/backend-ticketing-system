# P11 — Presentasi

## Alur demo (±10 menit per siswa)

1. **Pengantar (1 menit):** masalah yang diselesaikan dan teknologi yang dipakai.
2. **ERD & arsitektur (2 menit):** 7 tabel + diagram alur pembayaran.
3. **Demo customer (3 menit):** cari event → pilih tiket → bayar di simulator → status otomatis lunas → email → Tiket Saya + QR.
4. **Demo admin (2 menit):** tambah event + jenis tiket → dashboard → scan QR tiket tadi → scan ulang ditolak.
5. **Bagian tersulit (1 menit):** jelaskan `lockForUpdate`, signature webhook, atau idempotensi.
6. **Tanya jawab (1 menit).**

## Pertanyaan yang bisa diajukan penguji

- Apa yang terjadi jika dua orang membeli tiket terakhir bersamaan?
- Kenapa status pembayaran tidak diambil dari `onSuccess` Snap?
- Bagaimana kamu tahu notifikasi benar-benar dari Midtrans?
- Apa yang terjadi jika Midtrans mengirim notifikasi yang sama tiga kali?
- Kenapa email dikirim lewat queue dan setelah commit?
- Bagaimana kuota kembali jika customer tidak jadi membayar?

## Rubrik penilaian (usulan)

| Aspek | Bobot | Kriteria |
|---|---|---|
| Fitur | 35% | Semua halaman customer & admin berjalan end-to-end |
| Keamanan & keandalan pembayaran | 25% | Lock kuota, signature, idempoten, expire, key di `.env` |
| Kualitas kode | 15% | Form Request, Resource, Service, penamaan rapi, commit teratur |
| UI/UX | 15% | Rapi, responsif, ada loading/empty/error state |
| Presentasi | 10% | Demo lancar, mampu menjelaskan alur dan menjawab pertanyaan |

---

⬅️ [P10 — Pengujian, Bug Fix & Deploy](P10-pengujian-bugfix-deploy.md) · 🏠 [Daftar isi](../../README.md#daftar-isi) · [Lampiran](lampiran.md) ➡️
