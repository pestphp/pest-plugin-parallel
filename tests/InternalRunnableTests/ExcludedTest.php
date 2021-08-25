<?php

uses()->group('exclude');

/*
 * Tests in this file are all excluded, which lets us test for edge
 * cases where there should be no output for excluded test cases.
 */

it('is excluded', function () {
    expect(true)->toBeTrue();
});
