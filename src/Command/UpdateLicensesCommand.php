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

namespace PrestaShop\HeaderStamp\Command;

use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PrestaShop\HeaderStamp\LicenseHeader;
use PrestaShop\HeaderStamp\Reporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class UpdateLicensesCommand extends Command
{
    const DEFAULT_LICENSE_FILE = __DIR__ . '/../../assets/osl3.txt';
    const DEFAULT_EXTENSIONS = [
        'php',
        'js',
        'css',
        'scss',
        'tpl',
        'html.twig',
        'json',
        'vue',
    ];
    const DEFAULT_FOLDER_FILTERS = [];

    const DEFAULT_FILE_FILTERS = [];

    /**
     * License content
     *
     * @var string
     */
    private $text;

    /**
     * License file path (not content)
     *
     * @var string
     */
    private $license;

    /**
     * @var string|false Can be false because of realpath function
     */
    private $targetDirectory;

    /**
     * List of extensions to update
     *
     * @var array<int, string>
     */
    private $extensions;

    /**
     * List of folders to exclude from the search
     *
     * @var array<int, string>
     */
    private $folderFilters;

    /**
     * List of file names to exclude from the search
     *
     * @var array<int, string>
     */
    private $fileFilters;

    /**
     * dry-run feature flag
     *
     * @var bool
     */
    private $runAsDry;

    /**
     * display-report feature flag
     *
     * @var bool
     */
    private $displayReport;

    /**
     * Reporter in charge of monitoring what is done and provide a complete report
     * at the end of execution
     *
     * @var Reporter
     */
    private $reporter;

    /**
     * @var string
     */
    private $discriminationString;

    protected function configure(): void
    {
        $this
            ->setName('prestashop:licenses:update')
            ->setDescription('Rewrite your file headers to add the license or to make them up-to-date')
            ->addOption(
                'license',
                null,
                InputOption::VALUE_REQUIRED,
                'License file to apply',
                realpath(static::DEFAULT_LICENSE_FILE)
            )
            ->addOption(
                'target',
                null,
                InputOption::VALUE_REQUIRED,
                'Folder to work in (default: current dir)'
            )
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of folders to exclude from the update',
                implode(',', static::DEFAULT_FOLDER_FILTERS)
            )
            ->addOption(
                'not-name',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of file names to exclude from the update',
                implode(',', static::DEFAULT_FILE_FILTERS)
            )
            ->addOption(
                'extensions',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of file extensions to update',
                implode(',', static::DEFAULT_EXTENSIONS)
            )
            ->addOption(
                'display-report',
                null,
                InputOption::VALUE_NONE,
                'Whether or not to display a report'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Dry-run mode does not modify files'
            )
            ->addOption(
                'header-discrimination-string',
                null,
                InputOption::VALUE_OPTIONAL,
                'Fix existing licenses only if they contain that string',
                'prestashop'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->extensions = explode(',', $input->getOption('extensions'));
        $this->folderFilters = $input->getOption('exclude')
            ? explode(',', $input->getOption('exclude')) : [];
        $this->fileFilters = $input->getOption('not-name')
            ? explode(',', $input->getOption('not-name')) : [];

        $licenseOption = $input->getOption('license');
        $this->license = is_string($licenseOption) ? $licenseOption : '';

        $targetOption = $input->getOption('target');
        if (is_string($targetOption) && !empty($targetOption)) {
            $this->targetDirectory = realpath($targetOption);
        } else {
            $this->targetDirectory = getcwd();
        }
        $this->runAsDry = ($input->getOption('dry-run') === true);
        $this->displayReport = ($input->getOption('display-report') === true);

        $discriminationOption = $input->getOption('header-discrimination-string');
        $this->discriminationString = is_string($discriminationOption) ? $discriminationOption : '';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->text = trim((new LicenseHeader($this->license))->getContent(), PHP_EOL);

        $this->reporter = new Reporter();

        foreach ($this->extensions as $extension) {
            $this->findAndCheckExtension($input, $output, $extension);
        }

        if ($this->runAsDry) {
            $this->printDryRunPrettyReport($input, $output);

            if (empty($this->reporter->getReport()['fixed'])) {
                return 0;
            }

            return 1;
        }

        if ($this->displayReport) {
            $this->printPrettyReport($input, $output);
        }

        return 0;
    }

    private function findAndCheckExtension(InputInterface $input, OutputInterface $output, string $ext): void
    {
        if ($this->targetDirectory === false) {
            throw new \Exception('Could not get target directory. Check your permissions.');
        }

        $finder = new Finder();
        $finder
            ->files()
            ->name('*.' . $ext)
            ->in($this->targetDirectory)
            ->exclude($this->folderFilters)
            ->notPath($this->folderFilters)
            ->notName($this->fileFilters);

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $output->writeln('Updating license in ' . strtoupper($ext) . ' files ...');
        $progress = new ProgressBar($output, count($finder));
        $progress->start();
        $progress->setRedrawFrequency(20);

        foreach ($finder as $file) {
            switch ($file->getExtension()) {
                case 'php':
                    try {
                        $nodes = $parser->parse($file->getContents());
                        if ($nodes !== null && count($nodes)) {
                            $this->addLicenseToNode($nodes[0], $file);
                        }
                    } catch (\PhpParser\Error $exception) {
                        $output->writeln('Syntax error on file ' . $file->getRelativePathname() . '. Continue ...');
                        $this->reporter->reportLicenseCouldNotBeFixed($file->getFilename());
                    }

                    break;
                case 'js':
                case 'css':
                case 'scss':
                    $this->addLicenseToFile($file);

                    break;
                case 'tpl':
                    $this->addLicenseToSmartyTemplate($file);

                    break;
                case 'twig':
                    $this->addLicenseToTwigTemplate($file);

                    break;
                case 'json':
                    $this->addLicenseToJsonFile($file);

                    break;
                case 'vue':
                    $this->addLicenseToHtmlFile($file);

                    break;
            }
            $progress->advance();
        }

        $progress->finish();
        $output->writeln('');
    }

    private function addLicenseToFile(SplFileInfo $file, string $startDelimiter = '\/', string $endDelimiter = '\/'): void
    {
        $content = $file->getContents();
        $oldContent = $content;
        // Regular expression found thanks to Stephen Ostermiller's Blog. http://blog.ostermiller.org/find-comment
        $regex = '%' . $startDelimiter . '\*([^*]|[\r\n]|(\*+([^*' . $endDelimiter . ']|[\r\n])))*\*+' . $endDelimiter . '%';
        $matches = [];
        $text = $this->text;
        if ($startDelimiter != '\/') {
            $text = $startDelimiter . ltrim($text, '/');
        }
        if ($endDelimiter != '\/') {
            $text = rtrim($text, '/') . $endDelimiter;
        }

        // Try to find an existing license
        preg_match($regex, $content, $matches);

        if (count($matches)) {
            // Found - Replace it if prestashop one
            foreach ($matches as $match) {
                if (stripos($match, $this->discriminationString) !== false) {
                    $content = str_replace($match, $text, $content);
                }
            }
        } else {
            // Not found - Add it at the beginning of the file
            $content = $text . "\n" . $content;
        }

        if (!$this->runAsDry) {
            file_put_contents($this->targetDirectory . '/' . $file->getRelativePathname(), $content);
        }

        $this->reportOperationResult($content, $oldContent, $file->getFilename());
    }

    private function addLicenseToNode(Stmt $node, SplFileInfo $file): void
    {
        if (!$node->hasAttribute('comments')) {
            $needle = '<?php';
            $replace = "<?php\n" . $this->text;
            $haystack = $file->getContents();

            $pos = strpos($haystack, $needle);
            // Important, if the <?php is in the middle of the file, continue
            if ($pos === 0) {
                // Check if an empty newline is present right after the <?php tag
                // Append newline to replacement if missing
                $checkNewline = substr($haystack, strlen($needle), 2) === "\n\n";
                if (!$checkNewline) {
                    $replace .= "\n";
                }

                $newstring = substr_replace($haystack, $replace, $pos, strlen($needle));

                if (!$this->runAsDry) {
                    file_put_contents($this->targetDirectory . '/' . $file->getRelativePathname(), $newstring);
                }

                $this->reportOperationResult($newstring, $haystack, $file->getFilename());
            }

            return;
        }

        $comments = $node->getAttribute('comments');
        foreach ($comments as $comment) {
            if ($comment instanceof \PhpParser\Comment
                && strpos($comment->getText(), $this->discriminationString) !== false) {
                $newContent = str_replace($comment->getText(), $this->text, $file->getContents());

                if (!$this->runAsDry) {
                    file_put_contents(
                        $this->targetDirectory . '/' . $file->getRelativePathname(),
                        $newContent
                    );
                }

                $this->reportOperationResult($newContent, $file->getContents(), $file->getFilename());
            }
        }
    }

    private function addLicenseToSmartyTemplate(SplFileInfo $file): void
    {
        $this->addLicenseToFile($file, '{', '}');
    }

    private function addLicenseToTwigTemplate(SplFileInfo $file): void
    {
        if (strrpos($file->getRelativePathName(), 'html.twig') !== false) {
            $this->addLicenseToFile($file, '{#', '#}');
        }
    }

    private function addLicenseToHtmlFile(SplFileInfo $file): void
    {
        $this->addLicenseToFile($file, '<!--', '-->');
    }

    private function addLicenseToJsonFile(SplFileInfo $file): bool
    {
        if (!in_array($file->getFilename(), ['composer.json', 'package.json'])) {
            return false;
        }

        $content = json_decode($file->getContents(), true);
        $oldContent = $content;
        $content['author'] = 'PrestaShop';
        $content['license'] = (false !== strpos($this->license, 'afl')) ? 'AFL-3.0' : 'OSL-3.0';

        if (!$this->runAsDry) {
            $result = file_put_contents(
                $this->targetDirectory . '/' . $file->getRelativePathname(),
                json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );
        } else {
            $result = true;
        }

        $newFileContent = (string) json_encode($content);
        $oldFileContent = (string) json_encode($oldContent);

        $this->reportOperationResult($newFileContent, $oldFileContent, $file->getFilename());

        return false !== $result;
    }

    /**
     * @var string
     * @var string
     */
    private function reportOperationResult(string $newFileContent, string $oldFileContent, string $filename): void
    {
        if ($newFileContent !== $oldFileContent) {
            $this->reporter->reportLicenseHasBeenFixed($filename);
        } else {
            $this->reporter->reportLicenseWasFine($filename);
        }
    }

    private function printPrettyReport(InputInterface $input, OutputInterface $output): void
    {
        $style = new SymfonyStyle($input, $output);
        $style->section('Header Stamp Report');

        $report = $this->reporter->getReport();
        $sections = ['fixed', 'nothing to fix', 'failed'];
        foreach ($sections as $section) {
            if (empty($report[$section])) {
                continue;
            }

            $style->text(ucfirst($section) . ':');
            $style->listing($report[$section]);
        }
    }

    private function printDryRunPrettyReport(InputInterface $input, OutputInterface $output): void
    {
        $style = new SymfonyStyle($input, $output);
        $style->section('Header Stamp Dry Run Report');

        $report = $this->reporter->getReport();

        if (empty($report['fixed'])) {
            return;
        }

        $style->text('Files with bad license headers:');
        $style->listing($report['fixed']);
    }
}
