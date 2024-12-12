<?php

declare(strict_types=1);

use Codewithkyrian\Whisper\LibraryLoader;
use Codewithkyrian\Whisper\WhisperFullParams;
use Codewithkyrian\Whisper\WhisperGrammarElement;
use Codewithkyrian\Whisper\WhisperGrammarElementType;

beforeEach(function () {
    $this->ffi = (new LibraryLoader())->get('whisper');
});

it('correctly converts default parameters to C structure', function () {
    $params = WhisperFullParams::default();

    $cStruct = $params->toCStruct($this->ffi);

    expect($cStruct->strategy)->toBe($this->ffi->WHISPER_SAMPLING_GREEDY)
        ->and($cStruct->n_threads)->toBe(4)
        ->and($cStruct->n_max_text_ctx)->toBe(16384)
        ->and($cStruct->offset_ms)->toBe(0)
        ->and($cStruct->duration_ms)->toBe(0)
        ->and($cStruct->translate)->toBeFalse()
        ->and($cStruct->no_context)->toBeFalse();
});

it('sets language parameter correctly', function () {
    $params = WhisperFullParams::default()->withLanguage('en');

    $cStruct = $params->toCStruct($this->ffi);

    expect($cStruct->language)->not->toBeNull();
});

it('sets tokens parameter correctly', function () {
    $params = WhisperFullParams::default()->withTokens([1, 2, 3]);

    $cStruct = $params->toCStruct($this->ffi);

    expect($cStruct->prompt_n_tokens)->toBe(3);
});

it('sets grammar parameter correctly', function () {

    $grammar = [
        WhisperGrammarElement::make(WhisperGrammarElementType::Character, 120),
        WhisperGrammarElement::make(WhisperGrammarElementType::CharacterAlternate, 120),
    ];

    $params = WhisperFullParams::default()->withGrammar($grammar);

    $cStruct = $params->toCStruct($this->ffi);

    expect($cStruct->n_grammar_rules)->toBe(2);
});
