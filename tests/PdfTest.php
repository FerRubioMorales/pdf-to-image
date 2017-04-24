<?php

namespace Bnb\PdfToImage\Test;

use Bnb\PdfToImage\Exceptions\InvalidFormat;
use Bnb\PdfToImage\Exceptions\PageDoesNotExist;
use Bnb\PdfToImage\Exceptions\PdfDoesNotExist;
use Bnb\PdfToImage\Pdf;

class PdfTest extends \PHPUnit_Framework_TestCase
{

    protected $testFile;

    protected $outputDir;

    protected $outputFile;

    protected $testUrl = 'https://github.com/bnbwebexpertise/pdf-to-image/raw/master/tests/files/multipage-test.pdf';


    public function setUp()
    {
        parent::setUp();

        $this->outputDir = __DIR__ . '/files';
        $this->testFile = $this->outputDir . '/test.pdf';
        $this->outputFile = $this->outputDir . '/test.png';
        $this->multipageTestFile = $this->outputDir . '/multipage-test.pdf';
    }


    public function tearDown()
    {
        if (file_exists($this->outputFile)) {
            @unlink($this->outputFile);
        }
    }


    /** @test */
    public function it_will_throw_an_exception_when_try_to_convert_a_non_existing_file()
    {
        $this->setExpectedException(PdfDoesNotExist::class);

        new Pdf('pdfdoesnotexists.pdf');
    }


    /** @test */
    public function it_will_throw_an_exception_when_try_to_convert_to_an_invalid_file_type()
    {
        $this->setExpectedException(InvalidFormat::class);

        (new Pdf($this->testFile))->setOutputFormat('bla');
    }


    /** @test */
    public function it_will_throw_an_exception_when_passed_an_invalid_page()
    {
        $this->setExpectedException(PageDoesNotExist::class);

        (new Pdf($this->testFile))->setPage(5);
    }


    /** @test */
    public function it_will_correctly_return_the_number_of_pages_in_pdf_file()
    {
        $pdf = new Pdf($this->multipageTestFile);

        $this->assertTrue($pdf->getNumberOfPages() === 3);
    }


    /** @test */
    public function it_will_accept_a_custom_specified_resolution()
    {
        $pdf = new Pdf($this->testFile);

        $pdf->setResolution(72);

        $image = $pdf->getImageData('test.jpg')->getImageResolution();

        $this->assertEquals($image['x'], 72);
        $this->assertEquals($image['y'], 72);
        $this->assertNotEquals($image['x'], 144);
        $this->assertNotEquals($image['y'], 144);
    }


    /** @test */
    public function it_will_convert_a_specified_page()
    {
        $pdf = new Pdf($this->multipageTestFile);

        $pdf->setPage(2);

        $imagick = $pdf->getImageData('page-2.jpg');

        $this->assertInstanceOf('Imagick', $imagick);
    }


    /** @test */
    public function it_will_accept_a_specified_file_type_and_convert_to_it()
    {
        $pdf = new Pdf($this->testFile);

        $pdf->setOutputFormat('png');

        $imagick = $pdf->getImageData($this->outputFile);

        $this->assertSame($imagick->getFormat(), 'png');
        $this->assertNotSame($imagick->getFormat(), 'jpg');
    }


    /** @test */
    public function it_will_accept_settings_and_convert_with_it()
    {
        $beforeSettings = function (\Imagick $imagick) {
            $imagick->setColorspace(\Imagick::COLORSPACE_SRGB);

            return $imagick;
        };

        $afterSettings = function (\Imagick $imagick) {
            $imagick->resizeImage(1024, 1024, \Imagick::FILTER_LANCZOS, 0, true);

            return $imagick;
        };

        $pdf = new Pdf($this->testFile, $beforeSettings, $afterSettings);

        $pdf->setOutputFormat('png');

        $this->assertTrue($pdf->saveImage($this->outputFile));
        $this->assertSame(1024, getimagesize($this->outputFile)[1]);
    }


    /** @test */
    public function it_will_convert_from_url()
    {
        $pdf = new Pdf($this->testUrl);
        $pdf->setResolution(300);
        $pdf->setOutputFormat('png');

        $pdf->setAfterSettings(function (\Imagick $imagick, $page) {
            $imagick->resizeImage(max(128, 512 / $page), max(128, 512 / $page), \Imagick::FILTER_LANCZOS, 0, true);

            return $imagick;
        });

        $pages = $pdf->saveAllPagesAsImages($this->outputDir, 'url-');

        $this->assertSame(512, getimagesize($this->outputDir . '/url-1.png')[0]);
        $this->assertSame(256, getimagesize($this->outputDir . '/url-2.png')[0]);

        foreach ($pages as $page) {
            @unlink($page);
        }
    }
}
