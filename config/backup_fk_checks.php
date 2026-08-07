<?php

/**
 * Katalog lengkap foreign key aplikasi ini (bukan cuma kolom yang dipakai
 * untuk scoping seperti config/backup_institution_scope.php) — dipakai
 * App\Services\Backup\BackupIntegrityChecker untuk mendeteksi baris orphan
 * setelah restore parsial (id-exact replace bisa membuat referensi dangling
 * kalau closure saat backup vs saat restore berbeda, mis. user yang
 * direferensikan sudah dihapus dari sistem sejak backup diambil).
 *
 * Setiap entri diperiksa dengan `column IS NOT NULL AND column NOT IN
 * (SELECT id FROM references)` — kolom nullable otomatis aman (NULL selalu
 * lolos), jadi daftar ini sengaja tidak membedakan nullable/NOT NULL.
 */
return [

    'checks' => [
        ['table' => 'mahasiswa_ta', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'mahasiswa_ta', 'column' => 'pembimbing_1_id', 'references' => 'users'],
        ['table' => 'mahasiswa_ta', 'column' => 'pembimbing_2_id', 'references' => 'users'],
        ['table' => 'mahasiswa_ta', 'column' => 'penguji_1_id', 'references' => 'users'],
        ['table' => 'mahasiswa_ta', 'column' => 'penguji_2_id', 'references' => 'users'],
        ['table' => 'mahasiswa_ta', 'column' => 'institution_id', 'references' => 'institutions'],
        ['table' => 'mahasiswa_ta_members', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],
        ['table' => 'mahasiswa_ta_members', 'column' => 'user_id', 'references' => 'users'],

        ['table' => 'logbook_entries', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],
        ['table' => 'logbook_entries', 'column' => 'dosen_id', 'references' => 'users'],
        ['table' => 'action_items', 'column' => 'logbook_entry_id', 'references' => 'logbook_entries'],
        ['table' => 'pdf_comments', 'column' => 'logbook_entry_id', 'references' => 'logbook_entries'],
        ['table' => 'pdf_comments', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'logbook_harian_kp', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],
        ['table' => 'inactivity_notifications', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],

        ['table' => 'sidangs', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],
        ['table' => 'sidangs', 'column' => 'penguji_id', 'references' => 'users'],
        ['table' => 'seminar_submissions', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],

        ['table' => 'thesis_finalizations', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],
        ['table' => 'finalization_approvals', 'column' => 'finalization_id', 'references' => 'thesis_finalizations'],
        ['table' => 'finalization_approvals', 'column' => 'pembimbing_id', 'references' => 'users'],

        ['table' => 'workspace_files', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],
        ['table' => 'workspace_files', 'column' => 'uploaded_by', 'references' => 'users'],
        ['table' => 'workspace_files', 'column' => 'user_id', 'references' => 'users'],

        ['table' => 'feedback_templates', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'storage_quota_notifications', 'column' => 'user_id', 'references' => 'users'],

        ['table' => 'admin_scopes', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'admin_scopes', 'column' => 'institution_id', 'references' => 'institutions'],
        ['table' => 'admin_scopes', 'column' => 'granted_by', 'references' => 'users'],
        ['table' => 'program_naming_configs', 'column' => 'institution_id', 'references' => 'institutions'],
        ['table' => 'institution_workspaces', 'column' => 'institution_id', 'references' => 'institutions'],
        ['table' => 'institution_workspaces', 'column' => 'created_by', 'references' => 'users'],
        ['table' => 'institution_workspace_files', 'column' => 'institution_workspace_id', 'references' => 'institution_workspaces'],
        ['table' => 'institution_workspace_files', 'column' => 'uploaded_by', 'references' => 'users'],
        ['table' => 'institution_workspace_files', 'column' => 'deleted_by', 'references' => 'users'],
        ['table' => 'institution_workspace_allowed_users', 'column' => 'institution_workspace_id', 'references' => 'institution_workspaces'],
        ['table' => 'institution_workspace_allowed_users', 'column' => 'user_id', 'references' => 'users'],

        ['table' => 'faculties', 'column' => 'university_id', 'references' => 'universities'],
        ['table' => 'departments', 'column' => 'faculty_id', 'references' => 'faculties'],
        ['table' => 'study_programs', 'column' => 'department_id', 'references' => 'departments'],
        ['table' => 'groups', 'column' => 'university_id', 'references' => 'universities'],
        ['table' => 'groups', 'column' => 'faculty_id', 'references' => 'faculties'],
        ['table' => 'groups', 'column' => 'department_id', 'references' => 'departments'],
        ['table' => 'groups', 'column' => 'study_program_id', 'references' => 'study_programs'],
        ['table' => 'groups', 'column' => 'created_by', 'references' => 'users'],
        ['table' => 'group_members', 'column' => 'group_id', 'references' => 'groups'],
        ['table' => 'group_members', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'user_university', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'user_university', 'column' => 'university_id', 'references' => 'universities'],
        ['table' => 'user_university', 'column' => 'faculty_id', 'references' => 'faculties'],
        ['table' => 'user_university', 'column' => 'department_id', 'references' => 'departments'],
        ['table' => 'user_university', 'column' => 'study_program_id', 'references' => 'study_programs'],

        ['table' => 'announcements', 'column' => 'sender_id', 'references' => 'users'],
        ['table' => 'announcements', 'column' => 'institution_id', 'references' => 'institutions'],
        ['table' => 'announcement_recipients', 'column' => 'announcement_id', 'references' => 'announcements'],
        ['table' => 'announcement_recipients', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'conversations', 'column' => 'mahasiswa_ta_id', 'references' => 'mahasiswa_ta'],
        ['table' => 'conversations', 'column' => 'user_one_id', 'references' => 'users'],
        ['table' => 'conversations', 'column' => 'user_two_id', 'references' => 'users'],
        ['table' => 'messages', 'column' => 'conversation_id', 'references' => 'conversations'],
        ['table' => 'messages', 'column' => 'sender_id', 'references' => 'users'],

        ['table' => 'user_achievements', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'user_achievements', 'column' => 'achievement_id', 'references' => 'achievements'],

        ['table' => 'subscriptions', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'subscriptions', 'column' => 'plan_id', 'references' => 'plans'],
        ['table' => 'user_plan_overrides', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'user_storage_addons', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'directory_subscriptions', 'column' => 'plan_id', 'references' => 'plans'],
        ['table' => 'directory_subscriptions', 'column' => 'assigned_by', 'references' => 'users'],
        ['table' => 'directory_subscription_notifications', 'column' => 'directory_subscription_id', 'references' => 'directory_subscriptions'],
    ],

];
