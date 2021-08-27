<?php

/**
 * This test suite contains failing and errant tests which allow us to check for
 * stop of error/failure clauses and other phpunit configurations.
 */
it('fails', function () {
    expect(false)->toBeTrue();
});

it('errors', function () {
    throw new Exception();
});

it('fails again', function () {
    // By including this test, we're able to make sure that the
    // errant test above can stop execution.
    expect(false)->toBeTrue();
});
