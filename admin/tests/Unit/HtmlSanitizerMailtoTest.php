<?php

use App\Support\HtmlSanitizer;

it('repairs a Quill-split mailto address so the full email is linked', function () {
    $broken = '<p>email at <a href="mailto:hello@dokannward.com" rel="noopener noreferrer" target="_blank">support@d</a>okannward.com .</p>';

    $clean = HtmlSanitizer::clean($broken);

    expect($clean)->toContain('mailto:support@dokannward.com');
    expect($clean)->toContain('>support@dokannward.com</a>');
    expect($clean)->not->toContain('</a>okannward.com');
});

it('leaves complete mailto links untouched', function () {
    $ok = '<p>email at <a href="mailto:support@dokannward.com">support@dokannward.com</a>.</p>';

    expect(HtmlSanitizer::clean($ok))->toBe($ok);
});
