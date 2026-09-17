<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\TypeCatalog;
use Modules\Custom\MakerBids\Support\UploadRules;
use Modules\Custom\MakerBids\Support\BlankToNull;

require __DIR__.'/bootstrap.php';

// --- TypeCatalog::coerceTypeInput ---
expect('slug string passthrough', TypeCatalog::coerceTypeInput('modeling_3d'), 'modeling_3d');
expect('option object value', TypeCatalog::coerceTypeInput(['value' => 'print_3d', 'label' => '출력']), 'print_3d');
expect('option object slug', TypeCatalog::coerceTypeInput(['slug' => 'full_package', 'name' => '풀']), 'full_package');
expect('junk object Object', TypeCatalog::coerceTypeInput('[object Object]'), '');
expect('junk Array', TypeCatalog::coerceTypeInput('Array'), '');
expect('null empty', TypeCatalog::coerceTypeInput(null), '');
expect('nested list first', TypeCatalog::coerceTypeInput([['value' => 'design_mockup']]), 'design_mockup');

// --- UploadRules::parseExtensionList ---
expect('ext string list', UploadRules::parseExtensionList('stl, obj'), ['STL', 'OBJ']);
expect('ext object items', UploadRules::parseExtensionList([
    ['value' => '3mf'],
    ['label' => 'FBX'],
    ['ext' => 'pdf'],
]), ['3MF', 'FBX', 'PDF']);
expect('ext rejects object Object split junk', UploadRules::parseExtensionList(['[OBJECT', 'OBJECT]']), []);
expect('ext plain object values', UploadRules::parseExtensionList(['a' => 'STL', 'b' => 'DWG']), ['STL', 'DWG']);

// --- BlankToNull slugString via anon ---
$req = new class {
    use BlankToNull;

    public function expose(mixed $v, string $prefer = 'value'): string
    {
        return $this->slugString($v, $prefer);
    }
};
expect('BlankToNull object option', $req->expose(['value' => 'modeling_3d', 'label' => '모델링']), 'modeling_3d');
expect('BlankToNull junk string', $req->expose('[object Object]'), '');
expect('BlankToNull Array junk', $req->expose('Array'), '');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
