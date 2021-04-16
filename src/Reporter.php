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
 * Reporter in charge of reporting what HeaderStamp has done
 */
class Reporter
{
    /**
     * @var array<string, array<int, string>>
     */
    private $report = [
        'fixed' => [],
        'ignored' => [],
        'failed' => [],
    ];

    public function reportLicenseHasBeenFixed(string $fixedFilename): void
    {
        $this->report['fixed'][] = $fixedFilename;
    }

    public function reportLicenseWasFine(string $fixedFilename): void
    {
        $this->report['nothing to fix'][] = $fixedFilename;
    }

    public function reportLicenseCouldNotBeFixed(string $fixedFilename): void
    {
        $this->report['failed'][] = $fixedFilename;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getReport(): array
    {
        return $this->report;
    }
}
