<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Passage;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('passages')->truncate();
        DB::table('questions')->truncate();
        DB::table('question_options')->truncate();
        DB::table('exam_questions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // --- 1. Passages ---
        $passages = [
            ['id' => 1, 'type' => 'text', 'title' => null, 'content' => null],
            ['id' => 2, 'type' => 'text', 'title' => null, 'content' => null],
            ['id' => 3, 'type' => 'text', 'title' => null, 'content' => null],
            ['id' => 4, 'type' => 'text', 'title' => null, 'content' => null],
            ['id' => 5, 'type' => 'text', 'title' => null, 'content' => null],
            ['id' => 6, 'type' => 'text', 'title' => null, 'content' => null],
            ['id' => 7, 'type' => 'text', 'title' => 'السكن', 'content' => '<h2 class="ql-direction-rtl ql-align-center"><span class="ql-font-monospace">&nbsp;هَذِهِ&nbsp;شَقَّةُ&nbsp;أَحْمَدَ،</span></h2><h2 class="ql-direction-rtl ql-align-center"><span class="ql-font-monospace">&nbsp;شَقَّةُ&nbsp;أَحْمَدَ&nbsp;كَبِيرَةٌ،</span></h2><h2 class="ql-direction-rtl ql-align-center"><span class="ql-font-monospace">&nbsp;الشَّقَّةُ&nbsp;ثَلاثُ&nbsp;حُجُرَاتٍ،</span></h2><h2 class="ql-direction-rtl ql-align-center"><span class="ql-font-monospace">&nbsp;الْحُجُرَاتُ&nbsp;نَظِيفَةٌ،</span></h2><h2 class="ql-direction-rtl ql-align-center"><span class="ql-font-monospace">&nbsp;لَيْلَى&nbsp;أُخْتُ&nbsp;أَحْمَدَ،</span></h2><h2 class="ql-direction-rtl ql-align-center"><span class="ql-font-monospace">&nbsp;لَيْلَى&nbsp;تَسْكُنُ&nbsp;مَعَ&nbsp;أَحْمَدَ.</span></h2><p class="ql-direction-rtl"></p>'],
            ['id' => 8, 'type' => 'text', 'title' => 'الوظائف', 'content' => '<h2 class="ql-align-center" style="direction: rtl;">&nbsp;أَحْمَدُ&nbsp;صَدِيقُ&nbsp;خَالِدٍ&nbsp;مِنْ&nbsp;أَيَّامِ&nbsp;الْمَدْرَسَةِ</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;أَحْمَدُ&nbsp;رَجُلٌ&nbsp;فَقِيرٌ،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;وَهُوَ&nbsp;يَعْمَلُ&nbsp;كَثِيرًا&nbsp;كُلَّ&nbsp;يَوْمٍ،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;وَخَالِدٌ&nbsp;رَجُلٌ&nbsp;غَنِيٌّ،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;خالد&nbsp;يساعد&nbsp;أحمد&nbsp;دائما،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;أحمد&nbsp;سعيد&nbsp;وخالد&nbsp;أيضا،</h2><h2 style="direction: rtl;" class="ql-align-center">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;أَحْمَدُ&nbsp;وَخَالِدٌ&nbsp;صَدِيقَانِ&nbsp;إِلَى&nbsp;الآنَ.</h2>'],
            ['id' => 9, 'type' => 'text', 'title' => 'الصحة', 'content' => '<h2 class="ql-align-center" style="direction: rtl;">رَاحَةُ&nbsp;الْجِسْمِ&nbsp;تَبْدَأُ&nbsp;مِنَ&nbsp;الْقَدَمَيْنِ،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;وَلِكَيْ&nbsp;تَبْدَأُ&nbsp;هَذِهِ&nbsp;الرَّاحَةُ&nbsp;لابُدَّ&nbsp;مِنْ&nbsp;إِرَاحَةِ&nbsp;الْقَدَمِ&nbsp;نَفْسِهَا،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ثُمَّ&nbsp;لِبسِ&nbsp;الْحِذَاءِ&nbsp;الْمُنَاسِبِ&nbsp;الْوَاسِعِ&nbsp;للشُّعُورِ&nbsp;بِالرَّاحَةِ؛</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;وَالْعِنَايَةُ&nbsp;بِالْقَدَمِ&nbsp;تَبْدَأُ&nbsp;بِالأََظَافِرِ&nbsp;وَالْجِلْدِ،&nbsp;ثُمَّ&nbsp;اِخْتِيَارُ&nbsp;الْجَوْرَبِ،&nbsp;وَتَنْتَهِي&nbsp;بِالْحِذَاءِ،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;وَيَجِبُ&nbsp;أَنْ&nbsp;نُفَرِّقَ&nbsp;بَيْنَ&nbsp;الْعِنَايَةِ&nbsp;بِقَدَمِ&nbsp;الشَّخْصِ&nbsp;الْعَادِي&nbsp;وَبَيْنَ&nbsp;قَدَمِ&nbsp;الشَّخْصِ&nbsp;الرِّيَاضِيِّ.</h2><p style="direction: rtl;"></p>'],
            ['id' => 10, 'type' => 'text', 'title' => 'الرياضة', 'content' => '<h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;المكان..&nbsp;القصر&nbsp;الجمهوري&nbsp;بوسط&nbsp;البلد،&nbsp;والزمان..&nbsp;العاشرة&nbsp;والربع&nbsp;صباحا..</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;الحدث..&nbsp;استقبل&nbsp;الرئيس&nbsp;للاعبي&nbsp;الفريق&nbsp;أبطال&nbsp;الوطن&nbsp;لتكريمهم،</h2><h2 class="ql-align-center" style="direction: rtl;">وذلك&nbsp;بعد&nbsp;الفوز&nbsp;بكأس&nbsp;إفريقيا&nbsp;وبالمركز&nbsp;الثالث&nbsp;في&nbsp;كأس&nbsp;العالم&nbsp;للأندية&nbsp;في&nbsp;كرة&nbsp;القدم،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ومنحهم&nbsp;الرئيس&nbsp;جائزة&nbsp;الرياضة&nbsp;من&nbsp;الدرجة&nbsp;الأولي&nbsp;تكريما&nbsp;لأبناء&nbsp;الفريق.</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;استمر&nbsp;الاحتفال&nbsp;وتكريم&nbsp;الرئيس&nbsp;للأبطال&nbsp;45&nbsp;دقيقة،&nbsp;</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;وكانت&nbsp;السعادة&nbsp;والفرحة&nbsp;علي&nbsp;وجوه&nbsp;الجميع،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;وشعر&nbsp;كل&nbsp;اللاعبين&nbsp;باهتمام&nbsp;الرئيس&nbsp;بنجاحهم،</h2><h2 class="ql-align-center" style="direction: rtl;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;وقال&nbsp;الرئيس&nbsp;أنه&nbsp;سعيد&nbsp;بمقابلة&nbsp;الفريق&nbsp;بعد&nbsp;البطولة&nbsp;ويتمنى&nbsp;له&nbsp;دوام&nbsp;الفوز.</h2><h2 class="ql-align-center" style="direction: rtl;"></h2><p style="direction: rtl;"></p>'],
        ];
        DB::table('passages')->insert($passages);

        // --- 2. Questions ---
        $questions = [
            // Listening (Skill 1)
            ['id' => 1, 'skill_id' => 1, 'level_id' => 1, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/bJKxMGmaiy64o9Z59RVAnodRkSwu06qeuXPmMnGj.mp3'],
            ['id' => 2, 'skill_id' => 1, 'level_id' => 1, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/IMhzChQG78l2BY66rfMhbmyxzSmrG4VHsMdjGfgh.mp3'],
            ['id' => 3, 'skill_id' => 1, 'level_id' => 1, 'passage_id' => 1, 'type' => 'mcq', 'instructions' => 'مَتَى نَقُولُ هَذِهِ التَّحِيَّةُ؟', 'content' => '', 'image_path' => 'questions/images/MV5ft5YoVFRdfpZcY2ES9pbb5ZRrVFklQbJYams0.jpg', 'audio_path' => 'questions/audio/XZRwZpC1Kp7qBYSziBBwaxc9inK8BNBuoi4bbwXB.mp3'],
            ['id' => 4, 'skill_id' => 1, 'level_id' => 3, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/dcAq7rkJvgZLrKmViWUi2u60msOWerPfo6iljK2I.mp3'],
            ['id' => 5, 'skill_id' => 1, 'level_id' => 3, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/ntkxjb19gAD1dIL5cjEWKU5O5fYlJMX2BcJEAJLK.mp3'],
            ['id' => 6, 'skill_id' => 1, 'level_id' => 2, 'passage_id' => 2, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/CQHKveLXqSHXo4kX6bd8VguiT35ZuArS41VGBELP.mp3'],
            ['id' => 7, 'skill_id' => 1, 'level_id' => 2, 'passage_id' => 2, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/dzch0lojZxTF2lS9Hc9fwnldZq2c7FxfmS44hU0H.mp3'],
            ['id' => 8, 'skill_id' => 1, 'level_id' => 3, 'passage_id' => 3, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/n5I9Ka70QJyD1mFcbF2opP1I0bHp8m9fuy1wb0xn.mp3'],
            ['id' => 9, 'skill_id' => 1, 'level_id' => 3, 'passage_id' => 3, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/Seq8lX5CKcJaVuN3MhywXOksNYMp5QhHh1dERyUQ.mp3'],
            ['id' => 10, 'skill_id' => 1, 'level_id' => 4, 'type' => 'mcq', 'instructions' => null, 'content' => '', 'audio_path' => 'questions/audio/iyTmUvnHkwE9fI3Fg59QAaVR9OtBOm454G3VGMoz.mp3'],
            ['id' => 11, 'skill_id' => 1, 'level_id' => 4, 'type' => 'mcq', 'instructions' => null, 'content' => '', 'audio_path' => 'questions/audio/r9JlxHC6oEPGYNJCbcNm6DvMVwMyalRoJ8nGaviB.mp3'],
            ['id' => 12, 'skill_id' => 1, 'level_id' => 4, 'passage_id' => 4, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/AFjapeD2bz2yUGgBgYR1cXmjkU92Fa19pGFEqSpZ.mp3'],
            ['id' => 13, 'skill_id' => 1, 'level_id' => 5, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/yyHy6vpGGYufBFkQ5nJCV9eXOGNF9GtekrqdVeko.mp3'],
            ['id' => 14, 'skill_id' => 1, 'level_id' => 5, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/3qoThVC45341pTe1PFzzfVFvZlNoia93rNVFKDt1.mp3'],
            ['id' => 15, 'skill_id' => 1, 'level_id' => 6, 'passage_id' => 5, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/0o85HGgA4Vjz90gHeCoCxmPndx0D7Pa2Dk28hItB.mp3'],
            ['id' => 16, 'skill_id' => 1, 'level_id' => 6, 'passage_id' => 5, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/iVNgOFz6dCQ4MQqqUQPvchLkbC35KuDb3Kg3GkgC.mp3'],
            ['id' => 17, 'skill_id' => 1, 'level_id' => 5, 'passage_id' => 6, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/JZCg7YP8d3PbluhJYhPXgr6xAMf0CXd43K0KE7Ue.mp3'],
            ['id' => 18, 'skill_id' => 1, 'level_id' => 5, 'passage_id' => 6, 'type' => 'mcq', 'instructions' => 'مَاذَا تَفْهَمُ مِنَ الْحِوَارِ؟', 'content' => '', 'audio_path' => 'questions/audio/RyM0dMjJnotGzajeTdxyqzbgWfyVbMfGe1jqgAGB.mp3'],
            ['id' => 19, 'skill_id' => 1, 'level_id' => 6, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/fK1etafWMB6pkuKVwnRuA3htYadfklHn0jUIk49o.mp3'],
            ['id' => 20, 'skill_id' => 1, 'level_id' => 6, 'type' => 'mcq', 'instructions' => 'اسمتع الى الصوت تم اختر الاجابه الصحيحة', 'content' => '', 'audio_path' => 'questions/audio/VA50atTiZf6R1YSnmuYuCodF1z2JrDM2RVQV7ZcM.mp3'],
            
            // Reading (Skill 2)
            ['id' => 21, 'skill_id' => 2, 'level_id' => 10, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h2 class="ql-align-right">معنى&nbsp;كلمة&nbsp;(تَسْكُنُ)</h2>'],
            ['id' => 22, 'skill_id' => 2, 'level_id' => 10, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right"><span class="ql-font-serif">مضاد&nbsp;كلمة&nbsp;(نظيفة)</span></h1>'],
            ['id' => 23, 'skill_id' => 2, 'level_id' => 10, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">مضاد&nbsp;جملة&nbsp;(الشقة&nbsp;صغيرة)&nbsp;في&nbsp;النص</h1>'],
            ['id' => 24, 'skill_id' => 2, 'level_id' => 10, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">مع&nbsp;من&nbsp;تقيم&nbsp;ليلى؟</h1>'],
            ['id' => 25, 'skill_id' => 2, 'level_id' => 10, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">اختر&nbsp;عنوانا&nbsp;للنص</h1>'],
            ['id' => 26, 'skill_id' => 2, 'level_id' => 10, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">هل&nbsp;هذه&nbsp;شقة&nbsp;ليلى؟</h1>'],
            ['id' => 27, 'skill_id' => 2, 'level_id' => 11, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 class="ql-align-right">مضاد&nbsp;كلمة&nbsp;(كثيرا)</h2>'],
            ['id' => 28, 'skill_id' => 2, 'level_id' => 11, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 class="ql-align-right">معنى&nbsp;جملة&nbsp;(هو&nbsp;مجتهد&nbsp;دائما)&nbsp;في&nbsp;النص</h2>'],
            ['id' => 29, 'skill_id' => 2, 'level_id' => 11, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 class="ql-align-right">مضاد&nbsp;جملة&nbsp;(أحمد&nbsp;وخالد&nbsp;تعيسان)&nbsp;في&nbsp;النص</h2>'],
            ['id' => 30, 'skill_id' => 2, 'level_id' => 11, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h1 class="ql-align-right">&nbsp;:خالد</h1>'],
            ['id' => 31, 'skill_id' => 2, 'level_id' => 11, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">اختر&nbsp;عنوانا&nbsp;للنص:</h2>'],
            ['id' => 32, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<p></p>'],
            ['id' => 33, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => ''],
            ['id' => 34, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => ''],
            ['id' => 35, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => ''],
            ['id' => 36, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => ''],
            ['id' => 37, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => ''],
            ['id' => 38, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => ''],
            ['id' => 39, 'skill_id' => 2, 'level_id' => 12, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => ''],
            ['id' => 40, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">معنى&nbsp;كلمة&nbsp;(منح):</h2>'],
            ['id' => 41, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">مضاد&nbsp;كلمة&nbsp;(الفوز):</h2>'],
            ['id' => 42, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">مضاد&nbsp;جملة&nbsp;(استقبل&nbsp;الرئيس&nbsp;الفريق&nbsp;لتكريمهم)&nbsp;في&nbsp;النص:	</h2>'],
            ['id' => 43, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">يتناول&nbsp;النص&nbsp;السابق&nbsp;موضوع</h2>'],
            ['id' => 44, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">يبرز&nbsp;النص&nbsp;أهمية:</h2>'],
            ['id' => 45, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">استقبل&nbsp;الرئيس&nbsp;الفريق&nbsp;من&nbsp;أجل&nbsp;كل&nbsp;ما&nbsp;يأتي،&nbsp;ماعدا:</h2>'],
            ['id' => 46, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">أي&nbsp;الأفكار&nbsp;التالية&nbsp;وردت&nbsp;في&nbsp;النص&nbsp;أولا:	</h2>'],
            ['id' => 47, 'skill_id' => 2, 'level_id' => 13, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">شعر&nbsp;لاعبو&nbsp;الفريق:</h2>'],
        ];
        foreach ($questions as $q) {
            DB::table('questions')->insert($q);
        }

        // --- 3. Options ---
        // Mapping a few for the example, but including the bulk provided
        $options = [
            // Q1
            ['question_id' => 1, 'option_text' => 'عَادِلٌ يُقِيمُ فِي الْبَيتِ، وَمَرْيَمُ أَيْضًا.', 'is_correct' => 1],
            ['question_id' => 1, 'option_text' => 'جَلَسَ الطَّالِبُ فِي الْبَيتِ، وَتَحَدَّثَ مَعَ مَرْيَمَ.', 'is_correct' => 0],
            ['question_id' => 1, 'option_text' => 'عَادِلٌ يَسْكُنُ فِي الْبَيتِ وَلَيْسَ مَرْيَمُ.', 'is_correct' => 0],
            ['question_id' => 1, 'option_text' => 'يَجْلِسُ أَحْمَدُ فِي الْبَيتِ، وَكَذَلِكَ مَرْيَمُ.', 'is_correct' => 0],
            // Q2
            ['question_id' => 2, 'option_text' => 'هَؤُلاءِ الطُّلابُ ثَلاثَةٌ.', 'is_correct' => 1],
            ['question_id' => 2, 'option_text' => 'يَشْرَبُ أَحْمَدُ ثَلاثَ مَرَّاتٍ.', 'is_correct' => 0],
            ['question_id' => 2, 'option_text' => 'هَؤُلاءِ ثَلاثُ طَالِبَاتٍ.', 'is_correct' => 0],
            ['question_id' => 2, 'option_text' => 'عَدَدُ الطُّلابِ لَيْسَ ثَلاثَةً.', 'is_correct' => 0],
            // Q3
            ['question_id' => 3, 'option_text' => 'بَعْدَ الظُّهْرِ', 'is_correct' => 1],
            ['question_id' => 3, 'option_text' => 'فِي أَوَّلِ الْيَوْمِ', 'is_correct' => 0],
            ['question_id' => 3, 'option_text' => 'عِنْدَ النَّوْمِ', 'is_correct' => 0],
            ['question_id' => 3, 'option_text' => 'فِي أَيِّ وَقْتٍ', 'is_correct' => 0],
            // Q4
            ['question_id' => 4, 'option_text' => 'الإجابة الأولى (صحيحة)', 'is_correct' => 1],
            ['question_id' => 4, 'option_text' => 'الإجابة الثانية', 'is_correct' => 0],
            ['question_id' => 4, 'option_text' => 'الإجابة الثالثة', 'is_correct' => 0],
            ['question_id' => 4, 'option_text' => 'الإجابة الرابعة', 'is_correct' => 0],
            // Q5
            ['question_id' => 5, 'option_text' => 'الاختيار الأول (صحيح)', 'is_correct' => 1],
            ['question_id' => 5, 'option_text' => 'الاختيار الثاني', 'is_correct' => 0],
            ['question_id' => 5, 'option_text' => 'الاختيار الثالث', 'is_correct' => 0],
            ['question_id' => 5, 'option_text' => 'الاختيار الرابع', 'is_correct' => 0],
            // Q6
            ['question_id' => 6, 'option_text' => 'أَسْوَانُ جَوُّهَا جَمِيلٌ فِي الشِّتَاءِ.', 'is_correct' => 1],
            ['question_id' => 6, 'option_text' => 'أَسْوَانُ جَوُّهَا بَرْدٌ فِي الشِّتَاءِ.', 'is_correct' => 0],
            ['question_id' => 6, 'option_text' => 'الإسْكَنْدَرِيَّةُ جَوُّهَا حَرٌّ فِي الصَّيْفِ.', 'is_correct' => 0],
            ['question_id' => 6, 'option_text' => 'الإسْكَنْدَرِيَّةُ جَوُّهَا بَرْدٌ فِي الرَّبِيعِ.', 'is_correct' => 0],
            // Q21
            ['question_id' => 21, 'option_text' => 'تُقيم', 'is_correct' => 1],
            ['question_id' => 21, 'option_text' => 'تجلس', 'is_correct' => 0],
            ['question_id' => 21, 'option_text' => 'تنام', 'is_correct' => 0],
            ['question_id' => 21, 'option_text' => 'تتكلم', 'is_correct' => 0],
            // Q22
            ['question_id' => 22, 'option_text' => 'متسخة', 'is_correct' => 1],
            ['question_id' => 22, 'option_text' => 'جميلة', 'is_correct' => 0],
            ['question_id' => 22, 'option_text' => 'ضيقة', 'is_correct' => 0],
            ['question_id' => 22, 'option_text' => 'قبيحة', 'is_correct' => 0],
            // Q27
            ['question_id' => 27, 'option_text' => 'قليلا', 'is_correct' => 1],
            ['question_id' => 27, 'option_text' => 'قصيرا', 'is_correct' => 0],
            ['question_id' => 27, 'option_text' => 'ضيقا', 'is_correct' => 0],
            ['question_id' => 27, 'option_text' => 'ضعيفا', 'is_correct' => 0],
            // Q40
            ['question_id' => 40, 'option_text' => 'أعطى', 'is_correct' => 1],
            ['question_id' => 40, 'option_text' => 'أخذ', 'is_correct' => 0],
            ['question_id' => 40, 'option_text' => 'قابل', 'is_correct' => 0],
            ['question_id' => 40, 'option_text' => 'كرَّم', 'is_correct' => 0],
        ];

        foreach ($options as $opt) {
            DB::table('question_options')->insert($opt);
        }

        // --- 4. Exam Questions Link ---
        $examId = 1;
        for ($i = 1; $i <= 47; $i++) {
            DB::table('exam_questions')->insert([
                'exam_id' => $examId,
                'question_id' => $i,
                'order' => $i,
                'is_random' => 0,
            ]);
        }
    }
}
