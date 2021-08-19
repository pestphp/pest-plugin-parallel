<?php

/**
 * This test suite is excluded as part of the standard test run and
 * simply provides a set of tests that can be executed internally
 * in parallel to ensure the success of failure of various pieces
 * of functionality.
 */

it('passes once', function() {
    expect(true)->toBeTrue();
});

it('passes twice', function() {
    expect(false)->toBeFalse();
});

it('also fails', function() {
    expect(true)->toBeFalse();
});

it('also fails again', function() {
    expect(false)->toBeTrue();
});
