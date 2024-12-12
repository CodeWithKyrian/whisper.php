<?php

declare(strict_types=1);

use Codewithkyrian\Whisper\DtwMode;
use Codewithkyrian\Whisper\DtwParameters;
use Codewithkyrian\Whisper\LibraryLoader;
use Codewithkyrian\Whisper\WhisperContextParameters;

beforeEach(function () {
    $this->ffi = (new LibraryLoader())->get('whisper');
});

it('correctly converts default parameters to C structure', function () {
    $params = WhisperContextParameters::default();

    $cStruct = $params->toCStruct($this->ffi);

    expect($cStruct->use_gpu)->toBeFalse();
    expect($cStruct->flash_attn)->toBeFalse();
    expect($cStruct->gpu_device)->toBe(0);
    expect($cStruct->dtw_token_timestamps)->toBeFalse();
    expect($cStruct->dtw_mem_size)->toBe(134217728);
    expect($cStruct->dtw_aheads_preset)->toBe($this->ffi->WHISPER_AHEADS_NONE);
    expect($cStruct->dtw_n_top)->toBe(-1);
});

it('sets parameters for DtwMode::TopMost correctly', function () {
    $dtwParameters = new DtwParameters(
        mode: DtwMode::TopMost,
        nTop: 5,
        dtwMemSize: 1024
    );

    $params = (new WhisperContextParameters)->withDtwParameters($dtwParameters);

    $cStruct = $params->toCStruct($this->ffi);

    expect($cStruct->dtw_token_timestamps)->toBeTrue();
    expect($cStruct->dtw_mem_size)->toBe(1024);
    expect($cStruct->dtw_aheads_preset)->toBe($this->ffi->WHISPER_AHEADS_N_TOP_MOST);
    expect($cStruct->dtw_n_top)->toBe(5);
});

it('sets parameters for DtwMode::Custom correctly', function () {
    $aheads = [
        ['n_text_layer' => 3, 'n_head' => 2],
        ['n_text_layer' => 4, 'n_head' => 1],
    ];

    $dtwParameters = new DtwParameters(
        mode: DtwMode::Custom,
        aheads: $aheads,
        dtwMemSize: 512
    );

    $params = (new WhisperContextParameters)->withDtwParameters($dtwParameters);

    $cStruct = $params->toCStruct($this->ffi);

    expect($cStruct->dtw_token_timestamps)->toBeTrue();
    expect($cStruct->dtw_mem_size)->toBe(512);
    expect($cStruct->dtw_aheads_preset)->toBe($this->ffi->WHISPER_AHEADS_CUSTOM);
    expect($cStruct->dtw_aheads->n_heads)->toBe(count($aheads));

    // Verify individual heads
    //    $heads = $cStruct->dtw_aheads->heads;
    //    expect($heads[0]->n_text_layer)->toBe(3);
    //    expect($heads[0]->n_text_head)->toBe(2);
    //    expect($heads[1]->n_text_layer)->toBe(4);
    //    expect($heads[1]->n_text_head)->toBe(1);
});
