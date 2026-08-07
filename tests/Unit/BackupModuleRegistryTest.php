<?php

namespace Tests\Unit;

use App\Services\Backup\BackupModuleRegistry;
use Tests\TestCase;

/**
 * Test resolusi dependency closure & urutan topologis modul backup — tidak
 * menyentuh DB/mysqldump, murni logic terhadap config/backup_modules.php.
 */
class BackupModuleRegistryTest extends TestCase
{
    private BackupModuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new BackupModuleRegistry();
    }

    public function test_selecting_logbook_pulls_in_mahasiswa_ta_and_users(): void
    {
        $closure = $this->registry->resolveDependencyClosure(['logbook_bimbingan']);

        $this->assertContains('logbook_bimbingan', $closure);
        $this->assertContains('mahasiswa_ta', $closure);
        $this->assertContains('users', $closure);
    }

    public function test_selecting_sidang_pulls_in_users_directly(): void
    {
        // sidangs.penguji_id adalah FK NOT NULL cascade ke users — harus
        // ikut meski hanya lewat mahasiswa_ta secara transitif juga sudah cukup,
        // tapi config mencantumkannya eksplisit untuk kejelasan.
        $closure = $this->registry->resolveDependencyClosure(['sidang']);

        $this->assertContains('users', $closure);
        $this->assertContains('mahasiswa_ta', $closure);
    }

    public function test_users_module_has_no_dependencies(): void
    {
        $closure = $this->registry->resolveDependencyClosure(['users']);

        $this->assertSame(['users'], $closure);
    }

    public function test_topological_order_places_users_before_mahasiswa_ta_before_logbook(): void
    {
        $closure = $this->registry->resolveDependencyClosure(['logbook_bimbingan']);
        $order = $this->registry->topologicalOrder($closure);

        $usersPos = array_search('users', $order, true);
        $mahasiswaTaPos = array_search('mahasiswa_ta', $order, true);
        $logbookPos = array_search('logbook_bimbingan', $order, true);

        $this->assertNotFalse($usersPos);
        $this->assertNotFalse($mahasiswaTaPos);
        $this->assertNotFalse($logbookPos);
        $this->assertLessThan($mahasiswaTaPos, $usersPos);
        $this->assertLessThan($logbookPos, $mahasiswaTaPos);
    }

    public function test_tables_for_modules_orders_parent_tables_before_children(): void
    {
        $closure = $this->registry->resolveDependencyClosure(['logbook_bimbingan']);
        $tables = $this->registry->tablesForModules($closure);

        $usersPos = array_search('users', $tables, true);
        $mahasiswaTaPos = array_search('mahasiswa_ta', $tables, true);
        $logbookEntriesPos = array_search('logbook_entries', $tables, true);
        $actionItemsPos = array_search('action_items', $tables, true);

        $this->assertNotFalse($usersPos);
        $this->assertNotFalse($mahasiswaTaPos);
        $this->assertNotFalse($logbookEntriesPos);
        $this->assertNotFalse($actionItemsPos);

        $this->assertLessThan($mahasiswaTaPos, $usersPos);
        $this->assertLessThan($logbookEntriesPos, $mahasiswaTaPos);
        $this->assertLessThan($actionItemsPos, $logbookEntriesPos);
    }

    public function test_all_module_keys_are_resolvable_and_have_valid_dependencies(): void
    {
        foreach ($this->registry->allModuleKeys() as $key) {
            $closure = $this->registry->resolveDependencyClosure([$key]);
            $order = $this->registry->topologicalOrder($closure);

            $this->assertSame(count($closure), count($order));
            $this->assertContains($key, $order);
        }
    }
}
