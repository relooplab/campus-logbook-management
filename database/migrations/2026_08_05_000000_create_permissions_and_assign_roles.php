<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Daftar semua permission yang tersedia di sistem.
     * Grup: logbook, workspace, export, seminar, finalisasi, sidang,
     * komunikasi, admin, system, storage, grup, approval.
     */
    public const PERMISSIONS = [
        // Logbook
        'logbook.create',
        'logbook.review',
        // Workspace
        'workspace.upload',
        'workspace.delete',
        'workspace.manage-others',
        // Export / Import
        'export.pdf',
        'export.excel',
        'import.excel',
        // Seminar
        'seminar.submit',
        'seminar.review',
        // Finalisasi
        'finalization.submit',
        'finalization.approve',
        // Sidang
        'sidang.record',
        // Komunikasi
        'announcement.create',
        'chat.send',
        // Admin
        'admin.users',
        'admin.tas',
        'admin.sidangs',
        'admin.institution',
        'admin.bulk-review',
        // Storage
        'storage.manage',
        // Grup
        'groups.create',
        'groups.invite',
        // Approval
        'approval.manage',
        // System (khusus system_admin)
        'system.admins',
        'system.plans',
    ];

    public function up(): void
    {
        // Buat semua permission.
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Assign permission ke role.
        $systemAdmin = Role::findOrCreate('system_admin');
        $admin = Role::findOrCreate('admin');
        $dosen = Role::findOrCreate('dosen');
        $mahasiswa = Role::findOrCreate('mahasiswa');

        // system_admin: semua permission.
        $systemAdmin->syncPermissions(self::PERMISSIONS);

        // admin: semua kecuali system.*
        $admin->syncPermissions(array_values(array_filter(
            self::PERMISSIONS,
            fn ($p) => !str_starts_with($p, 'system.')
        )));

        // dosen: review, workspace, export, seminar.review, finalization.approve,
        // sidang, announcement, chat, storage, groups, approval.
        $dosen->syncPermissions([
            'logbook.review',
            'workspace.upload',
            'workspace.delete',
            'workspace.manage-others',
            'export.pdf',
            'export.excel',
            'seminar.review',
            'finalization.approve',
            'sidang.record',
            'announcement.create',
            'chat.send',
            'storage.manage',
            'groups.create',
            'groups.invite',
            'approval.manage',
        ]);

        // mahasiswa: create logbook, workspace upload/delete, seminar.submit,
        // finalization.submit, chat.
        $mahasiswa->syncPermissions([
            'logbook.create',
            'workspace.upload',
            'workspace.delete',
            'seminar.submit',
            'finalization.submit',
            'chat.send',
        ]);
    }

    public function down(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findByName($permission, 'web')?->delete();
        }
    }
};