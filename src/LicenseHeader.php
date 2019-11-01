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
            // If the file is not found, we might have a relative path
            // We check this before throwing any exception
            $fromRelativeFilePath = getcwd() . '/' . $this->filePath;

            if (!\file_exists($fromRelativeFilePath)) {
                throw new \Exception(
                    'File '. $this->filePath .' does not exist.'
                );
            }
            $this->filePath = $fromRelativeFilePath . $this->filePath;
        }

        if (!\is_readable($this->filePath)) {
            throw new \Exception(
                'File '. $this->filePath .' cannot be read.'
            );
        }

        $this->content = \file_get_contents($this->filePath);
    }
}