<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Akun untuk login saat demo (password: password)
        User::create([
            'name' => 'Admin Tiket',
            'email' => 'admin@tiket.test',
            'password' => 'password', // otomatis di-hash oleh cast 'hashed'
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Budi Customer',
            'email' => 'budi@tiket.test',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $events = [
            [
                'title' => 'Jakarta Music Festival 2026',
                'venue' => 'JIExpo Kemayoran, Jakarta',
                'days' => 30,
                'status' => 'published',
                'description' => "Festival musik tahunan dengan lebih dari 20 musisi lokal dan internasional.\nNikmati 2 panggung, food court, dan area komunitas.",
                'tickets' => [
                    ['name' => 'Early Bird', 'price' => 150000, 'quota' => 50],
                    ['name' => 'Reguler', 'price' => 250000, 'quota' => 200],
                    ['name' => 'VIP', 'price' => 750000, 'quota' => 30],
                ],
            ],
            [
                'title' => 'Tech Conference Indonesia',
                'venue' => 'ICE BSD, Tangerang',
                'days' => 45,
                'status' => 'published',
                'description' => "Konferensi teknologi untuk developer, product manager, dan startup founder.\nMateri: AI, cloud, dan web modern.",
                'tickets' => [
                    ['name' => 'Student', 'price' => 100000, 'quota' => 100],
                    ['name' => 'Professional', 'price' => 350000, 'quota' => 150],
                ],
            ],
            [
                'title' => 'Stand Up Comedy Night',
                'venue' => 'Teater Salihara, Jakarta',
                'days' => 14,
                'status' => 'published',
                'description' => 'Malam penuh tawa bersama komika-komika terbaik tanah air.',
                'tickets' => [
                    ['name' => 'Reguler', 'price' => 120000, 'quota' => 100],
                    ['name' => 'Front Row', 'price' => 200000, 'quota' => 5],
                ],
            ],
            [
                'title' => 'Workshop UI/UX Design',
                'venue' => 'Co-working Space Dago, Bandung',
                'days' => 21,
                'status' => 'published',
                'description' => 'Workshop praktik merancang aplikasi dari riset pengguna sampai prototipe di Figma.',
                'tickets' => [
                    ['name' => 'Peserta', 'price' => 75000, 'quota' => 40],
                ],
            ],
            [
                'title' => 'Pameran Seni Rupa Nusantara',
                'venue' => 'Galeri Nasional, Jakarta',
                'days' => 60,
                'status' => 'draft', // belum tampil di halaman publik
                'description' => 'Pameran karya seni rupa dari 34 provinsi.',
                'tickets' => [
                    ['name' => 'Umum', 'price' => 50000, 'quota' => 300],
                ],
            ],
        ];

        foreach ($events as $data) {
            $slug = Str::slug($data['title']);

            $event = Event::create([
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'],
                'venue' => $data['venue'],
                'start_at' => now()->addDays($data['days'])->setTime(19, 0),
                'banner' => "https://picsum.photos/seed/{$slug}/1200/600",
                'status' => $data['status'],
            ]);

            // createMany: membuat banyak ticket_types sekaligus lewat relasi
            $event->ticketTypes()->createMany($data['tickets']);
        }
    }
}
