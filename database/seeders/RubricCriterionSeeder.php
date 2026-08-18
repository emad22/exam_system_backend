<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RubricCriterion;

class RubricCriterionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $criteria = [
            // ── 1. General Format & Vocabulary ──────────────────────────────
            [
                'skill_type'  => 'writing',
                'category'    => 'General Format & Vocabulary',
                'name'        => 'Length (الطول)',
                'description' => 'Text length compared to the required length (طول التعبير مقارنة بالطول المطلوب)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 1,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'General Format & Vocabulary',
                'name'        => 'Vocabulary (المفردات)',
                'description' => 'Range and depth of vocabulary demonstrated (كم المفردات التي يعرفها الطالب)',
                'percentage'  => 10.00,
                'max_points'  => 90.00,
                'order_index' => 2,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'General Format & Vocabulary',
                'name'        => 'Spelling (الهجاء)',
                'description' => 'Spelling accuracy relative to text length (عدد أخطاء الهجاء مقترنة بطول النص)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 3,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'General Format & Vocabulary',
                'name'        => 'Punctuation (أدوات الترقيم)',
                'description' => 'Correct usage of punctuation marks (الاستخدام الصحيح لأدوات الترقيم)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 4,
                'is_active'   => true,
            ],

            // ── 2. Grammar & Syntax ─────────────────────────────────────────
            [
                'skill_type'  => 'writing',
                'category'    => 'Grammar & Syntax',
                'name'        => 'Conjugation (الصرف: تصريف أفعال)',
                'description' => 'Ability to conjugate verbs correctly (القدرة على تصريف الأفعال)',
                'percentage'  => 10.00,
                'max_points'  => 90.00,
                'order_index' => 5,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'Grammar & Syntax',
                'name'        => 'Tenses (الصرف: الزمن)',
                'description' => 'Proper use of verb tenses suited to the context (القدرة على استخدام الفعل المناسب للزمن)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 6,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'Grammar & Syntax',
                'name'        => 'Prepositions & Verbs (النحو: الأفعال وأدوات الجر)',
                'description' => 'Proper usage of prepositions with verbs (القدرة على استخدام حرف الجر المناسب للفعل)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 7,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'Grammar & Syntax',
                'name'        => 'General Grammar (النحو: الأخطاء العامة)',
                'description' => 'Ability to construct sound and grammatically correct sentences (القدرة على تكوين جمل عربية سليمة)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 8,
                'is_active'   => true,
            ],

            // ── 3. Content & Structure ──────────────────────────────────────
            [
                'skill_type'  => 'writing',
                'category'    => 'Content & Structure',
                'name'        => 'Comprehension & Clarity (المضمون: الفهم)',
                'description' => 'Clarity and ease of understanding the text (مدى سهولة فهم النص)',
                'percentage'  => 10.00,
                'max_points'  => 90.00,
                'order_index' => 9,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'Content & Structure',
                'name'        => 'Topic Relevance (المضمون: علاقة النص بالموضوع)',
                'description' => 'Focus and relevance of text to the prompt (النص يدور حول الموضوع المطلوب)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 10,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'Content & Structure',
                'name'        => 'Organization (المضمون: التنظيم)',
                'description' => 'Structure with introduction, body development, and conclusion (مقدمة وتطوير الأفكار والخاتمة)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 11,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'Content & Structure',
                'name'        => 'Coherence & Flow (المضمون: الربط)',
                'description' => 'Logical flow and cohesion within and between paragraphs (ترابط الجمل والفقرات)',
                'percentage'  => 5.00,
                'max_points'  => 45.00,
                'order_index' => 12,
                'is_active'   => true,
            ],

            // ── 4. Rhetoric & Style (Creativity & Impact) ───────────────────
            [
                'skill_type'  => 'writing',
                'category'    => 'Rhetoric & Style (Creativity & Impact)',
                'name'        => 'Bayan: Imagery & Figures of Speech (البلاغة: علم البيان)',
                'description' => 'Use of similes, metaphors, analogies, and imagery (الاستعانة بالصور والأخيلة مثل التشبيه والاستعارة)',
                'percentage'  => 10.00,
                'max_points'  => 90.00,
                'order_index' => 13,
                'is_active'   => true,
            ],
            [
                'skill_type'  => 'writing',
                'category'    => 'Rhetoric & Style (Creativity & Impact)',
                'name'        => 'Badi\': Rhetorical Devices (البلاغة: علم البديع)',
                'description' => 'Use of rhetorical devices, rhythm, antithesis, and synonymy (المحسنات البديعية مثل السجع والجناس والتضاد)',
                'percentage'  => 15.00,
                'max_points'  => 135.00,
                'order_index' => 14,
                'is_active'   => true,
            ],
        ];

        // Clear existing default writing criteria and re-insert
        RubricCriterion::where('skill_type', 'writing')->delete();

        foreach ($criteria as $item) {
            RubricCriterion::create($item);
        }
    }
}
