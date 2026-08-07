<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Hitung himpunan baris (row-level) yang harus ikut backup/restore ketika
 * admin memfilter berdasarkan institusi tertentu — lihat
 * config/backup_institution_scope.php untuk strategi per tabel.
 *
 * Desain sengaja berupa pass tetap & terbatas (bounded enumeration), BUKAN
 * traversal graph generik/rekursif — semua jalur FK aplikasi ini sudah
 * diinventarisasi, jadi traversal generik hanya menambah risiko ekspansi tak
 * terduga tanpa manfaat.
 *
 * Kalau $institutionIds kosong (tidak ada filter institusi — mis. backup
 * per-modul biasa tanpa narrow institusi), semua tabel yang diminta langsung
 * dikembalikan sebagai '*' (tidak difilter) tanpa query tambahan apapun —
 * sama sekali tidak mengubah perilaku Milestone 1.
 */
class InstitutionClosureResolver
{
    /**
     * @param array<int,string> $tables tabel yang scope-nya perlu dihitung
     * @param array<int,int> $institutionIds kosong = tidak ada filter institusi
     * @return array{scope: array<string, array<int,int>|string>, closure_expansions: array<int,array{table:string,user_id:int,reason:string}>, skipped_conversations_outside_scope: int}
     */
    public function resolve(array $tables, array $institutionIds, bool $includeIndividual): array
    {
        if ($institutionIds === []) {
            $scope = [];
            foreach ($tables as $table) {
                $scope[$table] = '*';
            }

            return ['scope' => $scope, 'closure_expansions' => [], 'skipped_conversations_outside_scope' => 0];
        }

        $strategies = config('backup_institution_scope.tables');
        $expansionDefs = config('backup_institution_scope.user_expansions');

        // 1. Anchor: mahasiswa_ta dalam institusi terpilih.
        $mtaRows = $this->queryInstitutionColumn('mahasiswa_ta', $institutionIds, $includeIndividual, true)
            ->get(['id', 'user_id', 'pembimbing_1_id', 'pembimbing_2_id', 'penguji_1_id', 'penguji_2_id']);
        $mahasiswaTaIds = $mtaRows->pluck('id')->all();

        // 2. Base user set (langsung anggota institusi terpilih).
        $baseUserIds = $this->queryInstitutionColumn('users', $institutionIds, $includeIndividual, true)->pluck('id')->all();

        $resolved = [
            'mahasiswa_ta' => $mahasiswaTaIds,
            'institutions' => $institutionIds,
        ];

        // 3. Resolve parent tingkat-2 yang dibutuhkan tabel 'via' & pass ekspansi.
        $resolved['logbook_entries'] = $this->resolveByStrategy('logbook_entries', $strategies['logbook_entries'], $resolved, $institutionIds, $includeIndividual);
        $resolved['thesis_finalizations'] = $this->resolveByStrategy('thesis_finalizations', $strategies['thesis_finalizations'], $resolved, $institutionIds, $includeIndividual);
        $resolved['institution_workspaces'] = $this->resolveByStrategy('institution_workspaces', $strategies['institution_workspaces'], $resolved, $institutionIds, $includeIndividual);
        $resolved['announcements'] = $this->resolveByStrategy('announcements', $strategies['announcements'], $resolved, $institutionIds, $includeIndividual);

        // 4. Pass ekspansi -> himpunan user final.
        $userIds = collect($baseUserIds);
        $expansions = [];
        $addUsers = function (iterable $ids, string $reason) use (&$userIds, &$expansions, $baseUserIds) {
            foreach ($ids as $id) {
                if ($id === null) {
                    continue;
                }
                if (!in_array($id, $baseUserIds, true)) {
                    $expansions[] = ['table' => 'users', 'user_id' => $id, 'reason' => $reason];
                }
                $userIds->push($id);
            }
        };

        foreach ($mtaRows as $row) {
            $addUsers([$row->user_id, $row->pembimbing_1_id, $row->pembimbing_2_id, $row->penguji_1_id, $row->penguji_2_id], 'mahasiswa_ta_role');
        }

        foreach ($expansionDefs as $def) {
            if ($def['source'] === 'mahasiswa_ta') {
                continue; // sudah ditangani di atas (butuh beberapa kolom per baris sekaligus).
            }

            [$scopedColumn, $scopedParent] = $def['scoped_by'];
            $query = DB::table($def['source']);
            if ($scopedParent === null) {
                $query->whereIn($scopedColumn, $institutionIds);
            } else {
                $query->whereIn($scopedColumn, $resolved[$scopedParent] ?? []);
            }

            $rows = $query->select($def['columns'])->get();
            foreach ($def['columns'] as $col) {
                $addUsers($rows->pluck($col)->all(), $def['reason']);
            }
        }

        $finalUserIds = $userIds->filter()->unique()->values()->all();
        $resolved['users'] = $finalUserIds;

        // 5. Conversations — kasus khusus, tidak memicu ekspansi user baru.
        $resolved['conversations'] = DB::table('conversations')
            ->whereIn('user_one_id', $finalUserIds)
            ->whereIn('user_two_id', $finalUserIds)
            ->pluck('id')->all();

        $skippedConversations = 0;
        if (in_array('conversations', $tables, true) || in_array('messages', $tables, true)) {
            $skippedConversations = DB::table('conversations')
                ->where(fn ($q) => $q->whereNotIn('user_one_id', $finalUserIds)->orWhereNotIn('user_two_id', $finalUserIds))
                ->count();
        }

        // 6. Resolusi generik untuk setiap tabel yang diminta.
        $scope = [];
        foreach ($tables as $table) {
            $scope[$table] = $resolved[$table]
                ?? $this->resolveByStrategy($table, $strategies[$table] ?? ['type' => 'catalog'], $resolved, $institutionIds, $includeIndividual);
        }

        return [
            'scope' => $scope,
            'closure_expansions' => $expansions,
            'skipped_conversations_outside_scope' => $skippedConversations,
        ];
    }

    /**
     * @param array<string, array<int,int>|string> $resolved
     * @return array<int,int>|string '*' = tidak difilter (catalog)
     */
    private function resolveByStrategy(string $table, array $strategy, array $resolved, array $institutionIds, bool $includeIndividual): array|string
    {
        switch ($strategy['type']) {
            case 'catalog':
                return '*';

            case 'institution_row':
                return $institutionIds;

            case 'institution_column':
                return $this->queryInstitutionColumn($table, $institutionIds, $includeIndividual, $strategy['nullable_individual'])
                    ->pluck('id')->all();

            case 'user_scope':
                return DB::table($table)->whereIn($strategy['column'], $resolved['users'] ?? [])->pluck('id')->all();

            case 'mahasiswa_ta_scope':
                return DB::table($table)->whereIn($strategy['column'], $resolved['mahasiswa_ta'] ?? [])->pluck('id')->all();

            case 'via':
                $parentScope = $resolved[$strategy['parent']] ?? $this->resolveByStrategy(
                    $strategy['parent'],
                    config('backup_institution_scope.tables.'.$strategy['parent']),
                    $resolved,
                    $institutionIds,
                    $includeIndividual
                );

                if ($parentScope === '*') {
                    return '*';
                }

                return DB::table($table)->whereIn($strategy['column'], $parentScope)->pluck('id')->all();

            case 'conversation_scope':
                return $resolved['conversations'] ?? [];

            default:
                return '*';
        }
    }

    private function queryInstitutionColumn(string $table, array $institutionIds, bool $includeIndividual, bool $nullableIndividual): \Illuminate\Database\Query\Builder
    {
        return DB::table($table)->where(function ($q) use ($institutionIds, $includeIndividual, $nullableIndividual) {
            $q->whereIn('institution_id', $institutionIds);
            if ($includeIndividual && $nullableIndividual) {
                $q->orWhereNull('institution_id');
            }
        });
    }
}
