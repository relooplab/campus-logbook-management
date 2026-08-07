<?php

/**
 * Definisi modul untuk fitur System Backup & Restore (system_admin).
 *
 * Setiap modul memetakan ke satu atau lebih tabel aplikasi, plus kolom file
 * (storage) yang dimiliki baris-baris di tabel itu. `depends_on` adalah
 * modul lain yang WAJIB ikut ter-backup kalau modul ini dipilih — dihitung
 * transitif oleh App\Services\Backup\BackupModuleRegistry — karena hampir
 * semua tabel di aplikasi ini cascade ke `users` dan/atau `mahasiswa_ta`.
 *
 * Tabel framework Laravel (sessions, cache, jobs, personal_access_tokens,
 * notifications, password_reset_tokens, migrations) SENGAJA tidak didaftarkan
 * di sini — tabel-tabel itu hanya ikut pada full backup (mysqldump utuh
 * tanpa filter tabel), tidak relevan untuk backup parsial per-modul.
 *
 * Urutan tabel di setiap modul HARUS parent-dulu (mengikuti arah FK) supaya
 * urutan dump/restore konsisten.
 */
return [

    'modules' => [

        'users' => [
            'label' => 'Pengguna',
            'description' => 'Akun user (mahasiswa, dosen, admin) beserta template feedback & notifikasi kuota storage.',
            'tables' => ['users', 'feedback_templates', 'storage_quota_notifications'],
            'depends_on' => [],
            'storage' => [
                ['table' => 'users', 'column' => 'profile_photo_path', 'disk' => 'public'],
            ],
        ],

        'institutions' => [
            'label' => 'Institusi',
            'description' => 'Data institusi (tenant), workspace berbagi file institusi, konfigurasi penamaan program, dan pembatasan cakupan admin.',
            'tables' => [
                'institutions',
                'admin_scopes',
                'program_naming_configs',
                'institution_workspaces',
                'institution_workspace_files',
                'institution_workspace_allowed_users',
            ],
            'depends_on' => ['users'],
            'storage' => [
                ['table' => 'institutions', 'column' => 'logo_path', 'disk' => 'local'],
                ['table' => 'institution_workspace_files', 'column' => 'path', 'disk' => 'local'],
            ],
        ],

        'direktori_akademik' => [
            'label' => 'Direktori Akademik',
            'description' => 'Direktori global universitas/fakultas/departemen/prodi (bukan data tenant), grup dosen, dan keanggotaan multi-universitas.',
            'tables' => [
                'universities',
                'faculties',
                'departments',
                'study_programs',
                'groups',
                'group_members',
                'user_university',
            ],
            'depends_on' => ['users'],
            'storage' => [
                ['table' => 'universities', 'column' => 'logo_path', 'disk' => 'local'],
            ],
        ],

        'mahasiswa_ta' => [
            'label' => 'Data Mahasiswa/TA',
            'description' => 'Data program TA/KP mahasiswa (hub utama — hampir semua modul lain bergantung pada ini).',
            'tables' => ['mahasiswa_ta', 'mahasiswa_ta_members'],
            'depends_on' => ['users'],
            'storage' => [],
        ],

        'logbook_bimbingan' => [
            'label' => 'Logbook & Bimbingan',
            'description' => 'Entri logbook, catatan perbaikan, komentar PDF, action item, logbook harian KP, dan notifikasi inaktivitas.',
            'tables' => [
                'logbook_entries',
                'action_items',
                'pdf_comments',
                'logbook_harian_kp',
                'inactivity_notifications',
            ],
            'depends_on' => ['mahasiswa_ta'],
            'storage' => [
                ['table' => 'logbook_entries', 'column' => 'lampiran_path', 'disk' => 'local'],
                ['table' => 'logbook_entries', 'column' => 'catatan_perbaikan_path', 'disk' => 'local'],
                ['table' => 'logbook_harian_kp', 'column' => 'foto_1', 'disk' => 'local'],
                ['table' => 'logbook_harian_kp', 'column' => 'foto_2', 'disk' => 'local'],
            ],
        ],

        'sidang' => [
            'label' => 'Sidang & Seminar',
            'description' => 'Riwayat sidang/pengujian dan pengajuan seminar.',
            'tables' => ['sidangs', 'seminar_submissions'],
            // 'users' dicantumkan eksplisit (selain lewat mahasiswa_ta) karena
            // sidangs.penguji_id adalah FK NOT NULL (cascadeOnDelete keras).
            'depends_on' => ['mahasiswa_ta', 'users'],
            'storage' => [
                ['table' => 'seminar_submissions', 'column' => 'undangan_path', 'disk' => 'local'],
                ['table' => 'seminar_submissions', 'column' => 'materi_path', 'disk' => 'local'],
            ],
        ],

        'finalisasi' => [
            'label' => 'Finalisasi Tugas Akhir',
            'description' => 'Finalisasi TA (cover, pengesahan, file lengkap) dan approval pembimbing.',
            'tables' => ['thesis_finalizations', 'finalization_approvals'],
            'depends_on' => ['mahasiswa_ta', 'users'],
            'storage' => [
                ['table' => 'thesis_finalizations', 'column' => 'cover_path', 'disk' => 'local'],
                ['table' => 'thesis_finalizations', 'column' => 'pengesahan_path', 'disk' => 'local'],
                ['table' => 'thesis_finalizations', 'column' => 'full_file_path', 'disk' => 'local'],
            ],
        ],

        'files' => [
            'label' => 'Berkas Workspace',
            'description' => 'Berkas workspace milik mahasiswa maupun dosen.',
            'tables' => ['workspace_files'],
            'depends_on' => ['mahasiswa_ta', 'users'],
            'storage' => [
                ['table' => 'workspace_files', 'column' => 'path', 'disk' => 'local'],
            ],
        ],

        'komunikasi' => [
            'label' => 'Komunikasi',
            'description' => 'Pengumuman, chat/percakapan dosen-mahasiswa, dan pesan.',
            'tables' => ['announcements', 'announcement_recipients', 'conversations', 'messages'],
            'depends_on' => ['users'],
            'storage' => [],
        ],

        'gamifikasi' => [
            'label' => 'Gamifikasi',
            'description' => 'Definisi achievement dan achievement yang sudah di-unlock user.',
            'tables' => ['achievements', 'user_achievements'],
            'depends_on' => ['users'],
            'storage' => [],
        ],

        'billing' => [
            'label' => 'Billing & Paket',
            'description' => 'Paket, langganan user & direktori, override paket per-user, dan top-up storage.',
            'tables' => [
                'plans',
                'subscriptions',
                'user_plan_overrides',
                'user_storage_addons',
                'directory_subscriptions',
                'directory_subscription_notifications',
            ],
            'depends_on' => ['users'],
            'storage' => [],
        ],

    ],

];
