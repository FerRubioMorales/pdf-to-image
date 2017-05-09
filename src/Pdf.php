<?php

namespace Bnb\PdfToImage;

use Bnb\PdfToImage\Exceptions\InvalidFormat;
use Bnb\PdfToImage\Exceptions\PageDoesNotExist;
use Bnb\PdfToImage\Exceptions\PdfDoesNotExist;

class Pdf
{

    protected $pdfFile;

    protected $resolution = 144;

    protected $outputFormat = '';

    protected $pages = 0;

    protected $page = 1;

    protected $imagick;

    protected $validOutputFormats = ['jpg', 'jpeg', 'png'];

    /** @var callable */
    protected $beforeSettings;

    /** @var callable */
    protected $afterSettings;


    /**
     * @param string   $pdfFile        The path or URL to the PDF file.
     *
     * @param callable $beforeSettings A callback that takes the Imagick object as parameter and returns it
     * @param callable $afterSettings  A callback that takes the Imagick object as parameter and returns it
     *
     * @throws PdfDoesNotExist
     */
    public function __construct($pdfFile, callable $beforeSettings = null, callable $afterSettings = null)
    {
        if ( ! filter_var($pdfFile, FILTER_VALIDATE_URL) && ! file_exists($pdfFile)) {
            throw new PdfDoesNotExist();
        }

        if (filter_var($pdfFile, FILTER_VALIDATE_URL)) {
            if ( ! ($file = tempnam(sys_get_temp_dir(), 'urltopdf'))) {
                throw new PdfDoesNotExist();
            }

            file_put_contents($file, file_get_contents($pdfFile));

            $pdfFile = $file;
        }

        $this->imagick = new \Imagick();
        $this->pdfFile = $pdfFile;
        $this->beforeSettings = $beforeSettings;
        $this->afterSettings = $afterSettings;
    }


    /**
     * Set the callback that takes the Imagick object as parameter and returns it before the image read
     *
     * @param callable $settings
     *
     * @return $this
     */
    public function setBeforeSettings(callable $settings)
    {
        $this->beforeSettings = $settings;

        return $this;
    }


    /**
     * Set the callback that takes the Imagick object as parameter and returns it after the image read
     *
     * @param callable $settings
     *
     * @return $this
     */
    public function setAfterSettings(callable $settings)
    {
        $this->afterSettings = $settings;

        return $this;
    }


    /**
     * Set the raster resolution.
     *
     * @param int $resolution
     *
     * @return $this
     */
    public function setResolution($resolution)
    {
        $this->resolution = $resolution;

        return $this;
    }


    /**
     * Set the output format.
     *
     * @param string $outputFormat
     *
     * @return $this
     *
     * @throws \Bnb\PdfToImage\Exceptions\InvalidFormat
     */
    public function setOutputFormat($outputFormat)
    {
        if ( ! $this->isValidOutputFormat($outputFormat)) {
            throw new InvalidFormat('Format ' . $outputFormat . ' is not supported');
        }

        $this->outputFormat = $outputFormat;

        return $this;
    }


    /**
     * Determine if the given format is a valid output format.
     *
     * @param $outputFormat
     *
     * @return bool
     */
    public function isValidOutputFormat($outputFormat)
    {
        return in_array($outputFormat, $this->validOutputFormats);
    }


    /**
     * Set the page number that should be rendered.
     *
     * @param int $page
     *
     * @return $this
     *
     * @throws \Bnb\PdfToImage\Exceptions\PageDoesNotExist
     */
    public function setPage($page)
    {
        if ($page > $this->getNumberOfPages()) {
            throw new PageDoesNotExist('Page ' . $page . ' does not exist');
        }

        $this->page = $page;

        return $this;
    }


    /**
     * Get the number of pages in the pdf file.
     *
     * @return int
     */
    public function getNumberOfPages()
    {
        if ($this->pages != 0) {
            return $this->pages;
        }

        $this->imagick->setResolution($this->resolution, $this->resolution);

        if ($this->beforeSettings) {
            $this->imagick = ($this->beforeSettings)($this->imagick, $this->page);
        }

        $this->imagick->readImage($this->pdfFile);

        return $this->pages = $this->imagick->getNumberImages();
    }


    /**
     * Save the image to the given path.
     *
     * @param string $pathToImage
     *
     * @return bool
     */
    public function saveImage($pathToImage)
    {
        $imageData = $this->getImageData($pathToImage);

        return file_put_contents($pathToImage, $imageData) === false ? false : true;
    }


    /**
     * Save the file as images to the given directory.
     *
     * @param string $directory
     * @param string $prefix
     *
     * @return array $files the paths to the created images
     */
    public function saveAllPagesAsImages($directory, $prefix = '')
    {
        $numberOfPages = $this->getNumberOfPages();

        if ($numberOfPages === 0) {
            return [];
        }

        return array_map(function ($pageNumber) use ($directory, $prefix) {
            $this->setPage($pageNumber);

            $destination = "{$directory}/{$prefix}{$pageNumber}.{$this->outputFormat}";

            $this->saveImage($destination);

            return $destination;
        }, range(1, $numberOfPages));
    }


    /**
     * Return raw image data.
     *
     * @param string $pathToImage
     *
     * @return \Imagick
     */
    public function getImageData($pathToImage)
    {
        $this->imagick->setResolution($this->resolution, $this->resolution);

        if ($this->beforeSettings) {
            $this->imagick = ($this->beforeSettings)($this->imagick, $this->page);
        }

        $this->imagick->readImage(sprintf('%s[%s]', $this->pdfFile, $this->page - 1));

        $this->imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        $this->imagick->setFormat($this->determineOutputFormat($pathToImage));

        if ($this->afterSettings) {
            $this->imagick = ($this->afterSettings)($this->imagick, $this->page);
        }

        return $this->imagick;
    }


    /**
     * Determine in which format the image must be rendered.
     *
     * @param $pathToImage
     *
     * @return string
     */
    protected function determineOutputFormat($pathToImage)
    {
        $outputFormat = pathinfo($pathToImage, PATHINFO_EXTENSION);

        if ($this->outputFormat != '') {
            $outputFormat = $this->outputFormat;
        }

        $outputFormat = strtolower($outputFormat);

        if ( ! $this->isValidOutputFormat($outputFormat)) {
            $outputFormat = 'jpg';
        }

        return $outputFormat;
    }
}
