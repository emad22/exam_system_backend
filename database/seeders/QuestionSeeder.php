<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Passage;
use App\Models\Exam;
use Illuminate\Database\Seeder;
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
            ['id' => 21, 'skill_id' => 2, 'level_id' => 1, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h2 class="ql-align-right">معنى&nbsp;كلمة&nbsp;(تَسْكُنُ)</h2>'],
            ['id' => 22, 'skill_id' => 2, 'level_id' => 1, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right"><span class="ql-font-serif">مضاد&nbsp;كلمة&nbsp;(نظيفة)</span></h1>'],
            ['id' => 23, 'skill_id' => 2, 'level_id' => 1, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">مضاد&nbsp;جملة&nbsp;(الشقة&nbsp;صغيرة)&nbsp;في&nbsp;النص</h1>'],
            ['id' => 24, 'skill_id' => 2, 'level_id' => 1, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">مع&nbsp;من&nbsp;تقيم&nbsp;ليلى؟</h1>'],
            ['id' => 25, 'skill_id' => 2, 'level_id' => 1, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">اختر&nbsp;عنوانا&nbsp;للنص</h1>'],
            ['id' => 26, 'skill_id' => 2, 'level_id' => 1, 'passage_id' => 7, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => '<h1 class="ql-align-right">هل&nbsp;هذه&nbsp;شقة&nbsp;ليلى؟</h1>'],
            ['id' => 27, 'skill_id' => 2, 'level_id' => 2, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 class="ql-align-right">مضاد&nbsp;كلمة&nbsp;(كثيرا)</h2>'],
            ['id' => 28, 'skill_id' => 2, 'level_id' => 2, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 class="ql-align-right">معنى&nbsp;جملة&nbsp;(هو&nbsp;مجتهد&nbsp;دائما)&nbsp;في&nbsp;النص</h2>'],
            ['id' => 29, 'skill_id' => 2, 'level_id' => 2, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 class="ql-align-right">مضاد&nbsp;جملة&nbsp;(أحمد&nbsp;وخالد&nbsp;تعيسان)&nbsp;في&nbsp;النص</h2>'],
            ['id' => 30, 'skill_id' => 2, 'level_id' => 2, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h1 class="ql-align-right">&nbsp;:خالد</h1>'],
            ['id' => 31, 'skill_id' => 2, 'level_id' => 2, 'passage_id' => 8, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">اختر&nbsp;عنوانا&nbsp;للنص:</h2>'],
            ['id' => 32, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'أين تبدأ راحة الجسم؟'],
            ['id' => 33, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'كيف تبدأ العناية بالقدم؟'],
            ['id' => 34, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'ما هو الحذاء المناسب؟'],
            ['id' => 35, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'ما الهدف من لبس الحذاء الواسع؟'],
            ['id' => 36, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'العناية بالقدم تنتهي بـ:'],
            ['id' => 37, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'يجب أن نفرق بين عناية الشخص العادي و:'],
            ['id' => 38, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'راحة الجسم مهمة لـ:'],
            ['id' => 39, 'skill_id' => 2, 'level_id' => 3, 'passage_id' => 9, 'type' => 'mcq', 'instructions' => 'اختر الاجابه الصحيحة', 'content' => 'اختر عنواناً مناسباً:'],
            ['id' => 40, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">معنى&nbsp;كلمة&nbsp;(منح):</h2>'],
            ['id' => 41, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">مضاد&nbsp;كلمة&nbsp;(الفوز):</h2>'],
            ['id' => 42, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">مضاد&nbsp;جملة&nbsp;(استقبل&nbsp;الرئيس&nbsp;الفريق&nbsp;لتكريمهم)&nbsp;في&nbsp;النص:	</h2>'],
            ['id' => 43, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">يتناول&nbsp;النص&nbsp;السابق&nbsp;موضوع</h2>'],
            ['id' => 44, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">يبرز&nbsp;النص&nbsp;أهمية:</h2>'],
            ['id' => 45, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">استقبل&nbsp;الرئيس&nbsp;الفريق&nbsp;من&nbsp;أجل&nbsp;كل&nbsp;ما&nbsp;يأتي،&nbsp;ماعدا:</h2>'],
            ['id' => 46, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">أي&nbsp;الأفكار&nbsp;التالية&nbsp;وردت&nbsp;في&nbsp;النص&nbsp;أولا:	</h2>'],
            ['id' => 47, 'skill_id' => 2, 'level_id' => 4, 'passage_id' => 10, 'type' => 'mcq', 'instructions' => null, 'content' => '<h2 style="direction: rtl;" class="ql-align-right">شعر&nbsp;لاعبو&nbsp;الفريق:</h2>'],
        ];
        foreach ($questions as $q) {
            // Dynamically find the correct level_id based on skill_id and level_number
            $level = Level::where('skill_id', $q['skill_id'])
                          ->where('level_number', $q['level_id']) // in the array, level_id was treated as level_number
                          ->first();
            
            if ($level) {
                $q['level_id'] = $level->id;
                $exam = Exam::first();
                if ($exam) {
                    $q['exam_id'] = $exam->id;
                }
                DB::table('questions')->insert($q);
            }
        }

        // --- 3. Options ---
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
            ['question_id' => 4, 'option_text' => 'أَنَا أُحِبُّ اللغة العربية.', 'is_correct' => 1],
            ['question_id' => 4, 'option_text' => 'الجو بارد اليوم.', 'is_correct' => 0],
            ['question_id' => 4, 'option_text' => 'أكلت التفاح في الصباح.', 'is_correct' => 0],
            ['question_id' => 4, 'option_text' => 'أذهب إلى المدرسة.', 'is_correct' => 0],
            // Q5
            ['question_id' => 5, 'option_text' => 'السماء صافية والجو مشمس.', 'is_correct' => 1],
            ['question_id' => 5, 'option_text' => 'نحن نلعب في الحديقة.', 'is_correct' => 0],
            ['question_id' => 5, 'option_text' => 'الكتاب مفيد جداً.', 'is_correct' => 0],
            ['question_id' => 5, 'option_text' => 'شربت الماء البارد.', 'is_correct' => 0],
            // Q6
            ['question_id' => 6, 'option_text' => 'لَكِنَّ الْجَوَّ يَكُونُ جَمِيلًا.', 'is_correct' => 1],
            ['question_id' => 6, 'option_text' => 'لَكِنَّ الْجَوَّ يَكُونُ بَارِدًا.', 'is_correct' => 0],
            ['question_id' => 6, 'option_text' => 'الْجَوُّ يَكُونُ حارًا.', 'is_correct' => 0],
            ['question_id' => 6, 'option_text' => 'الْجَوُّ يَكُونُ بَرًّا فِي الرَّبِيعِ.', 'is_correct' => 0],
            // Q7
            ['question_id' => 7, 'option_text' => 'لَكِنَّ الْجَوَّ يَكُونُ جَمِيلًا فِي الْجَبَلِ.', 'is_correct' => 1],
            ['question_id' => 7, 'option_text' => 'لَكِنَّ الْجَوَّ يَكُونُ جَمِيلًا فِي السَّمَاءِ.', 'is_correct' => 0],
            ['question_id' => 7, 'option_text' => 'لَكِنَّ الْجَوَّ يَكُونُ جَمِيلًا فِي الْبَيْتِ.', 'is_correct' => 0],
            ['question_id' => 7, 'option_text' => 'لَكِنَّ الْجَوَّ يَكُونُ جَمِيلًا فِي الشَّارِعِ.', 'is_correct' => 0],
            // Q8
            ['question_id' => 8, 'option_text' => 'لَكِنَّ الْبَحْرَ يَكُونُ بَرًّا فِي الشِّتَاءِ.', 'is_correct' => 1],
            ['question_id' => 8, 'option_text' => 'لَكِنَّ الْبَحْرَ يَكُونُ هَادِئًا فِي الشِّتَاءِ.', 'is_correct' => 0],
            ['question_id' => 8, 'option_text' => 'الْبَحْرَ يَكُونُ بَرًّا فِي الصَّيْفِ.', 'is_correct' => 0],
            ['question_id' => 8, 'option_text' => 'الْبَحْرَ يَكُونُ بَرًّا فِي الرَّبِيعِ.', 'is_correct' => 0],
            // Q9
            ['question_id' => 9, 'option_text' => 'لَكِنَّ الْجَوَّ لَطِيفٌ فِي الشِّتَاءِ.', 'is_correct' => 1],
            ['question_id' => 9, 'option_text' => 'لَكِنَّ الْجَوَّ لَطِيفٌ فِي الصَّيْفِ.', 'is_correct' => 0],
            ['question_id' => 9, 'option_text' => 'الْجَوَّ يَكُونُ بَرْدٌ فِي الشِّتَاءِ.', 'is_correct' => 0],
            ['question_id' => 9, 'option_text' => 'الْجَوَّ يَكُونُ حَرٌّ فِي الرَّبِيعِ.', 'is_correct' => 0],
            // Q10
            ['question_id' => 10, 'option_text' => 'تَكُونُ جَمِيلَةً فِي هَذَا الوَقْتِ.', 'is_correct' => 1],
            ['question_id' => 10, 'option_text' => 'لَكِنَّهَا تَكُونُ مُزْعِجَةً بِسَبَبِ الزِّحَامِ.', 'is_correct' => 0],
            ['question_id' => 10, 'option_text' => 'الجوُّ حَارٌّ جِدًّا هُنَاكَ.', 'is_correct' => 0],
            ['question_id' => 10, 'option_text' => 'تَسْقُطُ الثُّلوجُ كُلَّ يَوْمٍ.', 'is_correct' => 0],
            // Q11 
            ['question_id' => 11, 'option_text' => 'يَذْهَبُ إِلَى المَدْرَسَةِ مَاشِيًا.', 'is_correct' => 1],
            ['question_id' => 11, 'option_text' => 'يَرْكَبُ الحَافِلَةَ الكَبِيرَةَ.', 'is_correct' => 0],
            ['question_id' => 11, 'option_text' => 'يَبْقَى فِي البَيْتِ لِلدِّرَاسَةِ.', 'is_correct' => 0],
            ['question_id' => 11, 'option_text' => 'يَسْتَعِيرُ بَعْضَ الكُتُبِ.', 'is_correct' => 0],
            // Q12
            ['question_id' => 12, 'option_text' => 'يُفَضِّلُ شُرْبَ القَهْوَةِ السَّاخِنَةِ.', 'is_correct' => 1],
            ['question_id' => 12, 'option_text' => 'يُحِبُّ العَصِيرَ البَارِدَ.', 'is_correct' => 0],
            ['question_id' => 12, 'option_text' => 'يَشْرَبُ الشَّايَ مَعَ الحَلِيبِ.', 'is_correct' => 0],
            ['question_id' => 12, 'option_text' => 'يَطْلُبُ مَاءً نَقِيًّا فَقَطْ.', 'is_correct' => 0],
            // Q13
            ['question_id' => 13, 'option_text' => 'يُسَافِرُ بِالقِطَارِ السَّرِيعِ.', 'is_correct' => 1],
            ['question_id' => 13, 'option_text' => 'يَحْجِزُ تَذْكِرَةً لِلطَّائِرَةِ.', 'is_correct' => 0],
            ['question_id' => 13, 'option_text' => 'يَقُودُ سَيَّارَتَهُ الخَاصَّةَ.', 'is_correct' => 0],
            ['question_id' => 13, 'option_text' => 'يَنْتَظِرُ رُكُوبَ السَّفِينَةِ.', 'is_correct' => 0],
            // Q14
            ['question_id' => 14, 'option_text' => 'يَشْتَرِي بَعْضَ الفَوَاكِهِ الطَّازَجَةِ.', 'is_correct' => 1],
            ['question_id' => 14, 'option_text' => 'يَبْحَثُ عَنِ المَلابِسِ الشِّتَوِيَّةِ.', 'is_correct' => 0],
            ['question_id' => 14, 'option_text' => 'يَدْفَعُ ثَمَنَ الحَقِيبَةِ.', 'is_correct' => 0],
            ['question_id' => 14, 'option_text' => 'يَسْأَلُ عَنْ مَكَانِ السُّوقِ.', 'is_correct' => 0],
            // Q15
            ['question_id' => 15, 'option_text' => 'يَعْمَلُ فِي المَكْتَبِ مُنْذُ الصَّبَاحِ.', 'is_correct' => 1],
            ['question_id' => 15, 'option_text' => 'يُقَابِلُ المُدِيرَ فِي الاجْتِمَاعِ.', 'is_correct' => 0],
            ['question_id' => 15, 'option_text' => 'يَكْتُبُ تَقْرِيرًا طَوِيلًا.', 'is_correct' => 0],
            ['question_id' => 15, 'option_text' => 'يَتَحَدَّثُ مَعَ زُمَلائِهِ.', 'is_correct' => 0],
            // Q16
            ['question_id' => 16, 'option_text' => 'يَزُورُ جَدَّهُ فِي القَرْيَةِ.', 'is_correct' => 1],
            ['question_id' => 16, 'option_text' => 'يَقْضِي العُطْلَةَ فِي المَدِينَةِ.', 'is_correct' => 0],
            ['question_id' => 16, 'option_text' => 'يَذْهَبُ لِلصَّيْدِ مَعَ أَبِيهِ.', 'is_correct' => 0],
            ['question_id' => 16, 'option_text' => 'يَلْعَبُ كُرَةَ القَدَمِ هُنَاكَ.', 'is_correct' => 0],
            // Q17
            ['question_id' => 17, 'option_text' => 'يَسْتَمِعُ إِلَى الأَخْبَارِ المَحَلِّيَّةِ.', 'is_correct' => 1],
            ['question_id' => 17, 'option_text' => 'يُشَاهِدُ فِلْمًا وَثَائِقِيًّا.', 'is_correct' => 0],
            ['question_id' => 17, 'option_text' => 'يَقْرَأُ صَحِيفَةً يَوْمِيَّةً.', 'is_correct' => 0],
            ['question_id' => 17, 'option_text' => 'يَتَصَفَّحُ المَوَاقِعَ الإِلكتْرُونِيَّةَ.', 'is_correct' => 0],
            // Q18
            ['question_id' => 18, 'option_text' => 'يَسْكُنُ فِي شَقَّةٍ وَاسِعَةٍ.', 'is_correct' => 1],
            ['question_id' => 18, 'option_text' => 'يَبْنِي بَيْتًا صَغِيرًا.', 'is_correct' => 0],
            ['question_id' => 18, 'option_text' => 'يَبْحَثُ عَنْ سَكَنٍ قَرِيبٍ.', 'is_correct' => 0],
            ['question_id' => 18, 'option_text' => 'يَنْتَقِلُ إِلَى حَيٍّ جَدِيدٍ.', 'is_correct' => 0],
            // Q19
            ['question_id' => 19, 'option_text' => 'يُذَاكِرُ دُرُوسَهُ بِجِدٍّ.', 'is_correct' => 1],
            ['question_id' => 19, 'option_text' => 'يَنْجَحُ فِي الامْتِحَانِ النِّهَائِيِّ.', 'is_correct' => 0],
            ['question_id' => 19, 'option_text' => 'يَسْأَلُ المُعَلِّمَ عَنِ السُّؤَالِ.', 'is_correct' => 0],
            ['question_id' => 19, 'option_text' => 'يَكْتُبُ الوَاجِبَ المَنْزِلِيَّ.', 'is_correct' => 0],
            // Q20
            ['question_id' => 20, 'option_text' => 'يَتَنَاوَلُ الغَدَاءَ فِي المَطْعَمِ.', 'is_correct' => 1],
            ['question_id' => 20, 'option_text' => 'يَطْبُخُ طَعَامًا لَذِيذًا.', 'is_correct' => 0],
            ['question_id' => 20, 'option_text' => 'يَغْسِلُ الأَطْبَاقَ بَعْدَ الأَكْلِ.', 'is_correct' => 0],
            ['question_id' => 20, 'option_text' => 'يَطْلُبُ قَائِمَةَ الطَّعَامِ.', 'is_correct' => 0],
            // Q21 (تَسْكُنُ)
            ['question_id' => 21, 'option_text' => 'تُقيم', 'is_correct' => 1],
            ['question_id' => 21, 'option_text' => 'تجلس', 'is_correct' => 0],
            ['question_id' => 21, 'option_text' => 'تنام', 'is_correct' => 0],
            ['question_id' => 21, 'option_text' => 'تتكلم', 'is_correct' => 0],
            // Q22 (نظيفة)
            ['question_id' => 22, 'option_text' => 'متسخة', 'is_correct' => 1],
            ['question_id' => 22, 'option_text' => 'جميلة', 'is_correct' => 0],
            ['question_id' => 22, 'option_text' => 'واسعة', 'is_correct' => 0],
            ['question_id' => 22, 'option_text' => 'مرتبة', 'is_correct' => 0],
            // Q23 (الشقة صغيرة)
            ['question_id' => 23, 'option_text' => 'الشقة كبيرة', 'is_correct' => 1],
            ['question_id' => 23, 'option_text' => 'الشقة نظيفة', 'is_correct' => 0],
            ['question_id' => 23, 'option_text' => 'الشقة بعيدة', 'is_correct' => 0],
            ['question_id' => 23, 'option_text' => 'الشقة جديدة', 'is_correct' => 0],
            // Q24 (مع من تقيم ليلى؟)
            ['question_id' => 24, 'option_text' => 'أحمد', 'is_correct' => 1],
            ['question_id' => 24, 'option_text' => 'خالد', 'is_correct' => 0],
            ['question_id' => 24, 'option_text' => 'بمفردها', 'is_correct' => 0],
            ['question_id' => 24, 'option_text' => 'صديقتها', 'is_correct' => 0],
            // Q25 (عنوان النص)
            ['question_id' => 25, 'option_text' => 'شقة أحمد', 'is_correct' => 1],
            ['question_id' => 25, 'option_text' => 'عمل ليلى', 'is_correct' => 0],
            ['question_id' => 25, 'option_text' => 'مدرسة أحمد', 'is_correct' => 0],
            ['question_id' => 25, 'option_text' => 'السوق القريب', 'is_correct' => 0],
            // Q26 (هل هذه شقة ليلى؟)
            ['question_id' => 26, 'option_text' => 'لا، هي شقة أحمد', 'is_correct' => 1],
            ['question_id' => 26, 'option_text' => 'نعم، هي شقتها', 'is_correct' => 0],
            ['question_id' => 26, 'option_text' => 'ربما', 'is_correct' => 0],
            ['question_id' => 26, 'option_text' => 'لا أعرف', 'is_correct' => 0],
            // Q27 (كثيرا)
            ['question_id' => 27, 'option_text' => 'قليلا', 'is_correct' => 1],
            ['question_id' => 27, 'option_text' => 'دائماً', 'is_correct' => 0],
            ['question_id' => 27, 'option_text' => 'أحياناً', 'is_correct' => 0],
            ['question_id' => 27, 'option_text' => 'نادراً', 'is_correct' => 0],
            // Q28 (مجتهد دائما)
            ['question_id' => 28, 'option_text' => 'يعمل كثيراً', 'is_correct' => 1],
            ['question_id' => 28, 'option_text' => 'يحب اللعب', 'is_correct' => 0],
            ['question_id' => 28, 'option_text' => 'ينام كثيراً', 'is_correct' => 0],
            ['question_id' => 28, 'option_text' => 'يساعد الناس', 'is_correct' => 0],
            // Q29 (أحمد وخالد تعيسان)
            ['question_id' => 29, 'option_text' => 'أحمد سعيد وخالد أيضاً', 'is_correct' => 1],
            ['question_id' => 29, 'option_text' => 'أحمد غني وخالد فقير', 'is_correct' => 0],
            ['question_id' => 29, 'option_text' => 'أحمد يعمل وخالد يدرس', 'is_correct' => 0],
            ['question_id' => 29, 'option_text' => 'أحمد وخالد حزينان', 'is_correct' => 0],
            // Q30 (خالد:)
            ['question_id' => 30, 'option_text' => 'رجل غني يساعد صديقه', 'is_correct' => 1],
            ['question_id' => 30, 'option_text' => 'رجل فقير يعمل كثيراً', 'is_correct' => 0],
            ['question_id' => 30, 'option_text' => 'طالب في المدرسة', 'is_correct' => 0],
            ['question_id' => 30, 'option_text' => 'مدرس لغة عربية', 'is_correct' => 0],
            // Q31 (عنوان النص)
            ['question_id' => 31, 'option_text' => 'أحمد وخالد', 'is_correct' => 1],
            ['question_id' => 31, 'option_text' => 'العمل الشاق', 'is_correct' => 0],
            ['question_id' => 31, 'option_text' => 'المدرسة القديمة', 'is_correct' => 0],
            ['question_id' => 31, 'option_text' => 'أهمية المال', 'is_correct' => 0],
            // Q32
            ['question_id' => 32, 'option_text' => 'مِنَ القَدَمَيْنِ', 'is_correct' => 1],
            ['question_id' => 32, 'option_text' => 'مِنَ الرَّأْسِ', 'is_correct' => 0],
            ['question_id' => 32, 'option_text' => 'مِنَ اليَدَيْنِ', 'is_correct' => 0],
            ['question_id' => 32, 'option_text' => 'مِنَ الظَّهْرِ', 'is_correct' => 0],
            // Q33
            ['question_id' => 33, 'option_text' => 'بِالأَظَافِرِ وَالْجِلْدِ', 'is_correct' => 1],
            ['question_id' => 33, 'option_text' => 'بِالماءِ وَالصَّابُونِ', 'is_correct' => 0],
            ['question_id' => 33, 'option_text' => 'بِالمَشْيِ كَثِيرًا', 'is_correct' => 0],
            ['question_id' => 33, 'option_text' => 'بِالرَّاحَةِ التَّامَّةِ', 'is_correct' => 0],
            // Q34
            ['question_id' => 34, 'option_text' => 'الوَاسِعُ', 'is_correct' => 1],
            ['question_id' => 34, 'option_text' => 'الضَّيِّقُ', 'is_correct' => 0],
            ['question_id' => 34, 'option_text' => 'الجَدِيدُ', 'is_correct' => 0],
            ['question_id' => 34, 'option_text' => 'الغَالِي', 'is_correct' => 0],
            // Q35
            ['question_id' => 35, 'option_text' => 'لِلشُّعُورِ بِالرَّاحَةِ', 'is_correct' => 1],
            ['question_id' => 35, 'option_text' => 'لِلظُّهُورِ بِمَظْهَرٍ جَيِّدٍ', 'is_correct' => 0],
            ['question_id' => 35, 'option_text' => 'لِالسَّيْرِ بِسُرْعَةٍ', 'is_correct' => 0],
            ['question_id' => 35, 'option_text' => 'لِتَوْفِيرِ المَالِ', 'is_correct' => 0],
            // Q36
            ['question_id' => 36, 'option_text' => 'بِالحِذَاءِ', 'is_correct' => 1],
            ['question_id' => 36, 'option_text' => 'بِالجَوْرَبِ', 'is_correct' => 0],
            ['question_id' => 36, 'option_text' => 'بِالجِلْدِ', 'is_correct' => 0],
            ['question_id' => 36, 'option_text' => 'بِالعِلاجِ', 'is_correct' => 0],
            // Q37
            ['question_id' => 37, 'option_text' => 'الشَّخْصِ الرِّيَاضِيِّ', 'is_correct' => 1],
            ['question_id' => 37, 'option_text' => 'الشَّخْصِ المَرِيضِ', 'is_correct' => 0],
            ['question_id' => 37, 'option_text' => 'الشَّخْصِ الكَبِيرِ', 'is_correct' => 0],
            ['question_id' => 37, 'option_text' => 'الشَّخْصِ النَّائِمِ', 'is_correct' => 0],
            // Q38
            ['question_id' => 38, 'option_text' => 'لِجَمِيعِ الأَشْخَاصِ', 'is_correct' => 1],
            ['question_id' => 38, 'option_text' => 'لِلرِّيَاضِيِّينَ فَقَطْ', 'is_correct' => 0],
            ['question_id' => 38, 'option_text' => 'لِلأَطْفَالِ الصِّغَارِ', 'is_correct' => 0],
            ['question_id' => 38, 'option_text' => 'لِلعُمَّالِ فَقَطْ', 'is_correct' => 0],
            // Q39
            ['question_id' => 39, 'option_text' => 'صِحَّةُ القَدَمَيْنِ وَرَاحَةُ الجِسْمِ', 'is_correct' => 1],
            ['question_id' => 39, 'option_text' => 'أَهَمِّيَّةُ الرِّيَاضَةِ البَدَنِيَّةِ', 'is_correct' => 0],
            ['question_id' => 39, 'option_text' => 'كَيْفِيَّةُ اِخْتِيَارِ المَلابِسِ', 'is_correct' => 0],
            ['question_id' => 39, 'option_text' => 'أَنْوَاعُ الأَحْذِيَةِ الحَدِيثَةِ', 'is_correct' => 0],
            // Q40 (منح)
            ['question_id' => 40, 'option_text' => 'أعطى', 'is_correct' => 1],
            ['question_id' => 40, 'option_text' => 'أخذ', 'is_correct' => 0],
            ['question_id' => 40, 'option_text' => 'قابل', 'is_correct' => 0],
            ['question_id' => 40, 'option_text' => 'كرَّم', 'is_correct' => 0],
            // Q41 (الفوز)
            ['question_id' => 41, 'option_text' => 'الخسارة', 'is_correct' => 1],
            ['question_id' => 41, 'option_text' => 'الانتصار', 'is_correct' => 0],
            ['question_id' => 41, 'option_text' => 'التكريم', 'is_correct' => 0],
            ['question_id' => 41, 'option_text' => 'الاحتفال', 'is_correct' => 0],
            // Q42
            ['question_id' => 42, 'option_text' => 'رفض الرئيس استقبال الفريق', 'is_correct' => 1],
            ['question_id' => 42, 'option_text' => 'احتفل الفريق وحده', 'is_correct' => 0],
            ['question_id' => 42, 'option_text' => 'خسر الفريق البطولة', 'is_correct' => 0],
            ['question_id' => 42, 'option_text' => 'لم يهتم أحد بالفريق', 'is_correct' => 0],
            // Q43
            ['question_id' => 43, 'option_text' => 'تكريم رياضي في القصر الجمهوري', 'is_correct' => 1],
            ['question_id' => 43, 'option_text' => 'مباراة كرة قدم هامة', 'is_correct' => 0],
            ['question_id' => 43, 'option_text' => 'زيارة سياحية لأبطال الوطن', 'is_correct' => 0],
            ['question_id' => 43, 'option_text' => 'تدريبات الفريق الصباحية', 'is_correct' => 0],
            // Q44
            ['question_id' => 44, 'option_text' => 'تقدير الدولة للأبطال', 'is_correct' => 1],
            ['question_id' => 44, 'option_text' => 'أهمية الوقت والالتزام', 'is_correct' => 0],
            ['question_id' => 44, 'option_text' => 'جمال القصر الجمهوري', 'is_correct' => 0],
            ['question_id' => 44, 'option_text' => 'قوانين لعبة كرة القدم', 'is_correct' => 0],
            // Q45
            ['question_id' => 45, 'option_text' => 'منحهم قسطاً من الراحة', 'is_correct' => 1],
            ['question_id' => 45, 'option_text' => 'تكريمهم بالفوز بالكأس', 'is_correct' => 0],
            ['question_id' => 45, 'option_text' => 'منحهم جائزة الرياضة', 'is_correct' => 0],
            ['question_id' => 45, 'option_text' => 'التعبير عن السعادة بمقابلتهم', 'is_correct' => 0],
            // Q46
            ['question_id' => 46, 'option_text' => 'تحديد مكان وزمان اللقاء', 'is_correct' => 1],
            ['question_id' => 46, 'option_text' => 'منح الجوائز للاعبين', 'is_correct' => 0],
            ['question_id' => 46, 'option_text' => 'كلمة الرئيس للفريق', 'is_correct' => 0],
            ['question_id' => 46, 'option_text' => 'وصف فرحة اللاعبين', 'is_correct' => 0],
            // Q47
            ['question_id' => 47, 'option_text' => 'بالفخر والاهتمام من الرئيس', 'is_correct' => 1],
            ['question_id' => 47, 'option_text' => 'بالتعب بعد البطولة', 'is_correct' => 0],
            ['question_id' => 47, 'option_text' => 'بالقلق من المستقبل', 'is_correct' => 0],
            ['question_id' => 47, 'option_text' => 'بالرغبة في العودة للمنزل', 'is_correct' => 0],
        ];

        foreach ($options as $opt) {
            DB::table('question_options')->insert($opt);
        }
    }
}
