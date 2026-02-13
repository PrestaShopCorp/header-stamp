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

use Exception;

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

    private $contentByTypes;

    private $regexByTypes;

    /**
     * Path to the file
     *
     * @var string
     */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->contentByTypes = [];
        $this->regexByTypes = [];
    }

    /**
     * @return string Getter for Header content
     */
    public function getContent(): string
    {
        if (null === $this->content) {
            $this->content = $this->loadFile();
        }

        return $this->content;
    }

    public function getContentByType(string $type): string
    {
        if (!isset($this->contentByTypes[$type])) {
            switch ($type) {
                case 'vue':
                case 'html':
                    $this->contentByTypes[$type] = $this->rewriteContent($this->getContent(), '<!--*', '*-->');
                    break;
                case 'tpl':
                    $this->contentByTypes[$type] = $this->rewriteContent($this->getContent(), '{**', '*}');
                    break;
                case 'twig':
                    $this->contentByTypes[$type] = $this->rewriteContent($this->getContent(), '{#', '#}', ' #');
                    break;
                case 'php':
                case 'js':
                case 'ts':
                case 'css':
                case 'scss':
                    $this->contentByTypes[$type] = $this->rewriteContent($this->getContent(), '/**', '*/');
                    break;
                default:
                    throw new \RuntimeException('Unknown type ' . $type);
            }
        }

        return $this->contentByTypes[$type];
    }

    public function getRegexByType(string $type): string
    {
        if (!isset($this->regexByTypes[$type])) {
            switch ($type) {
                case 'vue':
                case 'html':
                    $this->regexByTypes[$type] = $this->getLicenseRegex('<!--', '-->');
                    break;
                case 'tpl':
                    $this->regexByTypes[$type] = $this->getLicenseRegex('{', '}');
                    break;
                case 'twig':
                    $this->regexByTypes[$type] = $this->getTwigLicenseRegex('{#', '#}', '#');
                    break;
                case 'php':
                case 'js':
                case 'ts':
                case 'css':
                case 'scss':
                    $this->regexByTypes[$type] = $this->getLicenseRegex('\/', '\/');
                    break;
                default:
                    throw new \RuntimeException('Unknown type ' . $type);
            }
        }

        return $this->regexByTypes[$type];
    }

    public function getTwigLicenseRegex(string $startDelimiter, string $endDelimiter, string $commentDelimiter = '*'): string
    {
        $startDelimiter = addcslashes($startDelimiter, '*#{}');
        $endDelimiter = addcslashes($endDelimiter, '*#{}');
        $commentDelimiter = addcslashes($commentDelimiter, '*#{}');

        $regex = '%^' . $startDelimiter . '([^' . $commentDelimiter . ']|[\r\n]|(' . $commentDelimiter . '+((?!\})|[\r\n])))*+' . $endDelimiter . '%';

        return $regex;
    }

    private function getLicenseRegex(string $startDelimiter, string $endDelimiter): string
    {
        // Regular expression found thanks to Stephen Ostermiller's Blog. http://blog.ostermiller.org/find-comment
        // $regex = '%^' . $startDelimiter . '\*([^*]|[\r\n]|(\*+([^*' . $endDelimiter . ']|[\r\n])))*\*+' . $endDelimiter . '%';
        // Initial regex was improved for special cases in Twig
        return '%^' . $startDelimiter . '\*([^*]|[\r\n]|(\*+((?!' . $endDelimiter . ')|[\r\n])))*\*+' . $endDelimiter . '%';
    }

    private function rewriteContent(string $text, string $startDelimiter, string $endDelimiter, ?string $commentDelimiter = null): string
    {
        // Adapt the license header with expected delimiters
        $text = rtrim($text, PHP_EOL);
        $text = $startDelimiter . ltrim($text, '/**');
        $text = rtrim($text, '*/') . $endDelimiter;
        if (null !== $commentDelimiter) {
            $text = (string) preg_replace('% \*%', $commentDelimiter, $text);
        }

        return trim($text, PHP_EOL);
    }

    /**
     * Checks the file and loads its content in memory
     */
    private function loadFile(): string
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
            throw new Exception('Cannot load license file ' . $this->filePath . '.');
        }

        return $content;
    }
}
