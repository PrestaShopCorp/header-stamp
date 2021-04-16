<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\HeaderStamp;

/**
 * Class responsible of loading license file in memory and returning its content
 */
class LicenseHeader
{
    /**
     * Header content
     *
     * @var ?string
     */
    private $content;

    /**
     * Path to the file
     *
     * @var string
     */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return string Getter for Header content
     */
    public function getContent(): string
    {
        if (null === $this->content) {
            $this->loadFile();
        }

        return $this->content;
    }

    /**
     * Checks the file and loads its content in memory
     */
    private function loadFile(): void
    {
        if (!\file_exists($this->filePath)) {
            // If the file is not found, we might have a relative path
            // We check this before throwing any exception
            $fromRelativeFilePath = getcwd() . '/' . $this->filePath;
            $fromSrcFolderFilePath = __DIR__ . '/../' . $this->filePath;

            if (\file_exists($fromRelativeFilePath)) {
                $this->filePath = $fromRelativeFilePath;
            } elseif (\file_exists($fromSrcFolderFilePath)) {
                $this->filePath = $fromSrcFolderFilePath;
            } else {
                throw new \Exception('File ' . $this->filePath . ' does not exist.');
            }
        }

        if (!\is_readable($this->filePath)) {
            throw new \Exception('File ' . $this->filePath . ' cannot be read.');
        }

        $content = \file_get_contents($this->filePath);
        if ($content === false) {
            return;
        }

        $this->content = $content;
    }
}
