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

use Exception;
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
use Symfony\Component\Yaml\Yaml;

class UpdateLicensesCommand extends Command
{
    const DEFAULT_LICENSE_FILE = __DIR__ . '/../../assets/osl3.txt';
    const DEFAULT_EXTENSIONS = [
        'php',
        'js',
        'ts',
        'css',
        'scss',
        'tpl',
        'html.twig',
        'json',
        'vue',
    ];
    const CONFIG_PARAMETERS_MAPPING = [
        'extensions' => 'extensions',
        'folderFilters' => 'exclude',
        'fileFilters' => 'not-name',
        'license' => 'license',
        'targetDirectory' => 'target-directory',
        'runAsDry' => 'dry-run',
        'displayReport' => 'display-report',
        'discriminationString' => 'header-discrimination-string',
    ];

    const DEFAULT_FOLDER_FILTERS = [
        'vendor',
        'node_modules',
    ];

    const DEFAULT_FILE_FILTERS = [];

    /**
     * @var LicenseHeader
     */
    private $licenseHeader;

    /**
     * License file path (not content)
     *
     * @var string
     */
    private $license;

    /**
     * @var string Can be false because of realpath function
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
                'NOTICE OF LICENSE'
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Use YAML config file instead of individual options',
                '.header-stamp-config.yml'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $defaultConfig = $this->getDefaultConfig($input);
        $fileConfig = $this->getConfigFromFile($input);
        $tokenConfig = $this->getTokenConfig($input);

        $mergedConfig = array_merge(
            $defaultConfig,
            // Config file has more priority that the default values (especially the automatic fallbacks)
            $fileConfig,
            // But explicit individual parameter still has more value than the config file
            $tokenConfig
        );

        // Now apply the config to the command fields
        $this->extensions = $mergedConfig['extensions'];
        $this->folderFilters = $mergedConfig['folderFilters'];
        $this->fileFilters = $mergedConfig['fileFilters'];
        $this->license = $mergedConfig['license'];
        $this->targetDirectory = $mergedConfig['targetDirectory'];
        $this->runAsDry = $mergedConfig['runAsDry'];
        $this->displayReport = $mergedConfig['displayReport'];
        $this->discriminationString = $mergedConfig['discriminationString'];

        // Output configuration
        $output->writeln('Header stamp configuration:');
        foreach (array_keys(self::CONFIG_PARAMETERS_MAPPING) as $commandField) {
            $configValue = $mergedConfig[$commandField];
            $output->writeln(sprintf(' - %s: %s', $commandField, is_array($configValue) ? implode(', ', $configValue) : $configValue));
        }
    }

    /**
     * Returns default configuration as per defined in the command options,
     * using their default value or fallback if they are not specified.
     *
     * @return array{extensions: string[], folderFilters: string[], fileFilters: string[], license: string, targetDirectory: string, runAsDry: bool, displayReport: bool, discriminationString: string}
     */
    protected function getDefaultConfig(InputInterface $input): array
    {
        $defaultConfig = [
            'extensions' => explode(',', $input->getOption('extensions')),
            'folderFilters' => $input->getOption('exclude') ? explode(',', $input->getOption('exclude')) : [],
            'fileFilters' => $input->getOption('not-name') ? explode(',', $input->getOption('not-name')) : [],
            'license' => $input->getOption('license'),
            'targetDirectory' => $input->getOption('target') ?: '',
            'runAsDry' => $input->getOption('dry-run') === true,
            'displayReport' => $input->getOption('display-report') === true,
            'discriminationString' => $input->getOption('header-discrimination-string') ?: '',
        ];
        $this->adaptConfig($defaultConfig);

        return $defaultConfig;
    }

    /**
     * Return the config only based on explicitly specified parameters in the CLI command.
     *
     * @return array{extensions?: string[], folderFilters?: string[], fileFilters?: string[], license?: string, targetDirectory?: string, runAsDry?: bool, displayReport?: bool, discriminationString?: string}
     */
    protected function getTokenConfig(InputInterface $input): array
    {
        $tokenConfig = [];
        $defaultConfig = $this->getDefaultConfig($input);
        foreach (self::CONFIG_PARAMETERS_MAPPING as $configParameter => $cliParameter) {
            // Only keep parameters that are explicitly specified as CLI parameters
            if ($input->hasParameterOption('--' . $cliParameter)) {
                $tokenConfig[$configParameter] = $defaultConfig[$configParameter];
            }
        }
        $this->adaptConfig($tokenConfig);

        return $tokenConfig;
    }

    /**
     * Return the config defined in the config file (when present), the yaml file
     * can use the same keys as the CLI command parameters OR the config ones (they
     * even have a higher priority).
     *
     * Ex: fileFilters will be preferred over not-name
     *
     * @return array{extensions?: string[], folderFilters?: string[], fileFilters?: string[], license?: string, targetDirectory?: string, runAsDry?: bool, displayReport?: bool, discriminationString?: string}
     */
    protected function getConfigFromFile(InputInterface $input): array
    {
        $configFromFile = [];

        $configFile = $input->getOption('config');
        if (null !== $configFile && file_exists($configFile)) {
            $parsedConfig = Yaml::parse(file_get_contents($configFile) ?: '');

            // Transform parameters with the appropriate naming when they are present in the yaml file
            foreach (self::CONFIG_PARAMETERS_MAPPING as $parameter => $value) {
                $parameterName = self::CONFIG_PARAMETERS_MAPPING[$parameter];
                if (isset($parsedConfig[$parameterName])) {
                    $configFromFile[$parameter] = $parsedConfig[$parameterName];
                }

                // If the YAML contains a value using the config property name directly it is prioritized
                if (isset($parsedConfig[$parameter])) {
                    $configFromFile[$parameter] = $parsedConfig[$parameter];
                }
            }
        }
        $this->adaptConfig($configFromFile);

        return $configFromFile;
    }

    /**
     * Some config fields need to be adapted, this method transforms all three configs the same way
     *
     * @param array{extensions?: string[], folderFilters?: string[], fileFilters?: string[], license?: string, targetDirectory?: string, runAsDry?: bool, displayReport?: bool, discriminationString?: string} $config
     */
    protected function adaptConfig(array &$config): void
    {
        if (!empty($config['license'])) {
            $config['license'] = realpath($config['license']);
        }

        // Directory must be transformed in real path when present (unlikely in the config file)
        if (isset($config['targetDirectory'])) {
            if (!empty($config['targetDirectory'])) {
                $config['targetDirectory'] = realpath($config['targetDirectory']);
            } else {
                $config['targetDirectory'] = getcwd();
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->licenseHeader = new LicenseHeader($this->license);
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
        if (!is_dir($this->targetDirectory)) {
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
                case 'ts':
                case 'css':
                case 'scss':
                case 'vue':
                case 'html':
                case 'tpl':
                    $this->addLicenseToFile($file, $this->licenseHeader->getRegexByType($file->getExtension()));

                    break;
                case 'twig':
                    $this->addLicenseToTwigTemplate($file);

                    break;
                case 'json':
                    $this->addLicenseToJsonFile($file);

                    break;
            }
            $progress->advance();
        }

        $progress->finish();
        $output->writeln('');
    }

    private function addLicenseToFile(SplFileInfo $file, string $regex): void
    {
        $content = $file->getContents();
        $oldContent = $content;
        $matches = [];

        $text = $this->licenseHeader->getContentByType($file->getExtension());
        // Try to find an existing license
        preg_match($regex, $content, $matches);

        $foundLicenseComment = false;
        if (count($matches)) {
            // Found - Replace it if prestashop one
            foreach ($matches as $match) {
                if (stripos($match, $this->discriminationString) !== false) {
                    $foundLicenseComment = true;
                    $content = str_replace($match, $text, $content);
                }
            }
        }
        if (!$foundLicenseComment) {
            // Not found - Add it at the beginning of the file
            $content = $text . "\n" . $content;
        }

        if (!$this->runAsDry) {
            file_put_contents($this->targetDirectory . '/' . $file->getRelativePathname(), $content);
        }

        $this->reportOperationResult($content, $oldContent, $file->getRelativePathname());
    }

    private function addLicenseToNode(Stmt $node, SplFileInfo $file): void
    {
        if (!$node->hasAttribute('comments')) {
            $this->prependInPHPFile($file);

            return;
        }

        $comments = $node->getAttribute('comments');
        foreach ($comments as $comment) {
            if ($comment instanceof \PhpParser\Comment
                && strpos($comment->getText(), $this->discriminationString) !== false) {
                $newContent = str_replace($comment->getText(), $this->licenseHeader->getContentByType('php'), $file->getContents());

                if (!$this->runAsDry) {
                    file_put_contents(
                        $this->targetDirectory . '/' . $file->getRelativePathname(),
                        $newContent
                    );
                }

                $this->reportOperationResult($newContent, $file->getContents(), $file->getRelativePathname());

                return;
            }
        }

        // No comment was replaced so we prepend the license
        $this->prependInPHPFile($file);
    }

    private function prependInPHPFile(SplFileInfo $file): void
    {
        $needle = '<?php';
        $replace = "<?php\n" . $this->licenseHeader->getContentByType('php');
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

            $this->reportOperationResult($newstring, $haystack, $file->getRelativePathname());
        }
    }

    private function addLicenseToTwigTemplate(SplFileInfo $file): void
    {
        $fileContent = $file->getContents();
        $regexpCandidates = [
            // For a short moment v9 had some twig headers a bit wrongly written with extra spaces because of automatic
            // changes made by the new linter This special cas aims at fixing those
            $this->licenseHeader->getTwigLicenseRegex('{# ', ' #}', '*'),
            $this->licenseHeader->getTwigLicenseRegex('{# ', ' #}', '#'),
            // These are properly formatted headers but using the * delimiter
            $this->licenseHeader->getTwigLicenseRegex('{#', '#}', '*'),
        ];

        foreach ($regexpCandidates as $regexpCandidate) {
            if (preg_match($regexpCandidate, $fileContent)) {
                $this->addLicenseToFile($file, $regexpCandidate);

                return;
            }
        }

        // These are the most recent and adopted headers with only # which are more twig style
        $defaultTwigRegex = $this->licenseHeader->getRegexByType('twig');
        $this->addLicenseToFile($file, $defaultTwigRegex);
    }

    /**
     * @throws Exception
     */
    private function addLicenseToJsonFile(SplFileInfo $file): void
    {
        if (!in_array($file->getFilename(), ['composer.json', 'package.json'])) {
            return;
        }

        $content = json_decode($file->getContents(), true);

        $authorDetails = [
            'name' => 'PrestaShop SA',
            'email' => 'contact@prestashop.com',
        ];

        // update author information depending of file
        if ('composer.json' === $file->getFilename()) {
            $content['authors'] = [$authorDetails];
        } else { // package.json
            $content['author'] = $authorDetails;
        }

        $content['license'] = (false !== strpos($this->license, 'afl')) ? 'AFL-3.0' : 'OSL-3.0';

        $encodedContent = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if (!$encodedContent) {
            throw new Exception('File can not be encoded to JSON format');
        }

        // add blank line in end of file if not exist
        if (substr($encodedContent, -1) !== "\n") {
            $encodedContent .= "\n";
        }

        if (!$this->runAsDry) {
            file_put_contents(
                $this->targetDirectory . '/' . $file->getRelativePathname(),
                $encodedContent
            );
        }

        $this->reportOperationResult($encodedContent, $file->getContents(), $file->getRelativePathname());
    }

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

        if (!empty($report['fixed'])) {
            $style->text('Files with bad license headers:');
            $style->listing($report['fixed']);
        }
        if (!empty($report['failed'])) {
            $style->text('Failed fixing license headers:');
            $style->listing($report['failed']);
        }
    }
}
