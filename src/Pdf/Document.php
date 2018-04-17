<?php

namespace Sztyup\Pdf;

use Barryvdh\DomPDF\PDF;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Routing\ResponseFactory;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\TcpdfFpdi;
use Closure;

class Document
{
    /** @var TcpdfFpdi */
    protected $pdf;

    /** @var PDF */
    protected $dompdf;

    /** @var Filesystem */
    protected $filesystem;

    /** @var ResponseFactory */
    protected $responseFactory;

    /** @var array */
    protected $config;

    protected $compatibility;

    const ORIENTATION_PORTRAIT = 'P';
    const ORIENTATION_LANDSCAPE = 'L';

    public function __construct(
        PDF $dompdf,
        Filesystem $filesystem,
        ResponseFactory $responseFactory,
        Repository $config,
        Compatibility $compatibility
    ) {
        $this->dompdf = $dompdf;
        $this->filesystem = $filesystem;
        $this->responseFactory = $responseFactory;
        $this->config = $config->get('pdf');
        $this->compatibility = $compatibility;

        $this->init();
    }

    protected function init()
    {
        $this->pdf = new TcpdfFpdi(
            $this->config['defaults']['orientation'],
            'mm',
            $this->config['defaults']['size'],
            true,
            'UTF-8',
            false,
            true
        );

        $this->pdf->setPrintFooter(false);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPDFVersion();

        $this->pdf->SetTitle($this->config['defaults']['title']);
    }

    public function setMetadata($author)
    {
        $this->pdf->SetAuthor($author);
    }

    /**
     * @param $file string|resource|StreamReader
     * @param Closure|null $callback A callback to run at each imported page
     * @throws \setasign\Fpdi\PdfReader\PdfReaderException
     */
    public function appendPdfFile($file, Closure $callback = null)
    {
        if (!$this->compatibility->check($file)) {
            throw new \InvalidArgumentException("Cant append file ($file) because of incompatible PDF version");
        }

        $pageCount = $this->pdf->setSourceFile($file);

        for ($i = 1; $i <= $pageCount; $i++) {
            $this->pdf->AddPage();
            $this->pdf->useTemplate($this->pdf->importPage($i));

            if (is_callable($callback)) {
                $this->pdf = $callback($this->pdf, $i, $pageCount);
            }
        }
    }

    /**
     * @param $string
     * @param Closure|null $callback
     * @throws \setasign\Fpdi\PdfReader\PdfReaderException
     */
    public function appendPdfString($string, Closure $callback = null)
    {
        $this->appendPdfFile(StreamReader::createByString($string), $callback);
    }

    /**
     * @param $html
     * @param Closure|null $callback
     * @throws \setasign\Fpdi\PdfReader\PdfReaderException
     */
    public function appendHtml($html, Closure $callback = null)
    {
        $this->dompdf->loadHTML($html);

        $this->appendPdfString($this->dompdf->output(), $callback);
    }

    public function output()
    {
        return $this->pdf->Output('', 'S');
    }

    /**
     * @param $file
     * @param bool $overwrite
     * @throws \Exception
     */
    public function save($file, $overwrite = false)
    {
        if ($this->filesystem->exists($file) && !$overwrite) {
            throw new \Exception('File already exists at ' . $file);
        }

        if ($this->filesystem->put($file, $this->output()) === false) {
            throw new \Exception('File saving failed at ' . $file);
        }
    }

    public function download()
    {
        return $this->responseFactory->streamDownload(function () {
            return $this->output();
        });
    }

    public function inline()
    {
        return $this->responseFactory->stream(function () {
            return $this->output();
        });
    }
}
