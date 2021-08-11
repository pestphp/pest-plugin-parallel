<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Coverage\CoverageReporter;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Support\Coverage;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
trait HandlesCoverage
{
    /**
     * CoverageMerger to hold track of the accumulated coverage.
     *
     * @var CoverageMerger|null
     */
    private $coverage = null;

    private function initCoverage(Options $options): void
    {
        if (!$options->hasCoverage()) {
            return;
        }

        $this->coverage = new CoverageMerger($options->coverageTestLimit());
    }

    /**
     * Output the coverage report if requested.
     */
    private function logCoverage(Options $options, OutputInterface $output): void
    {
        if (!$options->hasCoverage()) {
            return;
        }

        $coverageMerger = $this->coverage;
        assert($coverageMerger !== null);
        $codeCoverage = $coverageMerger->getCodeCoverageObject();
        assert($codeCoverage !== null);
        $codeCoverageConfiguration = null;
        if (($configuration = $options->configuration()) !== null) {
            $codeCoverageConfiguration = $configuration->codeCoverage();
        }

        $reporter = new CoverageReporter($codeCoverage, $codeCoverageConfiguration);

        if (($coverageClover = $options->coverageClover()) !== null) {
            $reporter->clover($coverageClover);
        }

        if (($coverageCobertura = $options->coverageCobertura()) !== null) {
            $reporter->cobertura($coverageCobertura);
        }

        if (($coverageCrap4j = $options->coverageCrap4j()) !== null) {
            $reporter->crap4j($coverageCrap4j);
        }

        if (($coverageHtml = $options->coverageHtml()) !== null) {
            $reporter->html($coverageHtml);
        }

        if (($coverageText = $options->coverageText()) !== null) {
            if ($coverageText === '') {
                $output->write($reporter->text());
            } else {
                file_put_contents($coverageText, $reporter->text());
            }
        }

        if (($coverageXml = $options->coverageXml()) !== null) {
            $reporter->xml($coverageXml);
        }

        if (($coveragePhp = $options->coveragePhp()) !== null) {
            $reporter->php($coveragePhp);
        }
    }

    /**
     * Add the given test's generated coverage file to the merger.
     */
    private function addCoverage(ExecutableTest $test, Options $options): void
    {
        if (!$options->hasCoverage()) {
            return;
        }

        $coverageMerger = $this->coverage;
        assert($coverageMerger !== null);
        $coverageMerger->addCoverageFromFile($test->getCoverageFileName());
    }
}
