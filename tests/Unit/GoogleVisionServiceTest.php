<?php

namespace Tests\Unit;

use App\Services\GoogleVisionService;
use Tests\TestCase;

class GoogleVisionServiceTest extends TestCase
{
    public function test_extracts_national_id_from_arabic_indic_digits(): void
    {
        $service = new GoogleVisionService();

        $text = "الرقم القومي\n٢٩٨٠١٠١٠١٠٠١٢٣";

        $this->assertSame(
            '29801010100123',
            $service->extractEgyptianNationalId($text)
        );
    }
}