<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Coverage\CoverageReporter;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Support\Coverage;

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
    private function logCoverage(Options $options): void
    {
        if (!$options->hasCoverage()) {
            return;
        }

        $coverageMerger = $this->coverage;
        assert($coverageMerger !== null);
        $codeCoverage = $coverageMerger->getCodeCoverageObject();
        assert($codeCoverage !== null);
        $codeCoverageConfiguration = null;
        if (($configuration = $this->options->configuration()) !== null) {
            $codeCoverageConfiguration = $configuration->codeCoverage();
        }

        $reporter = new CoverageReporter($codeCoverage, $codeCoverageConfiguration);

        if (($coverageClover = $this->options->coverageClover()) !== null) {
            $reporter->clover($coverageClover);
        }

        if (($coverageCobertura = $this->options->coverageCobertura()) !== null) {
            $reporter->cobertura($coverageCobertura);
        }

        if (($coverageCrap4j = $this->options->coverageCrap4j()) !== null) {
            $reporter->crap4j($coverageCrap4j);
        }

        if (($coverageHtml = $this->options->coverageHtml()) !== null) {
            $reporter->html($coverageHtml);
        }

        if (($coverageText = $this->options->coverageText()) !== null) {
            if ($coverageText === '') {
                $this->output->write($reporter->text());
            } else {
                file_put_contents($coverageText, $reporter->text());
            }
        }

        if (($coverageXml = $this->options->coverageXml()) !== null) {
            $reporter->xml($coverageXml);
        }

        if (($coveragePhp = $this->options->coveragePhp()) !== null) {
            $reporter->php($coveragePhp);
        }

        if ($this->options->coveragePhp() !== null && file_exists(Coverage::getPath())) {
            Coverage::report($this->output);
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
