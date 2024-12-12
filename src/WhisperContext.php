<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

use FFI;
use FFI\CData;

class WhisperContext
{
    private FFI $ffi;

    private mixed $ctx;

    /**
     * Create a new WhisperContext from a file, with parameters.
     *
     * @param string $modelPath The path to the model file.
     * @param WhisperContextParameters|null $params A parameter struct containing the parameters to use.
     *
     * @throws WhisperException
     */
    public function __construct(string $modelPath, ?WhisperContextParameters $params = null)
    {
        $libraryLoader = new LibraryLoader();
        $this->ffi = $libraryLoader->get('whisper');

        $this->setupLoggerCallback();

        $params ??= WhisperContextParameters::default();

        // Initialize context with parameters
        $cParams = $params->toCStruct($this->ffi);
        $this->ctx = $this->ffi->whisper_init_from_file_with_params($modelPath, $cParams);

        if ($this->ctx === null) {
            throw WhisperException::failedToCreateContext();
        }
    }

    public function createState(): WhisperState
    {
        $state = $this->ffi->whisper_init_state($this->ctx);
        if ($state === null) {
            throw WhisperException::failedToCreateState();
        }

        return new WhisperState($this->ffi, $this->ctx, $state);
    }

    /**
     * Convert the provided text into tokens.
     *
     * @param string $text The text to convert.
     * @param int $maxTokens The maximum number of tokens to return.
     */
    public function tokenize(string $text, int $maxTokens): array
    {
        $cText = $this->ffi->new('char['.(strlen($text) + 1).']');
        FFI::memcpy($cText, $text, strlen($text));

        $tokens = $this->ffi->new("whisper_token[$maxTokens]");
        $result = $this->ffi->whisper_tokenize(
            $this->ctx,
            $cText,
            $this->ffi->cast('whisper_token*', $tokens),
            $maxTokens
        );

        if ($result === -1) {
            throw WhisperException::invalidText();
        }

        // Convert FFI array to PHP array
        $output = [];
        for ($i = 0; $i < $result; $i++) {
            $output[] = $tokens[$i];
        }

        return $output;
    }

    /**
     * Return the number of tokens in the vocabulary
     */
    public function nVocab(): int
    {
        return $this->ffi->whisper_n_vocab($this->ctx);
    }

    /**
     * Return the number of text contexts
     */
    public function nTextCtx(): int
    {
        return $this->ffi->whisper_n_text_ctx($this->ctx);
    }

    /**
     * Return the number of audio contexts
     */
    public function nAudioCtx(): int
    {
        return $this->ffi->whisper_n_audio_ctx($this->ctx);
    }

    /**
     * Does this model support multiple languages?
     */
    public function isMultilingual(): bool
    {
        return (bool)$this->ffi->whisper_is_multilingual($this->ctx);
    }

    /**
     * Return the number of tokens in the model vocabulary
     */
    public function modelNVocab(): int
    {
        return $this->ffi->whisper_model_n_vocab($this->ctx);
    }

    public function modelNAudioCtx(): int
    {
        return $this->ffi->whisper_model_n_audio_ctx($this->ctx);
    }

    public function modelNAudioState(): int
    {
        return $this->ffi->whisper_model_n_audio_state($this->ctx);
    }

    public function modelNAudioHead(): int
    {
        return $this->ffi->whisper_model_n_audio_head($this->ctx);
    }

    public function modelNAudioLayer(): int
    {
        return $this->ffi->whisper_model_n_audio_layer($this->ctx);
    }

    public function modelNTextCtx(): int
    {
        return $this->ffi->whisper_model_n_text_ctx($this->ctx);
    }

    public function modelNTextState(): int
    {
        return $this->ffi->whisper_model_n_text_state($this->ctx);
    }

    public function modelNTextHead(): int
    {
        return $this->ffi->whisper_model_n_text_head($this->ctx);
    }

    public function modelNTextLayer(): int
    {
        return $this->ffi->whisper_model_n_text_layer($this->ctx);
    }

    public function modelNMels(): int
    {
        return $this->ffi->whisper_model_n_mels($this->ctx);
    }

    public function modelFtype(): int
    {
        return $this->ffi->whisper_model_ftype($this->ctx);
    }

    public function modelType(): int
    {
        return $this->ffi->whisper_model_type($this->ctx);
    }

    /**
     * Convert a token ID to a string.
     *
     * @param int $tokenId The ID of the token to convert.
     */
    public function tokenToStr(int $tokenId): string
    {
        $result = $this->ffi->whisper_token_to_str($this->ctx, $tokenId);
        if ($result === null) {
            throw WhisperException::nullPointer();
        }

        return FFI::string($result);
    }

    /**
     * Undocumented but exposed function in the C++ API.
     */
    public function modelTypeReadable(): string
    {
        $result = $this->ffi->whisper_model_type_readable($this->ctx);
        if ($result === null) {
            throw WhisperException::nullPointer();
        }

        return $result;
    }

    /**
     * Get the ID of the eot token.
     */
    public function tokenEot(): int
    {
        return $this->ffi->whisper_token_eot($this->ctx);
    }

    /**
     * Get the ID of the sot token.
     */
    public function tokenSot(): int
    {
        return $this->ffi->whisper_token_sot($this->ctx);
    }

    /**
     * Get the ID of the solm token.
     */
    public function tokenSolm(): int
    {
        return $this->ffi->whisper_token_solm($this->ctx);
    }

    /**
     * Get the ID of the prev token.
     */
    public function tokenPrev(): int
    {
        return $this->ffi->whisper_token_prev($this->ctx);
    }

    /**
     * Get the ID of the nosp token.
     */
    public function tokenNosp(): int
    {
        return $this->ffi->whisper_token_nosp($this->ctx);
    }

    /**
     * Get the ID of the not token.
     */
    public function tokenNot(): int
    {
        return $this->ffi->whisper_token_not($this->ctx);
    }

    /**
     * Get the ID of the beg token.
     */
    public function tokenBeg(): int
    {
        return $this->ffi->whisper_token_beg($this->ctx);
    }

    /**
     * Get the ID of a specified language token
     *
     * @param int $langId The ID of the language
     */
    public function tokenLang(int $langId): int
    {
        return $this->ffi->whisper_token_lang($this->ctx, $langId);
    }

    /**
     * Return the id of the specified language, returns -1 if not found
     *
     * @param string $lang The language to get the ID of
     */
    public function langId(string $lang): int
    {
        $len = strlen($lang) + 1;
        $cLang = $this->ffi->new("char[$len]");
        FFI::memcpy($cLang, $lang, strlen($lang));

        $result = $this->ffi->whisper_lang_id($this->ffi->cast('char *', $cLang));

        if ($result < 0) {
            throw WhisperException::invalidLanguage($lang);
        }

        return $result;
    }

    /**
     *  Return the short string of the specified language id (e.g. 2 -> "de"), returns nullptr if not found
     *
     * @param int $langId The ID of the language
     */
    public function langStr(int $langId): string
    {
        return $this->ffi->whisper_lang_str($langId);
    }

    /**
     * Return the short string of the specified language name (e.g. 2 -> "german"), returns nullptr if not found
     *
     * @param int $langId The ID of the language
     */
    public function langStrFull(int $langId): string
    {
        return $this->ffi->whisper_lang_str_full($langId);
    }

    /**
     * Print performance statistics to stderr.
     */
    public function printTimings(): void
    {
        $this->ffi->whisper_print_timings($this->ctx);
    }

    /**
     * Reset performance statistics.
     */
    public function resetTimings(): void
    {
        $this->ffi->whisper_reset_timings($this->ctx);
    }

    /**
     * Get the ID of the translate task token.
     */
    public function tokenTranslate(): int
    {
        return $this->ffi->whisper_token_translate($this->ctx);
    }

    /**
     * Get the ID of the transcribe task token.
     */
    public function tokenTranscribe(): int
    {
        return $this->ffi->whisper_token_transcribe($this->ctx);
    }

    /**
     * Number of generated text segments.
     *
     * A segment can be a few words, a sentence, or even a paragraph.
     */
    public function nSegments(): int
    {
        return $this->ffi->whisper_full_n_segments($this->ctx);
    }

    /**
     * Get the text of the segment at the specified index.
     *
     * @param int $index Segment index.
     */
    public function getSegmentText(int $index): string
    {
        return $this->ffi->whisper_full_get_segment_text($this->ctx, $index);
    }

    /**
     * Get the start time of the segment at the specified index.
     *
     * @param int $index Segment index.
     */
    public function getSegmentStartTime(int $index): int
    {
        return $this->ffi->whisper_full_get_segment_t0($this->ctx, $index);
    }

    /**
     * Get the end time of the segment at the specified index.
     *
     * @param int $index Segment index.
     */
    public function getSegmentEndTime(int $index): int
    {
        return $this->ffi->whisper_full_get_segment_t1($this->ctx, $index);
    }

    /**
     * Get number of tokens in the specified segment.
     *
     * @param int $index Segment index.
     */
    public function nTokens(int $index): int
    {
        return $this->ffi->whisper_full_n_tokens_from($this->ctx, $index);
    }

    /**
     * Get the token text of the specified token in the specified segment.
     *
     * @param int $index Segment index.
     * @param int $token Token index.
     */
    public function tokenText(int $index, int $token): string
    {
        return $this->ffi->whisper_full_get_token_text(
            $this->ctx,
            $index,
            $token
        );
    }

    public function tokenData(int $index, int $token): ?TokenData
    {
        $data = $this->ffi->whisper_full_get_token_data($this->ctx, $index, $token);

        return TokenData::fromCStruct($data);
    }

    /**
     * Get the token ID of the specified token in the specified segment.
     *
     * @param int $index Segment index.
     * @param int $token Token index.
     */
    public function tokenId(int $index, int $token): int
    {
        return $this->ffi->whisper_full_get_token_id($this->ctx, $index, $token);
    }

    /**
     * Get the probability of the specified token in the specified segment.
     *
     * @param int $index Segment index.
     * @param int $token Token index.
     */
    public function tokenProb(int $index, int $token): float
    {
        return $this->ffi->whisper_full_get_token_prob($this->ctx, $index, $token);
    }

    private function setupLoggerCallback(): void
    {
        $logger = Whisper::getLogger();

        if ($logger === null) {
            $logCallback = function () {};
        } else {
            $logCallback = function (int $level, string $message, ?CData $user_data) use ($logger) {
                $psrLevel = (LogLevel::tryFrom($level) ?? LogLevel::INFO)->toPsrLogLevel();
                try {
                    $logger->log($psrLevel, $message);
                } catch (\Throwable $e) {
                    fwrite(STDERR, $e->getMessage());
                }
            };
        }

        $this->ffi->whisper_log_set($logCallback, null);
    }

    public function __destruct()
    {
        if (isset($this->ctx)) {
            $this->ffi->whisper_free($this->ctx);
        }
    }
}
