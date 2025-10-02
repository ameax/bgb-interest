<?php

use Ameax\BgbInterest\BaseRateProvider;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/bgb-interest-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob("$this->tempDir/*"));
        rmdir($this->tempDir);
    }
});

it('can create provider with default temp directory', function () {
    $provider = new BaseRateProvider;

    expect($provider)->toBeInstanceOf(BaseRateProvider::class);
});

it('can create provider with custom directory', function () {
    $provider = new BaseRateProvider($this->tempDir);

    expect($provider->getCacheFilePath())
        ->toContain($this->tempDir)
        ->toEndWith('base_rates.json');
});

it('can parse xml and extract rates', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<message:GenericData xmlns:message="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message">
    <message:DataSet>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2023-01"/>
            <generic:ObsValue value="1.62"/>
        </generic:Obs>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2023-07"/>
            <generic:ObsValue value="3.12"/>
        </generic:Obs>
    </message:DataSet>
</message:GenericData>
XML;

    $provider = new BaseRateProvider($this->tempDir);
    $rates = $provider->parseXml($xml);

    expect($rates)->toBeArray()
        ->toHaveKey('2023-01')
        ->toHaveKey('2023-07')
        ->and($rates['2023-01'])->toBe(1.62)
        ->and($rates['2023-07'])->toBe(3.12);
});

it('filters out duplicate consecutive rates', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<message:GenericData xmlns:message="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message">
    <message:DataSet>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2023-01"/>
            <generic:ObsValue value="1.62"/>
        </generic:Obs>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2023-02"/>
            <generic:ObsValue value="1.62"/>
        </generic:Obs>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2023-07"/>
            <generic:ObsValue value="3.12"/>
        </generic:Obs>
    </message:DataSet>
</message:GenericData>
XML;

    $provider = new BaseRateProvider($this->tempDir);
    $rates = $provider->parseXml($xml);

    expect($rates)->toHaveCount(2)
        ->toHaveKey('2023-01')
        ->toHaveKey('2023-07')
        ->not->toHaveKey('2023-02'); // Filtered out because same as 2023-01
});

it('saves data to json with metadata', function () {
    $provider = new BaseRateProvider($this->tempDir);
    $data = [
        '2023-01' => 1.62,
        '2023-07' => 3.12,
    ];

    $filePath = $provider->saveToJson($data);

    expect($filePath)->toBeFile()
        ->and(file_exists($filePath))->toBeTrue();

    $content = json_decode(file_get_contents($filePath), true);

    expect($content)->toHaveKey('metadata')
        ->toHaveKey('data')
        ->and($content['metadata'])->toHaveKey('source_url')
        ->toHaveKey('last_updated')
        ->and($content['data'])->toBe($data);
});

it('creates cache directory if it does not exist', function () {
    $newDir = $this->tempDir.'/new/nested/dir';
    $provider = new BaseRateProvider($newDir);

    $provider->saveToJson(['2023-01' => 1.62]);

    expect($newDir)->toBeDirectory();

    // Cleanup nested directories
    array_map('unlink', glob("$newDir/*"));
    rmdir($newDir);
    rmdir(dirname($newDir, 1));
    rmdir(dirname($newDir, 2));
});

it('sorts rates by date oldest first', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<message:GenericData xmlns:message="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message">
    <message:DataSet>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2024-01"/>
            <generic:ObsValue value="3.62"/>
        </generic:Obs>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2023-01"/>
            <generic:ObsValue value="1.62"/>
        </generic:Obs>
        <generic:Obs xmlns:generic="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic">
            <generic:ObsDimension value="2023-07"/>
            <generic:ObsValue value="3.12"/>
        </generic:Obs>
    </message:DataSet>
</message:GenericData>
XML;

    $provider = new BaseRateProvider($this->tempDir);
    $rates = $provider->parseXml($xml);

    $keys = array_keys($rates);
    expect($keys[0])->toBe('2023-01')
        ->and($keys[1])->toBe('2023-07')
        ->and($keys[2])->toBe('2024-01');
});
