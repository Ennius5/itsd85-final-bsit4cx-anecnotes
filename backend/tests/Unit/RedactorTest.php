<?php

use App\Services\Ai\Redactor;

it('replaces student names in any order, case-insensitively', function () {
    $r = new Redactor;
    $text = $r->redact('Juan Dela Cruz shoved Ana. Later dela cruz, juan apologized.', [
        ['[STUDENT]', ['Juan', 'Dela Cruz']],
        ['[STUDENT_2]', ['Ana', 'Garcia']],
    ]);

    expect($text)->toBe('[STUDENT] shoved [STUDENT_2]. Later [STUDENT] apologized.')
        ->and($r->map())->toHaveKey('[STUDENT]');
});

it('does not replace names inside other words', function () {
    $text = (new Redactor)->redact('Ana ate a banana in the canteen.', [['[STUDENT]', ['Ana']]]);

    expect($text)->toBe('[STUDENT] ate a banana in the canteen.');
});

it('removes LRNs, phone numbers and emails', function () {
    $text = (new Redactor)->redact('LRN 123456789012, call 09171234567 or +63 917 123 4567, email mom@example.com.');

    expect($text)->not->toContain('123456789012')
        ->not->toContain('0917')
        ->not->toContain('mom@example.com')
        ->toContain('[LRN]')->toContain('[PHONE]')->toContain('[EMAIL]');
});

it('handles Filipino text and accented names', function () {
    $text = (new Redactor)->redact('Si Niño ay umiiyak sa klase.', [['[STUDENT]', ['Niño', 'Peña']]]);

    expect($text)->toBe('Si [STUDENT] ay umiiyak sa klase.');
});
