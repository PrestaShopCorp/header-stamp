<?php

namespace PrestaShop\PimpMyHeader;

class LicenseHeader
{
    /**
     * @param string Header content
     */
    private $content;

    /**
     * @param string Path to the file
     */
    private $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return string Getter for Header content
     */
    public function getContent()
    {
        if (null === $this->content) {
            $this->loadFile();
        }
        return $this->content;
    }

    /**
     * Checks the file and loads its content in memory
     */
    private function loadFile()
    {
        if (!\file_exists($this->filePath)) {
            throw new \Exception(
                'File '. $this->filePath .' does not exist.'
            );
        }

        if (!\is_readable($this->filePath)) {
            throw new \Exception(
                'File '. $this->filePath .' cannot be read.'
            );
        }

        $this->content = \file_get_contents($this->filePath);
    }
}