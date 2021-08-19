<?php

declare(strict_types=1);

uses()->group('runnable')
    ->beforeEach(function () {
        if (!($_SERVER['PEST_PARALLEL'] ?? false)) {
            $this->markTestSkipped('This test is run as part of internal test runner.');
        }
    })
    ->in('InternalRunnableTests');
