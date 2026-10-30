<?php

namespace myprojecttests;

use Codeception\Test\Unit;
use statikbe\translate\helpers\TranslationFile;

class TranslationFileTest extends Unit
{
    private function evaluate(string $php): array
    {
        $file = tempnam(sys_get_temp_dir(), 'tf');
        file_put_contents($file, $php);
        $result = include $file;
        unlink($file);
        return $result;
    }

    public function testNewFile()
    {
        $php = TranslationFile::apply(null, ['b' => 'B', "it's" => 'Het is']);
        self::assertSame(['b' => 'B', "it's" => 'Het is'], $this->evaluate($php));
        self::assertStringStartsWith("<?php\n\nreturn [\n    'b' => 'B',", $php);
    }

    public function testReplacesAnExistingValueOnly()
    {
        $file = "<?php\n\n// Front end\nreturn [\n    'a.one' => 'Een',\n    'a.two' => \"Twee\",\n];\n";
        $php = TranslationFile::apply($file, ['a.two' => "Twee's"]);
        self::assertSame("<?php\n\n// Front end\nreturn [\n    'a.one' => 'Een',\n    'a.two' => 'Twee\\'s',\n];\n", $php);
    }

    public function testInsertsInOrderInASortedFile()
    {
        $file = "<?php\n\nreturn [\n    'a' => 'A',\n    'c' => 'C',\n];\n";
        $php = TranslationFile::apply($file, ['b' => 'B', 'd' => 'D']);
        self::assertSame("<?php\n\nreturn [\n    'a' => 'A',\n    'b' => 'B',\n    'c' => 'C',\n    'd' => 'D',\n];\n", $php);
    }

    public function testAppendsInAnUnsortedLongArrayFile()
    {
        $file = "<?php\n\nreturn array (\n\t'z' => 'Z',\n\t'a' => 'A'\n);";
        $php = TranslationFile::apply($file, ['m' => 'M']);
        self::assertSame(['z' => 'Z', 'a' => 'A', 'm' => 'M'], $this->evaluate($php));
        self::assertStringContainsString("\t'a' => 'A',\n\t'm' => 'M',\n);", $php);
    }

    public function testEmptyArray()
    {
        $php = TranslationFile::apply("<?php\n\nreturn [];\n", ['a' => 'A']);
        self::assertSame(['a' => 'A'], $this->evaluate($php));
    }
}
