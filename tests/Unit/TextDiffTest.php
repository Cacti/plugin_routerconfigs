<?php

declare(strict_types = 1);

$base = dirname(__DIR__, 2) . '/Text';
require_once $base . '/Exception/Wrapped.php';
require_once $base . '/Diff/Exception.php';
require_once $base . '/Util/String.php';
require_once $base . '/Diff/Op/Base.php';
require_once $base . '/Diff/Op/Copy.php';
require_once $base . '/Diff/Op/Add.php';
require_once $base . '/Diff/Op/Delete.php';
require_once $base . '/Diff/Op/Change.php';
require_once $base . '/Diff/Engine/native.php';
require_once $base . '/Diff/Engine/string.php';
require_once $base . '/Diff.php';

describe('Horde_Text_Diff_Engine_Native: identical files', function (): void {
	it('produces only Copy operations for identical input', function (): void {
		$lines  = ['line one', 'line two', 'line three'];
		$engine = new Horde_Text_Diff_Engine_Native();
		$edits  = $engine->diff($lines, $lines);

		expect($edits)->toHaveCount(1);
		expect($edits[0])->toBeInstanceOf(Horde_Text_Diff_Op_Copy::class);
		expect($edits[0]->orig)->toBe(['line one', 'line two', 'line three']);
		expect($edits[0]->final)->toBe(['line one', 'line two', 'line three']);
	});

	it('produces a single Copy for single identical line', function (): void {
		$lines  = ['only line'];
		$engine = new Horde_Text_Diff_Engine_Native();
		$edits  = $engine->diff($lines, $lines);

		expect($edits)->toHaveCount(1);
		expect($edits[0])->toBeInstanceOf(Horde_Text_Diff_Op_Copy::class);
	});

	it('returns empty edits for two empty arrays', function (): void {
		$engine = new Horde_Text_Diff_Engine_Native();
		$edits  = $engine->diff([], []);

		expect($edits)->toBe([]);
	});
});

describe('Horde_Text_Diff_Engine_String: edge cases in parsing', function (): void {
	it('parses unified diff with whitespace-only content lines', function (): void {
		$patch  = "@@ -1,2 +1,2 @@\n- \n+ \t\n";
		$engine = new Horde_Text_Diff_Engine_String();
		$edits  = $engine->diff($patch, 'unified');

		expect($edits)->not->toBeEmpty();
		expect($edits[0])->toBeInstanceOf(Horde_Text_Diff_Op_Change::class);
		expect($edits[0]->orig)->toBe(['']);
		expect($edits[0]->final)->toBe(["\t"]);
	});

	it('parses unified diff with empty added line', function (): void {
		$patch  = "@@ -1,1 +1,2 @@\n foo\n+\n";
		$engine = new Horde_Text_Diff_Engine_String();
		$edits  = $engine->diff($patch, 'unified');

		$types = array_map('get_class', $edits);
		expect($types)->toContain(Horde_Text_Diff_Op_Copy::class);
		expect($types)->toContain(Horde_Text_Diff_Op_Add::class);
	});

	it('handles unified diff with only context (space-prefixed) lines', function (): void {
		$patch  = "@@ -1,2 +1,2 @@\n alpha\n beta\n";
		$engine = new Horde_Text_Diff_Engine_String();
		$edits  = $engine->diff($patch, 'unified');

		expect($edits)->toHaveCount(1);
		expect($edits[0])->toBeInstanceOf(Horde_Text_Diff_Op_Copy::class);
		expect($edits[0]->orig)->toBe(['alpha', 'beta']);
	});

	it('returns empty edits for a patch with only hunk header', function (): void {
		$patch  = "@@ -1,0 +1,0 @@\n";
		$engine = new Horde_Text_Diff_Engine_String();
		$edits  = $engine->diff($patch, 'unified');

		expect($edits)->toBe([]);
	});
});

describe('Horde_Text_Diff: reverse operation', function (): void {
	it('produces a reversed diff that swaps original and final', function (): void {
		$from = ['aaa', 'bbb', 'ccc'];
		$to   = ['aaa', 'xxx', 'ccc'];
		$diff = new Horde_Text_Diff('Native', [$from, $to]);
		$rev  = $diff->reverse();

		expect($rev)->toBeInstanceOf(Horde_Text_Diff::class);
		expect($rev->getOriginal())->toBe($to);
		expect($rev->getFinal())->toBe($from);
	});

	it('does not mutate the original diff when reversing', function (): void {
		$from = ['one', 'two'];
		$to   = ['one', 'three'];
		$diff = new Horde_Text_Diff('Native', [$from, $to]);

		$origBefore  = $diff->getOriginal();
		$finalBefore = $diff->getFinal();

		$diff->reverse();

		expect($diff->getOriginal())->toBe($origBefore);
		expect($diff->getFinal())->toBe($finalBefore);
	});

	it('reverse of identical files is still empty', function (): void {
		$lines = ['same', 'content'];
		$diff  = new Horde_Text_Diff('Native', [$lines, $lines]);

		expect($diff->isEmpty())->toBeTrue();

		$rev = $diff->reverse();
		expect($rev->isEmpty())->toBeTrue();
		expect($rev->getOriginal())->toBe($lines);
		expect($rev->getFinal())->toBe($lines);
	});

	it('double-reverse restores original diff', function (): void {
		$from = ['alpha', 'beta'];
		$to   = ['alpha', 'gamma', 'delta'];
		$diff = new Horde_Text_Diff('Native', [$from, $to]);

		$doubleRev = $diff->reverse()->reverse();
		expect($doubleRev->getOriginal())->toBe($from);
		expect($doubleRev->getFinal())->toBe($to);
	});
});

describe('Horde_Text_Diff_Engine_Native: large input arrays', function (): void {
	it('computes correct diff for 1000-line identical files', function (): void {
		$lines  = array_map(fn (int $i): string => "line $i", range(1, 1000));
		$engine = new Horde_Text_Diff_Engine_Native();
		$edits  = $engine->diff($lines, $lines);

		expect($edits)->toHaveCount(1);
		expect($edits[0])->toBeInstanceOf(Horde_Text_Diff_Op_Copy::class);
		expect($edits[0]->norig())->toBe(1000);
	});

	it('detects single change in 1000-line files', function (): void {
		$from    = array_map(fn (int $i): string => "line $i", range(1, 1000));
		$to      = $from;
		$to[499] = 'CHANGED LINE';

		$engine = new Horde_Text_Diff_Engine_Native();
		$edits  = $engine->diff($from, $to);

		$changeCount = 0;
		$copyCount   = 0;

		foreach ($edits as $edit) {
			if ($edit instanceof Horde_Text_Diff_Op_Change) {
				$changeCount++;
				expect($edit->orig)->toBe(['line 500']);
				expect($edit->final)->toBe(['CHANGED LINE']);
			} elseif ($edit instanceof Horde_Text_Diff_Op_Copy) {
				$copyCount++;
			}
		}

		expect($changeCount)->toBe(1);
		expect($copyCount)->toBe(2);
	});

	it('handles large addition at end of file', function (): void {
		$from = array_map(fn (int $i): string => "line $i", range(1, 500));
		$to   = array_merge($from, array_map(fn (int $i): string => "new $i", range(1, 500)));

		$engine = new Horde_Text_Diff_Engine_Native();
		$edits  = $engine->diff($from, $to);

		$addedLines = 0;

		foreach ($edits as $edit) {
			if ($edit instanceof Horde_Text_Diff_Op_Add) {
				$addedLines += $edit->nfinal();
			}
		}

		expect($addedLines)->toBe(500);
	});
});
