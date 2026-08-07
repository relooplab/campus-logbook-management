<?php

/**
 * Strategi filter per-institusi untuk setiap tabel yang bisa di-backup (lihat
 * config/backup_modules.php untuk daftar modul/tabelnya). Dipakai oleh
 * App\Services\Backup\InstitutionClosureResolver saat admin memilih institusi
 * tertentu (bukan "semua") — dilewati sama sekali kalau tidak ada filter
 * institusi (module-only backup, seperti Milestone 1).
 *
 * Tipe strategi:
 * - catalog            : selalu ikut penuh, tidak pernah difilter (direktori
 *                        akademik global, katalog achievement/plan, dst — ini
 *                        BUKAN data tenant, jadi memotongnya lebih berbahaya
 *                        daripada membiarkannya lengkap-tapi-lebih-besar).
 * - institution_row    : tabel institutions itu sendiri — filter oleh id IN
 *                        institusi terpilih (anchor).
 * - institution_column : filter oleh kolom institution_id IN institusi
 *                        terpilih. `nullable_individual: true` berarti baris
 *                        dengan institution_id NULL ikut juga kalau opsi
 *                        "sertakan data individual" dicentang.
 * - user_scope         : filter oleh kolom user_id IN himpunan user hasil
 *                        closure (lihat InstitutionClosureResolver).
 * - mahasiswa_ta_scope : filter oleh kolom mahasiswa_ta_id IN himpunan
 *                        mahasiswa_ta hasil closure.
 * - via                : filter lewat tabel induk (2 level) — kolom di tabel
 *                        ini menunjuk ke `parent`, ikut kalau baris `parent`
 *                        ikut. Kalau parent-nya catalog, tabel ini otomatis
 *                        ikut penuh juga (tidak perlu query tambahan).
 * - conversation_scope : khusus `conversations` — ikut kalau user_one_id DAN
 *                        user_two_id keduanya ada di himpunan user hasil
 *                        closure (tidak memicu ekspansi user baru).
 */
return [

    'tables' => [

        'users' => ['type' => 'institution_column', 'column' => 'institution_id', 'nullable_individual' => true],
        'feedback_templates' => ['type' => 'user_scope', 'column' => 'user_id'],
        'storage_quota_notifications' => ['type' => 'user_scope', 'column' => 'user_id'],

        'institutions' => ['type' => 'institution_row'],
        'admin_scopes' => ['type' => 'institution_column', 'column' => 'institution_id', 'nullable_individual' => false],
        'program_naming_configs' => ['type' => 'institution_column', 'column' => 'institution_id', 'nullable_individual' => false],
        'institution_workspaces' => ['type' => 'institution_column', 'column' => 'institution_id', 'nullable_individual' => false],
        'institution_workspace_files' => ['type' => 'via', 'column' => 'institution_workspace_id', 'parent' => 'institution_workspaces'],
        'institution_workspace_allowed_users' => ['type' => 'via', 'column' => 'institution_workspace_id', 'parent' => 'institution_workspaces'],

        'universities' => ['type' => 'catalog'],
        'faculties' => ['type' => 'catalog'],
        'departments' => ['type' => 'catalog'],
        'study_programs' => ['type' => 'catalog'],
        'groups' => ['type' => 'catalog'],
        'group_members' => ['type' => 'user_scope', 'column' => 'user_id'],
        'user_university' => ['type' => 'user_scope', 'column' => 'user_id'],

        'mahasiswa_ta' => ['type' => 'institution_column', 'column' => 'institution_id', 'nullable_individual' => true],
        'mahasiswa_ta_members' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],

        'logbook_entries' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],
        'action_items' => ['type' => 'via', 'column' => 'logbook_entry_id', 'parent' => 'logbook_entries'],
        'pdf_comments' => ['type' => 'via', 'column' => 'logbook_entry_id', 'parent' => 'logbook_entries'],
        'logbook_harian_kp' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],
        'inactivity_notifications' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],

        'sidangs' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],
        'seminar_submissions' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],

        'thesis_finalizations' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],
        'finalization_approvals' => ['type' => 'via', 'column' => 'finalization_id', 'parent' => 'thesis_finalizations'],

        'workspace_files' => ['type' => 'mahasiswa_ta_scope', 'column' => 'mahasiswa_ta_id'],

        'announcements' => ['type' => 'institution_column', 'column' => 'institution_id', 'nullable_individual' => true],
        'announcement_recipients' => ['type' => 'via', 'column' => 'announcement_id', 'parent' => 'announcements'],

        'conversations' => ['type' => 'conversation_scope'],
        'messages' => ['type' => 'via', 'column' => 'conversation_id', 'parent' => 'conversations'],

        'achievements' => ['type' => 'catalog'],
        'user_achievements' => ['type' => 'user_scope', 'column' => 'user_id'],

        'plans' => ['type' => 'catalog'],
        'subscriptions' => ['type' => 'user_scope', 'column' => 'user_id'],
        'user_plan_overrides' => ['type' => 'user_scope', 'column' => 'user_id'],
        'user_storage_addons' => ['type' => 'user_scope', 'column' => 'user_id'],
        'directory_subscriptions' => ['type' => 'catalog'],
        'directory_subscription_notifications' => ['type' => 'via', 'column' => 'directory_subscription_id', 'parent' => 'directory_subscriptions'],

    ],

    /**
     * Pass ekspansi user (row-level, bukan traversal generik — daftar tetap &
     * terbatas karena semua jalur FK sudah diinventarisasi). Setiap entri:
     * kalau `table` ada di daftar tabel yang diminta, tarik `column` dari
     * tabel itu (difilter `via` kalau 2 level) ke dalam himpunan user.
     */
    'user_expansions' => [
        // Dari baris mahasiswa_ta yang sudah dalam scope (selalu jalan, ini anchor).
        ['reason' => 'mahasiswa_ta_role', 'source' => 'mahasiswa_ta', 'columns' => ['user_id', 'pembimbing_1_id', 'pembimbing_2_id', 'penguji_1_id', 'penguji_2_id']],
        ['reason' => 'kp_member', 'table' => 'mahasiswa_ta_members', 'source' => 'mahasiswa_ta_members', 'columns' => ['user_id'], 'scoped_by' => ['mahasiswa_ta_id', 'mahasiswa_ta']],
        ['reason' => 'sidang_penguji', 'table' => 'sidangs', 'source' => 'sidangs', 'columns' => ['penguji_id'], 'scoped_by' => ['mahasiswa_ta_id', 'mahasiswa_ta']],
        ['reason' => 'finalization_approval', 'table' => 'finalization_approvals', 'source' => 'finalization_approvals', 'columns' => ['pembimbing_id'], 'scoped_by' => ['finalization_id', 'thesis_finalizations']],
        ['reason' => 'logbook_reviewer', 'table' => 'logbook_entries', 'source' => 'logbook_entries', 'columns' => ['dosen_id'], 'scoped_by' => ['mahasiswa_ta_id', 'mahasiswa_ta']],
        ['reason' => 'pdf_comment_author', 'table' => 'pdf_comments', 'source' => 'pdf_comments', 'columns' => ['user_id'], 'scoped_by' => ['logbook_entry_id', 'logbook_entries']],
        ['reason' => 'workspace_admin', 'table' => 'institution_workspaces', 'source' => 'institution_workspaces', 'columns' => ['created_by'], 'scoped_by' => ['institution_id', null]],
        ['reason' => 'workspace_admin', 'table' => 'institution_workspace_files', 'source' => 'institution_workspace_files', 'columns' => ['uploaded_by', 'deleted_by'], 'scoped_by' => ['institution_workspace_id', 'institution_workspaces']],
        ['reason' => 'admin_scope_grantee', 'table' => 'admin_scopes', 'source' => 'admin_scopes', 'columns' => ['user_id', 'granted_by'], 'scoped_by' => ['institution_id', null]],
    ],

];
