<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Coverage\CoverageReporter;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
trait HandlesCoverage
{
    /**
     * @var CoverageMerger|null
     */
    private $coverage;

    private function getCoverage(Options $options): ?CoverageMerger
    {
        if (!$options->hasCoverage()) {
            return null;
        }

        if ($this->coverage === null) {
            $this->coverage = new CoverageMerger($options->coverageTestLimit());
        }

        return $this->coverage;
    }

    /**
     * Log the coverage report if requested and available.
     */
    private function logCoverage(Options $options, OutputInterface $output): void
    {
        $coverageMerger = $this->getCoverage($options);

        if ($coverageMerger === null) {
            return;
        }

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
        $coverageMerger = $this->getCoverage($options);

        if ($coverageMerger === null) {
            return;
        }

        $coverageMerger->addCoverageFromFile($test->getCoverageFileName());
    }
}
