<?php

require_once 'php/Flv2Fmp4.php';

echo "FLV to MP4 Converter Test\n";
echo "========================\n\n";

function testBasicConversion() {
    $flv2fmp4 = new Flv2Fmp4();

    $flv2fmp4->onInitSegment = function($data) {
        echo "✓ Init segment generated (size: " . count($data) . " bytes)\n";
        file_put_contents('output/init.mp4', pack('C*', ...$data));
    };

    $flv2fmp4->onMediaSegment = function($data) {
        static $segmentCount = 0;
        $segmentCount++;
        echo "✓ Media segment " . $segmentCount . " generated (size: " . count($data) . " bytes)\n";
        file_put_contents('output/segment_' . $segmentCount . '.m4s', pack('C*', ...$data));
    };

    $flv2fmp4->onMediaInfo = function($mediaInfo, $tracks) {
        echo "\nMedia Info:\n";
        echo "  - Has Audio: " . ($tracks['hasAudio'] ? 'Yes' : 'No') . "\n";
        echo "  - Has Video: " . ($tracks['hasVideo'] ? 'Yes' : 'No') . "\n";
        if ($mediaInfo) {
            echo "  - Width: " . $mediaInfo->width . "\n";
            echo "  - Height: " . $mediaInfo->height . "\n";
            echo "  - FPS: " . $mediaInfo->fps . "\n";
            echo "  - Duration: " . $mediaInfo->duration . "\n";
            echo "  - MIME Type: " . $mediaInfo->mimeType . "\n";
        }
    };

    if (!file_exists('test.flv')) {
        echo "✗ Test file 'test.flv' not found. Please provide a test FLV file.\n";
        return;
    }

    $flvData = file_get_contents('test.flv');
    $flvBytes = unpack('C*', $flvData);
    $flvArray = array_values($flvBytes);

    echo "Processing FLV file (size: " . count($flvArray) . " bytes)...\n\n";

    try {
        $offset = $flv2fmp4->setflv($flvArray);
        echo "\n✓ Processing completed. Offset: " . $offset . "\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

function testModuleLoading() {
    echo "Testing module loading...\n";
    
    $modules = [
        'FlvTag',
        'FlvParse',
        'FlvDemux',
        'ExpGolomb',
        'SPSParser',
        'MediaInfo',
        'MediaSegmentInfo',
        'MP4Remux',
        'AACSilent',
        'MP4Moof',
        'TagDemux',
        'decodeUTF8'
    ];

    foreach ($modules as $module) {
        $file = 'php/flv/' . $module . '.php';
        if (!file_exists($file)) {
            $file = 'php/mp4/' . $module . '.php';
        }
        if (!file_exists($file)) {
            $file = 'php/utils/' . $module . '.php';
        }
        
        if (file_exists($file)) {
            require_once $file;
            echo "  ✓ " . $module . " loaded\n";
        } else {
            echo "  ✗ " . $module . " not found\n";
        }
    }
}

// Create output directory
if (!file_exists('output')) {
    mkdir('output', 0777, true);
}

echo "1. Testing module loading:\n";
echo "--------------------------\n";
testModuleLoading();

echo "\n2. Testing basic conversion:\n";
echo "----------------------------\n";
testBasicConversion();

echo "\nTest completed!\n";
?>