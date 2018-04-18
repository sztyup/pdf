<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Sztyup\Pdf\Compatibility;

class PdfChecker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'pdf:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all pdf file to confrom to version requirement set by TCPDF';

    protected function getArguments()
    {
        return [
            ['path', InputArgument::REQUIRED, 'Path to check when looking for pdf documents']
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['check-only', null, InputOption::VALUE_NONE, 'Only check and count files in need of conversion'],
        ];
    }

    /**
     * @param Compatibility $compatibility
     * @throws \Exception
     */
    public function handle(Compatibility $compatibility)
    {
        if (!$this->checkGsInstall()) {
            $this->error('Ghostscript is not installed. Aborting');
            return;
        }

        $this->info('Checking files');
        $path = $this->argument('path');

        $files = $this->collectConvertable($this->getFiles($path), $compatibility);

        if (!$this->option('check-only')) {
            $this->convert($compatibility, $files);
        }
    }

    /**
     * @param $directory
     * @return array
     */
    protected function getFiles($directory)
    {
        return iterator_to_array(
            Finder::create()->name('*.pdf')->files()->in($directory)->sortByName(),
            false
        );
    }

    /**
     * @return bool
     */
    protected function checkGsInstall()
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

    protected function collectConvertable(array $files, Compatibility $checker)
    {
        $bar = $this->output->createProgressBar(count($files));

        $convertable = [];

        foreach ($files as $i => $file) {
            if (!Str::endsWith($file, '.pdf')) {
                continue;
            }

            if ($checker->check($file) === false) {
                $convertable[] = $file;
            }

            $bar->advance();
        }

        $bar->finish();

        $this->info("\n" . count($files) . ' files checked, ' . count($convertable) . ' files need conversion');

        return $convertable;
    }

    /**
     * @param Compatibility $converter
     * @param $files
     * @throws \Exception
     */
    protected function convert(Compatibility $converter, $files)
    {
        $this->info('Converting files');

        $bar = $this->output->createProgressBar(count($files));

        foreach ($files as $file) {
            try {
                $converter->convert($file, '1.4');
            } catch (\Exception $exception) {
                $this->error("\nGhostScript cant convert pdf files, make sure GhostScript is installed and you have
                                permission to write files in the storage folder");

                throw $exception;
            }

            $bar->advance();
        }

        $bar->finish();
    }
}
