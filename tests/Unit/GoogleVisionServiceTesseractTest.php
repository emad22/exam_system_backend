<?php

namespace Tests\Unit;

use App\Services\GoogleVisionService;
use Tests\TestCase;

class GoogleVisionServiceTesseractTest extends TestCase
{
    public function test_extract_raw_text_prefers_tesseract_when_available(): void
{
        $scriptPath = tempnam(sys_get_temp_dir(), 'tess_stub_');
        $this->assertNotFalse($scriptPath, 'Unable to create temporary script path');

        if (DIRECTORY_SEPARATOR === '\\') {
            unlink($scriptPath);
            $scriptPath .= '.bat';
            $scriptContents = "@echo off\r\n"
                . "if \"%1\"==\"--version\" echo tesseract 5.0.0 & exit /b 0\r\n"
                . "set output=%~2\r\n"
                . "echo ٢٩٨٠١٠١٠١٠٠١٢٣ > \"%output%.txt\"\r\n";
        } else {
            unlink($scriptPath);
            $scriptPath .= '.sh';
            $scriptContents = "#!/bin/sh\n"
                . "if [ \"$1\" = \"--version\" ]; then echo 'tesseract 5.0.0'; exit 0; fi\n"
                . "output=\"$2\"\n"
                . "echo '٢٩٨٠١٠١٠١٠٠١٢٣' > \"${output}.txt\"\n";
        }

        file_put_contents($scriptPath, $scriptContents);
        if (DIRECTORY_SEPARATOR !== '\\') {
            chmod($scriptPath, 0755);
        }

        putenv('TESSERACT_BIN=' . $scriptPath);
        putenv('TESSERACT_LANG=ara');

        $service = new GoogleVisionService();
        $imagePath = tempnam(sys_get_temp_dir(), 'ocr_image_');
        file_put_contents($imagePath, 'fake-image-bytes');
        $base64 = base64_encode(file_get_contents($imagePath));

        $result = $service->extractRawText('data:image/png;base64,' . $base64);

        $this->assertSame('٢٩٨٠١٠١٠١٠٠١٢٣', $result);

        unlink($imagePath);
        unlink($scriptPath);
        putenv('TESSERACT_BIN');
        putenv('TESSERACT_LANG');
    }
}
