<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\Student;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ExamService
{
    /**
     * Determine whether a user is a demo / test account.
     */
    public function isDemoUser(?Authenticatable $user): bool
    {
        if (!$user)
            return false;
        if (in_array(strtolower($user->role ?? ''), ['demo', 'deom', 'staff'])) {
            return true;
        }
        if (isset($user->student) && $user->student && $user->student->is_demo) {
            return true;
        }
        return false;
    }

    /**
     * Resolve the ordered list of allowed skill identifiers (ID / name / short_code)
     * for a student using a three-priority waterfall:
     *
     *   Priority 1 — student.assigned_skills  (e.g. ["STRU","READ","LIST","WRIT","LIVE"])
     *   Priority 2 — student.package.skills   (skills JSON on the linked package)
     *   Priority 3 — empty array              (caller falls back to ALL exam skills)
     */
    public function getAllowedSkills(?Student $student): array
    {
        if (!$student) {
            return [];
        }

        // Priority 1: explicitly assigned skills
        $identifiers = array_filter((array) $student->assigned_skills);

        // Priority 2: fall back to package skills
        if (empty($identifiers)) {
            $student->loadMissing('package');
            if ($student->package && !empty($student->package->skills)) {
                $identifiers = array_filter((array) $student->package->skills);
            }
        }

        // Priority 3: empty → caller should use all exam skills
        return array_values($identifiers);
    }

    /**
     * Resolve a list of skill identifiers (numeric IDs, short_codes, or names)
     * to actual Skill IDs via a single database query.
     * This is more reliable than string-based matching.
     */
    public function resolveSkillIds(array $identifiers): array
    {
        if (empty($identifiers)) {
            return [];
        }

        $numericIds  = array_values(array_filter($identifiers, 'is_numeric'));
        $stringCodes = array_values(array_filter($identifiers, fn($v) => !is_numeric($v)));
        $lower       = array_map(fn($v) => strtolower(trim($v)), $stringCodes);

        return Skill::query()
            ->where(function ($q) use ($numericIds, $lower) {
                if (!empty($numericIds)) {
                    $q->orWhereIn('id', $numericIds);
                }
                if (!empty($lower)) {
                    // Exact match on short_code (case-insensitive)
                    $q->orWhereRaw(
                        'LOWER(TRIM(short_code)) IN (' . implode(',', array_fill(0, count($lower), '?')) . ')',
                        $lower
                    );
                    // Exact match on name (case-insensitive) — fallback for packages
                    // that store full names like "speaking" instead of short codes
                    $q->orWhereRaw(
                        'LOWER(TRIM(name)) IN (' . implode(',', array_fill(0, count($lower), '?')) . ')',
                        $lower
                    );
                }
            })
            ->pluck('id')
            ->toArray();
    }

    /**
     * Filter an Eloquent/Support Collection of Skill models to only those
     * whose IDs are in the resolved set for the given identifiers.
     *
     * Uses resolveSkillIds() for exact DB-level matching — no substring false-positives.
     *
     * @param  Collection|SupportCollection  $skills
     * @param  array                         $identifiers
     * @return SupportCollection
     */
    public function filterSkills($skills, array $identifiers): SupportCollection
    {
        if (empty($identifiers)) {
            return collect($skills)->values();
        }

        $allowedIds = $this->resolveSkillIds($identifiers);

        if (empty($allowedIds)) {
            // Identifiers were supplied but none matched any known skill → show all
            return collect($skills)->values();
        }

        return collect($skills)
            ->filter(fn(Skill $skill) => in_array($skill->id, $allowedIds))
            ->values();
    }

    /**
     * Check whether a single Skill matches any entry in the allowed-identifiers list.
     * Used by createNewAttempt() — kept for backwards compatibility.
     * Uses exact matching only (numeric ID or exact short_code / name).
     */
    public function skillMatchesIdentifiers(Skill $skill, array $identifiers): bool
    {
        $skillName = strtolower(trim($skill->name));
        $skillCode = strtolower(trim($skill->short_code ?? ''));

        foreach ($identifiers as $idOrCode) {
            $match = strtolower(trim((string) $idOrCode));
            if ($match === '') {
                continue;
            }

            // 1. Exact numeric ID
            if (is_numeric($match) && $skill->id == $match) {
                return true;
            }

            // 2. Exact name / short_code match
            if ($skillName === $match || $skillCode === $match) {
                return true;
            }

            // 3. Partial / contains — only if BOTH sides are >= 4 chars
            // (prevents 'S' or 'LIVE' from matching inside 'LIST','WRIT','STRU', etc.)
            if (mb_strlen($match) >= 4 && mb_strlen($skillName) >= 4) {
                if (str_contains($skillName, $match) || str_contains($match, $skillName)) {
                    return true;
                }
            }

            if ($skillCode !== '' && mb_strlen($match) >= 4 && mb_strlen($skillCode) >= 4) {
                if (str_contains($skillCode, $match) || str_contains($match, $skillCode)) {
                    return true;
                }
            }
        }

        return false;
    }
}
