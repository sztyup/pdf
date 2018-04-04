<?php

namespace Sztyup\Pdf\Compatibility;

use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Converter
{
    protected $filesystem;

    const OPTIONS = [
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
    ];

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    protected function generateCommand($inputFile, $outputFile, $version = '1.4')
    {
        $options = '';
        foreach (self::OPTIONS as $key => $value) {
            if (is_int($key)) {
                $options .= ' -d' . $value;
            } else {
                $options .= ' -d' . $key . '=' . $value;
            }
        }

        return
            "gs -sDEVICE=pdfwrite $options -dCompatibilityLevel=$version -sOutputFile=$outputFile $inputFile";
    }

    public function convert($file, $version = '1.4')
    {
        $temporaryFile = sys_get_temp_dir() . '/pdf/' . Str::random(20);

        $process = new Process(
            $this->generateCommand($file, $temporaryFile, $version)
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        if ($this->filesystem->exists($temporaryFile)) {
            // Copy temporary file back
            $this->filesystem->copy($temporaryFile, $file, true);

            // Delete temporary file
            $this->filesystem->remove($temporaryFile);
        } else {
            throw new \RuntimeException('Something failed in creating the temporary file');
        }
    }
}
