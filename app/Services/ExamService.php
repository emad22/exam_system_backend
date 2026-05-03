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
        if (!$user) return false;
        return in_array(strtolower($user->role ?? ''), ['demo', 'deom', 'staff']);
    }

    /**
     * Resolve the ordered list of allowed skill identifiers (ID / name / short_code)
     * for a student using a three-priority waterfall:
     *   1. Explicitly assigned_skills on the student record.
     *   2. Skills array defined on the student's package.
     *   3. Empty array — caller should fall back to all exam skills.
     */
    public function getAllowedSkills(?Student $student): array
    {
        if (!$student) {
            return [];
        }

        $identifiers = array_filter((array) $student->assigned_skills);

        if (empty($identifiers) && $student->package && $student->package->skills) {
            $identifiers = array_filter((array) $student->package->skills);
        }

        return array_values($identifiers);
    }

    /**
     * Filter an Eloquent/Support Collection of Skill models to only those
     * matching the supplied identifiers (ID, name, or short_code — case-insensitive).
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

        return collect($skills)
            ->filter(fn(Skill $skill) => $this->skillMatchesIdentifiers($skill, $identifiers))
            ->values();
    }

    /**
     * Check whether a single Skill matches any entry in the allowed-identifiers list.
     */
    public function skillMatchesIdentifiers(Skill $skill, array $identifiers): bool
    {
        $skillName = strtolower(trim($skill->name));
        $skillCode = strtolower(trim($skill->short_code ?? ''));

        foreach ($identifiers as $idOrCode) {
            $match = strtolower(trim((string) $idOrCode));
            if ($skill->id == $match || $skillName === $match || $skillCode === $match) {
                return true;
            }
        }

        return false;
    }
}
