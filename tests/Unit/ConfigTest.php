<?php

use Ameax\BgbInterest\Config;

it('can create config with default values', function () {
    $config = new Config;

    expect($config->getCacheDirectory())->toBe(sys_get_temp_dir())
        ->and($config->getConsumerSurcharge())->toBe(5.0)
        ->and($config->getBusinessSurcharge())->toBe(9.0);
});

it('can create config with custom cache directory', function () {
    $config = new Config('/custom/path');

    expect($config->getCacheDirectory())->toBe('/custom/path');
});

it('can create config with custom surcharge rates', function () {
    $config = new Config(null, 7.5, 12.0);

    expect($config->getConsumerSurcharge())->toBe(7.5)
        ->and($config->getBusinessSurcharge())->toBe(12.0);
});

it('can create config with all custom values', function () {
    $config = new Config('/my/cache', 10.0, 15.0);

    expect($config->getCacheDirectory())->toBe('/my/cache')
        ->and($config->getConsumerSurcharge())->toBe(10.0)
        ->and($config->getBusinessSurcharge())->toBe(15.0);
});

it('can set cache directory', function () {
    $config = new Config;
    $config->setCacheDirectory('/new/path');

    expect($config->getCacheDirectory())->toBe('/new/path');
});

it('can chain cache directory setter', function () {
    $config = new Config;
    $result = $config->setCacheDirectory('/new/path');

    expect($result)->toBe($config);
});

it('can set consumer surcharge', function () {
    $config = new Config;
    $config->setConsumerSurcharge(8.0);

    expect($config->getConsumerSurcharge())->toBe(8.0);
});

it('can chain consumer surcharge setter', function () {
    $config = new Config;
    $result = $config->setConsumerSurcharge(8.0);

    expect($result)->toBe($config);
});

it('can set business surcharge', function () {
    $config = new Config;
    $config->setBusinessSurcharge(12.0);

    expect($config->getBusinessSurcharge())->toBe(12.0);
});

it('can chain business surcharge setter', function () {
    $config = new Config;
    $result = $config->setBusinessSurcharge(12.0);

    expect($result)->toBe($config);
});

it('can chain multiple setters', function () {
    $config = new Config;

    $config->setCacheDirectory('/path')
        ->setConsumerSurcharge(6.0)
        ->setBusinessSurcharge(10.0);

    expect($config->getCacheDirectory())->toBe('/path')
        ->and($config->getConsumerSurcharge())->toBe(6.0)
        ->and($config->getBusinessSurcharge())->toBe(10.0);
});

it('preserves default values when only some parameters are set', function () {
    $config = new Config('/custom/path');

    expect($config->getCacheDirectory())->toBe('/custom/path')
        ->and($config->getConsumerSurcharge())->toBe(5.0)
        ->and($config->getBusinessSurcharge())->toBe(9.0);
});
