<?php

namespace Sztyup\Pdf\Compatibility;

class Checker
{
    /**
     * @param $file
     * @return null
     */
    public function check($file)
    {
        $fp = @fopen($file, 'rb');

        if (!$fp) {
            throw new \InvalidArgumentException("Cant read file ($file)");
        }

        fseek($fp, 0);

        preg_match('/%PDF-(\d\.\d)/', fread($fp, 1024), $match);

        fclose($fp);

        return $match[1] ?? null;
    }
}
