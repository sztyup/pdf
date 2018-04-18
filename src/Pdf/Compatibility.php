<?php

namespace Sztyup\Pdf;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\Process\Process;

class Compatibility
{
    protected $filesystem;

    const CONVERTER_OPTIONS = [
        'PDFSETTINGS' => '/screen',
        'NOPAUSE',
        'QUIET',
        'BATCH',
        'ColorConversionStrategy' => '/LeaveColorUnchanged',
        'EncodeColorImages' => 'false',
        'EncodeGrayImages' => 'false',
        'EncodeMonoImages' => 'false',
        'DownsampleMonoImages' => 'false',
        'DownsampleGrayImages' => 'false',
        'DownsampleColorImages' => 'false',
        'AutoFilterColorImages' => 'false',
        'AutoFilterGrayImages' => 'false',
        'ColorImageFilter' => '/FlateEncode',
        'GrayImageFilter' => '/FlateEncode',
        'CompatibilityLevel' => '1.4'
    ];

    const MAXIMUM_COMPATIBLE_PDF_VERSION = 14;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    protected function generateCommand($inputFile, $outputFile)
    {
        $options = '';
        foreach (self::CONVERTER_OPTIONS as $key => $value) {
            if (is_int($key)) {
                $options .= ' -d' . $value;
            } else {
                $options .= ' -d' . $key . '=' . $value;
            }
        }

        return "gs -sDEVICE=pdfwrite $options -sOutputFile=$outputFile $inputFile";
    }

    /**
     * @return bool
     */
    public function converterAvailable()
    {
        $command = new Process('gs --version');

        try {
            $command->run();
        } catch (\Exception $exception) {
            return false;
        }

        if ($command->isSuccessful()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $file
     */
    public function convert($file)
    {
        if (!$this->converterAvailable()) {
            throw new \RuntimeException('Ghostscript is not installed.');
        }

        $temporaryFile = sys_get_temp_dir() . '/pdf/' . Str::random(20);

        $process = new Process(
            $this->generateCommand($file, $temporaryFile)
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        if ($this->filesystem->exists($temporaryFile)) {
            // Copy temporary file back
            $this->filesystem->copy($temporaryFile, $file);

            // Delete temporary file
            $this->filesystem->delete($temporaryFile);
        } else {
            throw new \RuntimeException('Something failed in creating the temporary file');
        }
    }

    /**
     * @param $file
     * @return int Version number as int (1.4 => 14)
     */
    public function getVersion($file)
    {
        if (!$this->filesystem->exists($file)) {
            throw new \InvalidArgumentException("File does not exists ($file)");
        }

        $stream = StreamReader::createByFile($file);

        preg_match('/%PDF-(\d\.\d)/', $stream->readBytes(1024), $match);

        unset($stream);

        $version = floatval($match[1]);

        if (is_float($version) && $version > 0 && $version < 10) {
            return intval(Str::replaceFirst('.', '', $match[1]));
        }

        throw new \LogicException("Unrecognised version number ($match[1])");
    }

    /**
     * @param $file
     * @return bool
     */
    public function check($file)
    {
        return $this->getVersion($file) <= self::MAXIMUM_COMPATIBLE_PDF_VERSION;
    }
}
