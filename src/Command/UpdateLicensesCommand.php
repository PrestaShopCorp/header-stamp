<?php
/**
 * 2007-2019 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\PimpMyHeader\Command;

use PhpParser\ParserFactory;
use PrestaShop\PimpMyHeader\LicenseHeader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class UpdateLicensesCommand extends Command
{
    const DEFAULT_LICENSE_FILE = __DIR__ . '/../../assets/osl3.txt';

    private $text;

    private $license;

    private $filters = [];

    private $extensions = [
        'php',
        'js',
        'css',
        'tpl',
        'html.twig',
        'json',
        'vue',
    ];

    protected function configure()
    {
        $this
            ->setName('prestashop:licenses:update')
            ->setDescription('Rewrite your licenses to be up-to-date');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        /*
            - Filter folders
            - List of file extensions to update
            - License block to use
            - Configuration file to use
         */
        $this->license = self::DEFAULT_LICENSE_FILE;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->text = str_replace(
            '{currentYear}',
            date('Y'),
            (new LicenseHeader($this->license))->getContent()
        );

        foreach ($this->extensions as $extension) {
            $this->findAndCheckExtension($output, $extension);
        }
    }

    private function findAndCheckExtension(OutputInterface $output, $ext)
    {
        $finder = new Finder();
        $finder
            ->files()
            ->name('*.' . $ext)
            ->in(getcwd())
            ->exclude($this->filters);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $output->writeln('Updating license in ' . strtoupper($ext) . ' files ...');
        $progress = new ProgressBar($output, count($finder));
        $progress->start();
        $progress->setRedrawFrequency(20);

        foreach ($finder as $file) {
            $this->license = $this->text;

            switch ($file->getExtension()) {
                case 'php':
                    try {
                        $nodes = $parser->parse($file->getContents());
                        if (count($nodes)) {
                            $this->addLicenseToNode($nodes[0], $file);
                        }
                    } catch (\PhpParser\Error $exception) {
                        $output->writeln('Syntax error on file ' . $file->getRelativePathname() . '. Continue ...');
                    }

                    break;
                case 'js':
                case 'css':
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

    private function addLicenseToFile($file, $startDelimiter = '\/', $endDelimiter = '\/')
    {
        $content = $file->getContents();
        // Regular expression found thanks to Stephen Ostermiller's Blog. http://blog.ostermiller.org/find-comment
        $regex = '%' . $startDelimiter . '\*([^*]|[\r\n]|(\*+([^*' . $endDelimiter . ']|[\r\n])))*\*+' . $endDelimiter . '%';
        $matches = array();
        $text = $this->license;
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
                if (stripos($match, 'prestashop') !== false) {
                    $content = str_replace($match, $text, $content);
                }
            }
        } else {
            // Not found - Add it at the beginning of the file
            $content = $text . "\n" . $content;
        }

        file_put_contents($file->getRelativePathname(), $content);
    }

    /**
     * @param $node
     * @param SplFileInfo $file
     */
    private function addLicenseToNode($node, SplFileInfo $file)
    {
        if (!$node->hasAttribute('comments')) {
            $needle = '<?php';
            $replace = "<?php\n" . $this->license . "\n";
            $haystack = $file->getContents();

            $pos = strpos($haystack, $needle);
            // Important, if the <?php is in the middle of the file, continue
            if ($pos === 0) {
                $newstring = substr_replace($haystack, $replace, $pos, strlen($needle));
                file_put_contents($file->getRelativePathname(), $newstring);
            }

            return;
        }

        $comments = $node->getAttribute('comments');
        foreach ($comments as $comment) {
            if ($comment instanceof \PhpParser\Comment
                && strpos($comment->getText(), 'prestashop') !== false) {
                file_put_contents($file->getRelativePathname(), str_replace($comment->getText(), $this->license, $file->getContents()));
            }
        }
    }

    /**
     * @param SplFileInfo $file
     */
    private function addLicenseToSmartyTemplate(SplFileInfo $file)
    {
        $this->addLicenseToFile($file, '{', '}');
    }

    /**
     * @param SplFileInfo $file
     */
    private function addLicenseToTwigTemplate(SplFileInfo $file)
    {
        if (strrpos($file->getRelativePathName(), 'html.twig') !== false) {
            $this->addLicenseToFile($file, '{#', '#}');
        }
    }

    /**
     * @param SplFileInfo $file
     */
    private function addLicenseToHtmlFile(SplFileInfo $file)
    {
        $this->addLicenseToFile($file, '<!--', '-->');
    }

    /**
     * @param SplFileInfo $file
     *
     * @return bool
     */
    private function addLicenseToJsonFile(SplFileInfo $file)
    {
        if (!in_array($file->getFilename(), array('composer.json', 'package.json'))) {
            return false;
        }

        $content = (array) json_decode($file->getContents());
        $content['author'] = 'PrestaShop';
        $content['license'] = strpos($this->license, 'afl') ? 'AFL-3.0' : 'OSL-3.0';

        return file_put_contents($file->getRelativePathname(), json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
